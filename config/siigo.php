<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credenciales y endpoints
    |--------------------------------------------------------------------------
    */

    'api_base_url' => env('SIIGO_API_BASE_URL', 'https://api.siigo.com'),
    'username' => env('SIIGO_USERNAME'),
    'access_key' => env('SIIGO_ACCESS_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Partner-Id
    |--------------------------------------------------------------------------
    |
    | Siigo exige el header Partner-Id en todas las llamadas para identificar
    | la aplicación que consume la API.
    |
    */

    'partner_id' => env('SIIGO_PARTNER_ID', 'integrationHub'),

    /*
    |--------------------------------------------------------------------------
    | Catálogos referenciados al crear facturas
    |--------------------------------------------------------------------------
    |
    | Se consultan una sola vez en Siigo y se fijan en .env:
    |   - document_id  → /document-types?type=FV
    |   - seller_id    → /v1/users
    |   - payment_id   → /v1/payment-types?document_type=FV
    |   - tax_id_iva_19 → /v1/taxes (impuesto IVA 19%)
    |
    */

    'document_id' => env('SIIGO_DOCUMENT_ID'),
    'seller_id' => env('SIIGO_SELLER_ID'),
    'payment_id' => env('SIIGO_PAYMENT_ID'),
    'tax_id_iva_19' => env('SIIGO_TAX_ID_IVA_19'),

    /*
    |--------------------------------------------------------------------------
    | Producto genérico (todos los ítems de Zoho)
    |--------------------------------------------------------------------------
    |
    | Si está definido, TODAS las líneas usan este código de producto en Siigo
    | (ignora el SKU de Zoho). Útil cuando los SKU de Zoho no existen en Siigo.
    | Ejemplo: código "41" = "Articulo 41 - PANTALON DAMA".
    |
    */

    'default_product_code' => env('SIIGO_DEFAULT_PRODUCT_CODE'),
    'default_product_description' => env('SIIGO_DEFAULT_PRODUCT_DESCRIPTION'),

    /*
    |--------------------------------------------------------------------------
    | IVA forzado para líneas Zoho sin impuesto
    |--------------------------------------------------------------------------
    |
    | Si Zoho trae tax_percentage = 0, el rate se trata como precio CON IVA
    | incluido: se descompone (÷ 1.19) y se envía a Siigo con IVA 19%.
    | Ej: 78.000 → base 65.546,22 + IVA 12.453,78 = 78.000.
    |
    */

    'force_iva_on_untaxed' => filter_var(env('SIIGO_FORCE_IVA_ON_UNTAXED', true), FILTER_VALIDATE_BOOLEAN),
    'iva_rate' => (float) env('SIIGO_IVA_RATE', 0.19),
    'price_decimals' => (int) env('SIIGO_PRICE_DECIMALS', 2),

    /*
    |--------------------------------------------------------------------------
    | Defaults para creación de cliente desde la factura
    |--------------------------------------------------------------------------
    |
    | Cuando el cliente no existe aún en Siigo, se envía el objeto customer
    | completo dentro del POST /v1/invoices. Estos defaults se usan cuando
    | el contacto en Zoho no trae los datos geográficos.
    |
    */

    'customer_defaults' => [
        'country_code' => env('SIIGO_DEFAULT_COUNTRY_CODE', 'CO'),
        'state_code' => env('SIIGO_DEFAULT_STATE_CODE', '11'),
        'city_code' => env('SIIGO_DEFAULT_CITY_CODE', '11001'),
        'address' => env('SIIGO_DEFAULT_ADDRESS', 'N/A'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapeo cf_tipo_de_identificaci_n (Zoho) → id_type (Siigo)
    |--------------------------------------------------------------------------
    |
    | Los valores de la izquierda son los textos posibles del custom field en
    | Zoho. Los de la derecha son los códigos numéricos que Siigo espera.
    | Se compara en minúsculas y sin acentos.
    |
    */

    'id_type_map' => [
        'cedula de ciudadania' => '13',
        'cedula' => '13',
        'cc' => '13',
        'tarjeta de identidad' => '12',
        'ti' => '12',
        'cedula de extranjeria' => '22',
        'ce' => '22',
        'pasaporte' => '41',
        'nit' => '31',
        'rut' => '31',
        'documento de identificacion extranjero' => '21',
        'tipo de documento extranjero' => '21',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default si no se reconoce el tipo de identificación
    |--------------------------------------------------------------------------
    */

    'id_type_default' => env('SIIGO_DEFAULT_ID_TYPE', '13'),

    /*
    |--------------------------------------------------------------------------
    | Comportamiento de la factura electrónica
    |--------------------------------------------------------------------------
    */

    'invoice' => [
        'send_to_dian' => env('SIIGO_INVOICE_SEND_DIAN', false),
        'send_mail' => env('SIIGO_INVOICE_SEND_MAIL', false),
        // Siigo/DIAN: fechas fuera de ventana → invalid_date (típicamente ± pocos días).
        // today = siempre hoy | zoho = fecha Zoho si está en ventana, si no hoy
        'date_mode' => env('SIIGO_INVOICE_DATE_MODE', 'today'),
        'date_max_days_past' => (int) env('SIIGO_INVOICE_DATE_MAX_DAYS_PAST', 3),
        'date_max_days_future' => (int) env('SIIGO_INVOICE_DATE_MAX_DAYS_FUTURE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP timeouts (segundos)
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('SIIGO_HTTP_TIMEOUT', 30),

];
