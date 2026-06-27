<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OAuth 2.0 credentials
    |--------------------------------------------------------------------------
    |
    | Credenciales del cliente OAuth registrado en api-console.zoho.com.
    | El refresh_token se genera una sola vez con access_type=offline y se
    | usa para obtener access_tokens nuevos.
    |
    */

    'client_id' => env('ZOHO_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET'),
    'refresh_token' => env('ZOHO_REFRESH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Endpoints
    |--------------------------------------------------------------------------
    |
    | La URL de accounts cambia por región (.com, .eu, .com.au, .com.co, etc.).
    | Para Colombia se suele usar la región global (.com) o .com.co según
    | dónde se haya creado la cuenta.
    |
    */

    'accounts_url' => env('ZOHO_ACCOUNTS_URL', 'https://accounts.zoho.com'),
    'api_base_url' => env('ZOHO_API_BASE_URL', 'https://www.zohoapis.com/books/v3'),

    /*
    |--------------------------------------------------------------------------
    | Custom fields (api_name en Zoho Books)
    |--------------------------------------------------------------------------
    |
    | Los api_name son los identificadores estables que Zoho asigna a cada
    | campo personalizado. Los IDs numéricos (customfield_id) se resuelven
    | en tiempo de ejecución para no acoplarse a una organización concreta.
    |
    */

    'custom_fields' => [
        'invoice' => [
            'link_factura_siigo' => env('ZOHO_CF_LINK_FACTURA_SIIGO', 'cf_linkdefacturasiigo'),
        ],
        'contact' => [
            'tipo_identificacion' => env('ZOHO_CF_TIPO_IDENTIFICACION', 'cf_tipo_de_identificaci_n'),
            'numero_identificacion' => env('ZOHO_CF_NUMERO_IDENTIFICACION', 'cf_n_mero_de_identificaci_n'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP timeouts (segundos)
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('ZOHO_HTTP_TIMEOUT', 20),

];
