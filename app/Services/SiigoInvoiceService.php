<?php

namespace App\Services;

use App\DTOs\SiigoInvoicePayload;
use App\Exceptions\ExternalApiException;
use InvalidArgumentException;
use Throwable;

class SiigoInvoiceService
{
    public const HUB_BUILD = '20260730-entity-discounts';

    public function __construct(private readonly SiigoHttpClient $http)
    {
    }

    /**
     * Construye el array de items para Siigo a partir de los line_items de Zoho.
     *
     * El rate de Zoho se trata como precio CON IVA incluido:
     *   - taxed_price = rate (Siigo lo usa como total con IVA; reemplaza price)
     *   - price       = rate / 1.19 (base neta, hasta 6 decimales)
     *   - taxes       = IVA 19% (SIIGO_TAX_ID_IVA_19)
     *
     * Ej: 78.000 → total factura 78.000 con desglose IVA (no 92.820).
     *
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array<int, array<string, mixed>>
     */
    public function buildItemsFromZohoInvoice(array $invoice): array
    {
        $lineItems = (array) ($invoice['line_items'] ?? []);
        $entityDiscount = $this->resolveEntityLevelDiscount($invoice, $lineItems);

        return $this->buildItemsFromZohoLineItems($lineItems, $entityDiscount);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array<int, array<string, mixed>>
     */
    public function buildItemsFromZohoLineItems(array $lineItems, float $entityDiscountGross = 0.0): array
    {
        $productMap = (array) config('siigo_product_map', []);
        $taxIdIva19 = trim((string) config('siigo.tax_id_iva_19', ''));
        $defaultCode = trim((string) config('siigo.default_product_code', ''));
        $defaultDescription = trim((string) config('siigo.default_product_description', ''));
        $ivaRate = $this->resolveIvaRate();
        $divisor = 1 + $ivaRate;

        if ($taxIdIva19 === '' || (int) $taxIdIva19 <= 0) {
            throw new InvalidArgumentException(
                'SIIGO_TAX_ID_IVA_19 es obligatorio (ID numérico de IVA 19% en Siigo). Sin eso las facturas salen a 0% IVA. Configúralo en Coolify desde /setup → impuestos. ['.self::HUB_BUILD.']'
            );
        }

        $taxId = (int) $taxIdIva19;
        $items = [];
        $entityDiscounts = $this->allocateEntityDiscount($lineItems, $entityDiscountGross);

        foreach ($lineItems as $index => $line) {
            $sku = (string) ($line['sku'] ?? $line['item_id'] ?? '');
            $code = $defaultCode !== '' ? $defaultCode : ($productMap[$sku] ?? $sku);

            $description = $defaultDescription !== ''
                ? $defaultDescription
                : (string) ($line['name'] ?? $line['description'] ?? $defaultCode);

            $quantity = (float) ($line['quantity'] ?? 0);
            $gross = round((float) ($line['rate'] ?? 0), 2);
            $discountGross = round(
                (float) ($line['discount_amount'] ?? 0) + ($entityDiscounts[$index] ?? 0),
                2
            );

            // Base neta con hasta 6 decimales (límite Siigo) para que base+IVA = gross.
            $net = round($gross / $divisor, 6);
            $discountNet = $discountGross > 0 ? round($discountGross / $divisor, 6) : 0.0;

            $item = [
                'code' => $code,
                'description' => $description !== '' ? $description : $code,
                'quantity' => $quantity,
                'price' => $net,
                'vat_excluded' => false,
                'taxes' => [
                    ['id' => $taxId],
                ],
            ];

            if ($discountNet > 0) {
                $item['discount'] = round($discountNet, 2);
            } else {
                // Siigo: taxed_price reemplaza price y conserva exactamente el total con IVA.
                // No se usa en líneas con descuento para que este reduzca la base gravable.
                $item['taxed_price'] = $gross;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Obtiene el descuento global aplicado al subtotal en Zoho.
     *
     * @param  array<string, mixed>  $invoice
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    private function resolveEntityLevelDiscount(array $invoice, array $lineItems): float
    {
        $type = strtolower(trim((string) ($invoice['discount_type'] ?? '')));
        if ($type !== 'entity_level') {
            return 0.0;
        }

        if (isset($invoice['discount_total']) && is_numeric($invoice['discount_total'])) {
            return max(0.0, round((float) $invoice['discount_total'], 2));
        }

        $raw = trim((string) ($invoice['discount'] ?? '0'));
        if ($raw === '') {
            return 0.0;
        }

        if (str_ends_with($raw, '%')) {
            $percentage = (float) rtrim($raw, "% \t\n\r\0\x0B");
            $base = (float) ($invoice['discount_applied_on_amount'] ?? 0);
            if ($base <= 0) {
                $base = array_reduce(
                    $lineItems,
                    fn (float $sum, array $line): float => $sum
                        + ((float) ($line['rate'] ?? 0) * (float) ($line['quantity'] ?? 0)),
                    0.0
                );
            }

            return max(0.0, round($base * ($percentage / 100), 2));
        }

        return is_numeric($raw) ? max(0.0, round((float) $raw, 2)) : 0.0;
    }

    /**
     * Distribuye el descuento global proporcionalmente entre los productos.
     *
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array<int, float>
     */
    private function allocateEntityDiscount(array $lineItems, float $discount): array
    {
        $discount = max(0.0, round($discount, 2));
        if ($discount <= 0 || $lineItems === []) {
            return [];
        }

        $weights = [];
        foreach ($lineItems as $index => $line) {
            $lineGross = (float) ($line['rate'] ?? 0) * (float) ($line['quantity'] ?? 0);
            $lineDiscount = (float) ($line['discount_amount'] ?? 0);
            $weights[$index] = max(0.0, $lineGross - $lineDiscount);
        }

        $subtotal = array_sum($weights);
        if ($subtotal <= 0) {
            return [];
        }

        $discount = min($discount, round($subtotal, 2));
        $eligible = array_keys(array_filter($weights, fn (float $weight): bool => $weight > 0));
        $last = end($eligible);
        $remaining = $discount;
        $allocated = [];

        foreach ($eligible as $index) {
            $value = $index === $last
                ? $remaining
                : round($discount * ($weights[$index] / $subtotal), 2);
            $allocated[$index] = max(0.0, $value);
            $remaining = round($remaining - $value, 2);
        }

        return $allocated;
    }

    /**
     * Total de factura según fórmula Siigo (o taxed_price si viene).
     *
     * ValorBase = Round(qty * price - discount, 2)
     * IVA       = Round(base * pct / 100, 2)
     * TotalItem = Round(base + IVA, 2)
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function estimateTotalWithTax(array $items): float
    {
        $pct = $this->resolveIvaRate() * 100;
        $total = 0.0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);

            // Si hay taxed_price sin descuento, el total de línea es qty * taxed_price.
            if (
                isset($item['taxed_price'])
                && is_numeric($item['taxed_price'])
                && $discount <= 0
            ) {
                $total += round($qty * (float) $item['taxed_price'], 2);
                continue;
            }

            // Fórmula oficial Siigo sobre price neto.
            $price = (float) ($item['price'] ?? 0);
            $base = round(($qty * $price) - $discount, 2);
            $hasTaxes = ! empty($item['taxes']) && ! ((bool) ($item['vat_excluded'] ?? false));
            if ($hasTaxes && $pct > 0) {
                $iva = round(($base * $pct) / 100, 2);
                $total += round($base + $iva, 2);
            } else {
                $total += $base;
            }
        }

        return round($total, 2);
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
        $body = $this->postInvoice($payload);

        if ($body['ok']) {
            return $body['json'] ?? [];
        }

        // Si el total de pagos no cuadra, reintentar 1 vez con el total que Siigo calculó.
        $calculated = $this->extractCalculatedInvoiceTotal($body['detail'] ?? '');
        if ($calculated !== null && $this->isInvalidTotalPayments($body['detail'] ?? '')) {
            $retryPayload = $this->withPaymentValue($payload, $calculated);
            $retry = $this->postInvoice($retryPayload);
            if ($retry['ok']) {
                return $retry['json'] ?? [];
            }

            throw new ExternalApiException(
                'Siigo rechazó la creación de la factura (reintento payments='.$calculated.').'.($retry['detail'] ?? '').' ['.self::HUB_BUILD.']',
                'siigo',
                $retry['status'] ?? null,
                array_merge(
                    is_array($retry['json'] ?? null) ? $retry['json'] : ['raw' => $retry['raw'] ?? ''],
                    ['_request' => $retryPayload->toArray(), '_hub_build' => self::HUB_BUILD],
                ),
            );
        }

        throw new ExternalApiException(
            'Siigo rechazó la creación de la factura.'.($body['detail'] ?? '').' ['.self::HUB_BUILD.']',
            'siigo',
            $body['status'] ?? null,
            array_merge(
                is_array($body['json'] ?? null) ? $body['json'] : ['raw' => $body['raw'] ?? ''],
                ['_request' => $payload->toArray(), '_hub_build' => self::HUB_BUILD],
            ),
        );
    }

    /**
     * @return array{ok:bool,status?:int,json?:array<string,mixed>|null,raw?:string,detail?:string}
     */
    private function postInvoice(SiigoInvoicePayload $payload): array
    {
        try {
            $response = $this->http->post('v1/invoices', $payload->toArray());
        } catch (Throwable $e) {
            throw new ExternalApiException(
                'Error de red creando la factura en Siigo: '.$e->getMessage().' ['.self::HUB_BUILD.']',
                'siigo',
                null,
                ['payload' => $payload->toArray(), '_hub_build' => self::HUB_BUILD],
                $e,
            );
        }

        $json = $response->json();
        $raw = (string) $response->body();

        if ($response->failed()) {
            return [
                'ok' => false,
                'status' => $response->status(),
                'json' => is_array($json) ? $json : null,
                'raw' => $raw,
                'detail' => $this->formatSiigoErrorDetail(is_array($json) ? $json : [], $raw),
            ];
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'json' => is_array($json) ? $json : [],
            'raw' => $raw,
        ];
    }

    private function withPaymentValue(SiigoInvoicePayload $payload, float $value): SiigoInvoicePayload
    {
        $payments = $payload->payments;
        if ($payments === []) {
            $payments = $this->buildPayment($value);
        } else {
            $first = $payments[0];
            $first['value'] = round($value, 2);
            $payments[0] = $first;
        }

        return new SiigoInvoicePayload(
            documentId: $payload->documentId,
            date: $payload->date,
            customer: $payload->customer,
            seller: $payload->seller,
            items: $payload->items,
            payments: $payments,
            observations: $payload->observations,
            sendToDian: $payload->sendToDian,
            sendMail: $payload->sendMail,
        );
    }

    private function isInvalidTotalPayments(string $detail): bool
    {
        return stripos($detail, 'invalid_total_payments') !== false;
    }

    private function extractCalculatedInvoiceTotal(string $detail): ?float
    {
        if (preg_match('/total invoice calculated is\s*([0-9]+(?:\.[0-9]+)?)/i', $detail, $m) !== 1) {
            return null;
        }

        return (float) $m[1];
    }

    /**
     * Normaliza SIIGO_IVA_RATE: acepta 0.19, 19, "19%" o "0,19". Default 0.19.
     */
    private function resolveIvaRate(): float
    {
        $raw = config('siigo.iva_rate', 0.19);
        if (is_string($raw)) {
            $raw = str_replace(['%', ' '], '', str_replace(',', '.', trim($raw)));
        }
        $rate = is_numeric($raw) ? (float) $raw : 0.19;

        // Si pusieron 19 en vez de 0.19, convertir a fracción.
        if ($rate > 1) {
            $rate = $rate / 100;
        }

        if ($rate <= 0 || $rate >= 1) {
            return 0.19;
        }

        return $rate;
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
