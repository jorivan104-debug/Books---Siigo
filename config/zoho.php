<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tipo de cliente OAuth
    |--------------------------------------------------------------------------
    |
    | self   → Self Client (recomendado para backend / Coolify / sin redirect URL)
    | server → Server-based Application (requiere ZOHO_REDIRECT_URI)
    |
    */

    'client_type' => env('ZOHO_CLIENT_TYPE', 'self'),

    /*
    |--------------------------------------------------------------------------
    | OAuth 2.0 credentials
    |--------------------------------------------------------------------------
    |
    | Self Client (api-console.zoho.com):
    |   1. Crea un Self Client y copia Client ID + Secret.
    |   2. Generate Code → pega los scopes de `oauth_scopes` abajo.
    |   3. Intercambia el Grant Token:
    |        php artisan zoho:exchange-grant-token {grant_token} --show-env
    |   4. Guarda el refresh_token en ZOHO_REFRESH_TOKEN.
    |
    | En runtime la API solo usa refresh_token → access_token automáticamente.
    |
    */

    'client_id' => env('ZOHO_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET'),
    'refresh_token' => env('ZOHO_REFRESH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Solo para client_type=server (Server-based Application)
    |--------------------------------------------------------------------------
    */

    'redirect_uri' => env('ZOHO_REDIRECT_URI'),

    /*
    |--------------------------------------------------------------------------
    | Scopes recomendados (referencia para Generate Code del Self Client)
    |--------------------------------------------------------------------------
    */

    'oauth_scopes' => env(
        'ZOHO_OAUTH_SCOPES',
        'ZohoBooks.invoices.ALL,ZohoBooks.contacts.READ,ZohoBooks.settings.READ'
    ),

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
