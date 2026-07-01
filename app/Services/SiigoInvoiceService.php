<?php

namespace App\Services;

use App\DTOs\SiigoInvoicePayload;
use App\Exceptions\ExternalApiException;
use Throwable;

class SiigoInvoiceService
{
    public function __construct(private readonly SiigoHttpClient $http)
    {
    }

    /**
     * Construye el array de items para Siigo a partir de los line_items de Zoho.
     *
     * Mapeo:
     *   sku             → code (con override opcional desde config/siigo_product_map.php)
     *   name            → description
     *   quantity        → quantity
     *   rate            → price
     *   discount_amount → discount
     *   tax_percentage > 0 → taxes: [{id: SIIGO_TAX_ID_IVA_19}]
     */
    public function buildItemsFromZohoLineItems(array $lineItems): array
    {
        $productMap = (array) config('siigo_product_map', []);
        $taxIdIva19 = config('siigo.tax_id_iva_19');

        $items = [];
        foreach ($lineItems as $line) {
            $sku = (string) ($line['sku'] ?? $line['item_id'] ?? '');
            $code = $productMap[$sku] ?? $sku;

            $item = [
                'code' => $code,
                'description' => (string) ($line['name'] ?? $line['description'] ?? ''),
                'quantity' => (float) ($line['quantity'] ?? 0),
                'price' => (float) ($line['rate'] ?? 0),
            ];

            $discount = (float) ($line['discount_amount'] ?? 0);
            if ($discount > 0) {
                $item['discount'] = $discount;
            }

            $taxPercentage = (float) ($line['tax_percentage'] ?? 0);
            if ($taxPercentage > 0 && $taxIdIva19 !== null && $taxIdIva19 !== '') {
                $item['taxes'] = [
                    ['id' => (int) $taxIdIva19],
                ];
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Construye el bloque payments con el medio de pago configurado en .env.
     */
    public function buildPayment(float $total, ?string $dueDate = null): array
    {
        $payment = [
            'id' => (int) config('siigo.payment_id'),
            'value' => round($total, 2),
        ];

        if ($dueDate !== null && $dueDate !== '') {
            $payment['due_date'] = $dueDate;
        }

        return [$payment];
    }

    public function create(SiigoInvoicePayload $payload): array
    {
        try {
            $response = $this->http->post('v1/invoices', $payload->toArray());
        } catch (Throwable $e) {
            throw new ExternalApiException(
                'Error de red creando la factura en Siigo: '.$e->getMessage(),
                'siigo',
                null,
                ['payload' => $payload->toArray()],
                $e,
            );
        }

        if ($response->failed()) {
            throw new ExternalApiException(
                'Siigo rechazó la creación de la factura.',
                'siigo',
                $response->status(),
                array_merge(
                    is_array($response->json()) ? $response->json() : ['raw' => $response->body()],
                    ['_request' => $payload->toArray()],
                ),
            );
        }

        return $response->json() ?? [];
    }
}
