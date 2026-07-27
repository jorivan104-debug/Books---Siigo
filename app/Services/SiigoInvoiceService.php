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
     *   sku             → code (override por SIIGO_DEFAULT_PRODUCT_CODE o siigo_product_map)
     *   name            → description (o SIIGO_DEFAULT_PRODUCT_DESCRIPTION)
     *   quantity        → quantity
     *   rate            → price (si Zoho viene sin IVA y SIIGO_FORCE_IVA_ON_UNTAXED,
     *                     el rate se trata como precio con IVA incluido y se divide ÷1.19)
     *   discount_amount → discount (también se descompone si aplica)
     *   tax_percentage > 0 → taxes IVA; si = 0 y force IVA → también se aplica IVA 19%
     */
    public function buildItemsFromZohoLineItems(array $lineItems): array
    {
        $productMap = (array) config('siigo_product_map', []);
        $taxIdIva19 = config('siigo.tax_id_iva_19');
        $defaultCode = trim((string) config('siigo.default_product_code', ''));
        $defaultDescription = trim((string) config('siigo.default_product_description', ''));
        $forceIva = (bool) config('siigo.force_iva_on_untaxed', true);
        $ivaRate = (float) config('siigo.iva_rate', 0.19);
        $decimals = max(0, (int) config('siigo.price_decimals', 2));
        $divisor = 1 + max(0.0, $ivaRate);

        $items = [];
        foreach ($lineItems as $line) {
            $sku = (string) ($line['sku'] ?? $line['item_id'] ?? '');
            if ($defaultCode !== '') {
                $code = $defaultCode;
            } else {
                $code = $productMap[$sku] ?? $sku;
            }

            $description = $defaultDescription !== ''
                ? $defaultDescription
                : (string) ($line['name'] ?? $line['description'] ?? $defaultCode);

            $quantity = (float) ($line['quantity'] ?? 0);
            $rate = (float) ($line['rate'] ?? 0);
            $discount = (float) ($line['discount_amount'] ?? 0);
            $taxPercentage = (float) ($line['tax_percentage'] ?? 0);

            $applyForcedIva = $forceIva
                && $taxPercentage <= 0
                && $taxIdIva19 !== null
                && $taxIdIva19 !== ''
                && $divisor > 1;

            if ($applyForcedIva) {
                // Precio Zoho = total con IVA incluido → base imponible para Siigo.
                $rate = round($rate / $divisor, $decimals);
                if ($discount > 0) {
                    $discount = round($discount / $divisor, $decimals);
                }
            }

            $item = [
                'code' => $code,
                'description' => $description !== '' ? $description : $code,
                'quantity' => $quantity,
                'price' => $rate,
            ];

            if ($discount > 0) {
                $item['discount'] = $discount;
            }

            $shouldAttachIva = ($taxPercentage > 0 || $applyForcedIva)
                && $taxIdIva19 !== null
                && $taxIdIva19 !== '';

            if ($shouldAttachIva) {
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
            $body = $response->json();
            $detail = $this->formatSiigoErrorDetail(is_array($body) ? $body : [], (string) $response->body());

            throw new ExternalApiException(
                'Siigo rechazó la creación de la factura.'.$detail,
                'siigo',
                $response->status(),
                array_merge(
                    is_array($body) ? $body : ['raw' => $response->body()],
                    ['_request' => $payload->toArray()],
                ),
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function formatSiigoErrorDetail(array $body, string $raw): string
    {
        $errors = $body['Errors'] ?? $body['errors'] ?? null;
        if (is_array($errors) && $errors !== []) {
            $parts = [];
            foreach (array_slice($errors, 0, 5) as $error) {
                if (! is_array($error)) {
                    $parts[] = (string) $error;
                    continue;
                }
                $code = $error['Code'] ?? $error['code'] ?? null;
                $message = $error['Message'] ?? $error['message'] ?? $error['Detail'] ?? $error['detail'] ?? null;
                $params = $error['Params'] ?? $error['params'] ?? null;
                $chunk = trim(implode(' — ', array_filter([
                    is_string($code) || is_numeric($code) ? (string) $code : null,
                    is_string($message) ? $message : null,
                    is_array($params) ? json_encode($params, JSON_UNESCAPED_UNICODE) : (is_string($params) ? $params : null),
                ])));
                if ($chunk !== '') {
                    $parts[] = $chunk;
                }
            }
            if ($parts !== []) {
                return ' '.implode(' | ', $parts);
            }
        }

        if (isset($body['Message']) && is_string($body['Message']) && $body['Message'] !== '') {
            return ' '.$body['Message'];
        }

        if (isset($body['message']) && is_string($body['message']) && $body['message'] !== '') {
            return ' '.$body['message'];
        }

        $preview = trim(mb_substr($raw, 0, 280));

        return $preview !== '' ? ' HTTP detail: '.$preview : '';
    }
}
