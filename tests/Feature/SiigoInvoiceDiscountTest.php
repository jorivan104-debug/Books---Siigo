<?php

namespace Tests\Feature;

use App\Services\SiigoInvoiceService;
use Tests\TestCase;

class SiigoInvoiceDiscountTest extends TestCase
{
    public function test_distributes_entity_level_discount_into_siigo_items(): void
    {
        config()->set('siigo.tax_id_iva_19', 123);
        config()->set('siigo.default_product_code', '41');
        config()->set('siigo.iva_rate', '0.19');

        $service = app(SiigoInvoiceService::class);
        $items = $service->buildItemsFromZohoInvoice([
            'discount_type' => 'entity_level',
            'discount_total' => 8000,
            'line_items' => [
                [
                    'sku' => 'SKU-1',
                    'name' => 'Producto',
                    'quantity' => 1,
                    'rate' => 78000,
                    'discount_amount' => 0,
                ],
            ],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame(6722.69, $items[0]['discount']);
        $this->assertArrayNotHasKey('taxed_price', $items[0]);
        $this->assertSame(70000.0, $service->estimateTotalWithTax($items));
    }

    public function test_keeps_taxed_price_when_invoice_has_no_discount(): void
    {
        config()->set('siigo.tax_id_iva_19', 123);
        config()->set('siigo.default_product_code', '41');
        config()->set('siigo.iva_rate', '0.19');

        $service = app(SiigoInvoiceService::class);
        $items = $service->buildItemsFromZohoInvoice([
            'discount_type' => 'item_level',
            'line_items' => [
                [
                    'sku' => 'SKU-1',
                    'name' => 'Producto',
                    'quantity' => 1,
                    'rate' => 78000,
                    'discount_amount' => 0,
                ],
            ],
        ]);

        $this->assertSame(78000.0, $items[0]['taxed_price']);
        $this->assertSame(78000.0, $service->estimateTotalWithTax($items));
    }
}
