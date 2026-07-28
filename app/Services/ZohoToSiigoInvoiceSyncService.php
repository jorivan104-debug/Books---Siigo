<?php

namespace App\Services;

use App\DTOs\SiigoInvoicePayload;
use App\Enums\IntegrationType;
use App\Exceptions\ExternalApiException;
use App\Exceptions\InvoiceAlreadySyncedException;
use App\Models\IntegrationLog;
use App\Support\SiigoIdTypeMapper;
use App\Support\ZohoCustomFieldHelper;
use Carbon\Carbon;
use InvalidArgumentException;

class ZohoToSiigoInvoiceSyncService
{
    public function __construct(
        private readonly ZohoBooksService $zoho,
        private readonly SiigoCustomerService $siigoCustomers,
        private readonly SiigoInvoiceService $siigoInvoices,
        private readonly IntegrationLogService $logs,
    ) {
    }

    /**
     * Orquesta toda la sincronización de una factura Zoho hacia Siigo.
     *
     * @return array{success:bool,message:string,siigo_invoice_id?:string,siigo_invoice_link?:string,details?:array,log_id?:int}
     */
    public function sync(string $organizationId, string $invoiceId): array
    {
        $log = $this->logs->start(
            IntegrationType::ZohoSiigo,
            'invoice_sync',
            $organizationId,
            $invoiceId,
            ['organization_id' => $organizationId, 'invoice_id' => $invoiceId],
        );

        try {
            return $this->run($log, $organizationId, $invoiceId);
        } catch (InvoiceAlreadySyncedException $e) {
            $payload = [
                'success' => false,
                'message' => $e->getMessage(),
                'siigo_invoice_link' => $e->existingLink,
                'details' => ['reason' => 'already_synced'],
                'hub_build' => SiigoInvoiceService::HUB_BUILD,
                'log_id' => $log?->id,
            ];
            $this->logs->skipped($log, $e->getMessage(), $payload);

            return $payload;
        } catch (InvalidArgumentException $e) {
            $payload = [
                'success' => false,
                'message' => $e->getMessage(),
                'details' => ['reason' => 'validation'],
                'hub_build' => SiigoInvoiceService::HUB_BUILD,
                'log_id' => $log?->id,
            ];
            $this->logs->failed($log, $e->getMessage(), ['reason' => 'validation'], $payload);

            return $payload;
        } catch (ExternalApiException $e) {
            $payload = [
                'success' => false,
                'message' => $e->getMessage(),
                'details' => $e->context(),
                'hub_build' => SiigoInvoiceService::HUB_BUILD,
                'log_id' => $log?->id,
            ];
            $this->logs->failed($log, $e->getMessage(), $e->context(), $payload);

            return $payload;
        } catch (\Throwable $e) {
            $payload = [
                'success' => false,
                'message' => 'Error inesperado durante la sincronización. ['.SiigoInvoiceService::HUB_BUILD.']',
                'details' => ['exception' => $e->getMessage()],
                'hub_build' => SiigoInvoiceService::HUB_BUILD,
                'log_id' => $log?->id,
            ];
            $this->logs->failed($log, $e->getMessage(), ['exception' => $e::class, 'trace' => $e->getMessage()], $payload);

            return $payload;
        }
    }

    private function run(?IntegrationLog $log, string $organizationId, string $invoiceId): array
    {
        $invoice = $this->zoho->getInvoice($organizationId, $invoiceId);

        $linkApiName = (string) config('zoho.custom_fields.invoice.link_factura_siigo');
        $existingLink = ZohoCustomFieldHelper::getValue($invoice, $linkApiName);
        if ($existingLink !== null) {
            throw new InvoiceAlreadySyncedException($existingLink);
        }

        $contactId = (string) ($invoice['customer_id'] ?? '');
        if ($contactId === '') {
            throw new InvalidArgumentException('La factura no tiene customer_id en Zoho.');
        }
        $contact = $this->zoho->getContact($organizationId, $contactId);

        $tipoIdentApi = (string) config('zoho.custom_fields.contact.tipo_identificacion');
        $numeroIdentApi = (string) config('zoho.custom_fields.contact.numero_identificacion');

        $tipoIdentValue = ZohoCustomFieldHelper::getValue($contact, $tipoIdentApi);
        $numeroIdent = ZohoCustomFieldHelper::getValue($contact, $numeroIdentApi);

        if ($numeroIdent === null) {
            throw new InvalidArgumentException(
                "El contacto en Zoho no tiene número de identificación (campo {$numeroIdentApi})."
            );
        }

        $idType = SiigoIdTypeMapper::fromZoho($tipoIdentValue);

        $existingCustomer = $this->siigoCustomers->findByIdentification($numeroIdent);
        if ($existingCustomer !== null) {
            $customerBlock = [
                'identification' => $numeroIdent,
                'branch_office' => (int) ($existingCustomer['branch_office'] ?? 0),
            ];
        } else {
            $customerBlock = $this->siigoCustomers->buildCustomerPayloadFromZoho(
                $contact,
                $numeroIdent,
                $idType,
            );
        }

        $items = $this->siigoInvoices->buildItemsFromZohoLineItems(
            (array) ($invoice['line_items'] ?? [])
        );
        if (empty($items)) {
            throw new InvalidArgumentException('La factura de Zoho no tiene ítems para sincronizar.');
        }

        // El pago debe coincidir con el total que Siigo calcula (base + IVA), no solo con Zoho.total.
        // Si se envía 78000 y Siigo calcula 92820 → invalid_total_payments.
        $total = $this->siigoInvoices->estimateTotalWithTax($items);
        $siigoDate = $this->resolveInvoiceDate($invoice);
        $payments = $this->siigoInvoices->buildPayment(
            $total,
            $this->resolveDueDate($invoice, $siigoDate),
        );

        $dto = new SiigoInvoicePayload(
            documentId: (int) config('siigo.document_id'),
            date: $siigoDate,
            customer: $customerBlock,
            seller: (int) config('siigo.seller_id'),
            items: $items,
            payments: $payments,
            observations: $this->buildObservations($invoice, $siigoDate),
            sendToDian: (bool) config('siigo.invoice.send_to_dian', false),
            sendMail: (bool) config('siigo.invoice.send_mail', false),
        );

        $siigoResponse = $this->siigoInvoices->create($dto);
        $siigoInvoiceId = (string) ($siigoResponse['id'] ?? '');
        $siigoInvoiceLink = $this->extractSiigoLink($siigoResponse);

        if ($siigoInvoiceId === '') {
            throw new ExternalApiException(
                'Siigo no devolvió el id de la factura creada.',
                'siigo',
                null,
                $siigoResponse,
            );
        }

        $linkToStore = $siigoInvoiceLink ?? $siigoInvoiceId;
        $customFieldId = $this->zoho->findCustomFieldId($organizationId, 'invoice', $linkApiName);

        if ($customFieldId !== null) {
            $this->zoho->updateInvoiceCustomField(
                $organizationId,
                $invoiceId,
                $customFieldId,
                $linkToStore,
            );
        }

        $payload = [
            'success' => true,
            'message' => 'Factura sincronizada correctamente.',
            'siigo_invoice_id' => $siigoInvoiceId,
            'siigo_invoice_link' => $linkToStore,
            'hub_build' => \App\Services\SiigoInvoiceService::HUB_BUILD,
            'log_id' => $log?->id,
        ];

        $this->logs->success($log, $payload, 'Factura sincronizada correctamente.', $siigoInvoiceId);

        return $payload;
    }

    private function resolveInvoiceDate(array $invoice): string
    {
        $today = now()->startOfDay();
        $mode = strtolower((string) config('siigo.invoice.date_mode', 'today'));

        if ($mode === 'today') {
            return $today->toDateString();
        }

        $raw = $invoice['date'] ?? null;
        if (! is_string($raw) || trim($raw) === '') {
            return $today->toDateString();
        }

        try {
            $zohoDate = Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return $today->toDateString();
        }

        $maxPast = max(0, (int) config('siigo.invoice.date_max_days_past', 3));
        $maxFuture = max(0, (int) config('siigo.invoice.date_max_days_future', 0));
        $earliest = $today->copy()->subDays($maxPast);
        $latest = $today->copy()->addDays($maxFuture);

        if ($zohoDate->lt($earliest) || $zohoDate->gt($latest)) {
            return $today->toDateString();
        }

        return $zohoDate->toDateString();
    }

    private function resolveDueDate(array $invoice, string $siigoDate): ?string
    {
        $raw = $invoice['due_date'] ?? null;
        if (! is_string($raw) || trim($raw) === '') {
            return $siigoDate;
        }

        try {
            $due = Carbon::parse($raw)->startOfDay();
            $invoiceDay = Carbon::parse($siigoDate)->startOfDay();
            if ($due->lt($invoiceDay)) {
                return $siigoDate;
            }

            return $due->toDateString();
        } catch (\Throwable) {
            return $siigoDate;
        }
    }

    private function buildObservations(array $invoice, string $siigoDate): string
    {
        $number = $invoice['invoice_number'] ?? $invoice['number'] ?? null;
        $id = $invoice['invoice_id'] ?? null;
        $zohoDate = $invoice['date'] ?? null;
        $parts = ['Sincronizada desde Zoho Books'];

        if ($number !== null) {
            $parts[] = 'Número: '.$number;
        }
        if ($id !== null) {
            $parts[] = 'ID Zoho: '.$id;
        }
        if (is_string($zohoDate) && $zohoDate !== '' && $zohoDate !== $siigoDate) {
            $parts[] = 'Fecha Zoho: '.$zohoDate;
        }

        return implode(' | ', $parts);
    }

    private function extractSiigoLink(array $response): ?string
    {
        $metadata = $response['metadata'] ?? [];

        foreach (['public_url', 'url', 'pdf_url'] as $key) {
            if (isset($metadata[$key]) && is_string($metadata[$key]) && $metadata[$key] !== '') {
                return $metadata[$key];
            }
            if (isset($response[$key]) && is_string($response[$key]) && $response[$key] !== '') {
                return $response[$key];
            }
        }

        $links = $response['_links'] ?? [];
        if (isset($links['self']['href']) && is_string($links['self']['href'])) {
            return $links['self']['href'];
        }

        return null;
    }
}
