<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Clave compartida que las integraciones externas (Zoho Books, Shopify,
    | Kommo, Chatwoot, etc.) deben enviar en el header X-INTEGRATION-KEY
    | para autenticar las llamadas al integration-hub.
    |
    */

    'api_key' => env('INTEGRATION_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Header name
    |--------------------------------------------------------------------------
    */

    'header' => 'X-INTEGRATION-KEY',

    /*
    |--------------------------------------------------------------------------
    | Integraciones disponibles
    |--------------------------------------------------------------------------
    |
    | Identificadores usados en la columna `integration` de integration_logs.
    | Aquí se irán agregando los nuevos proveedores conforme se conecten.
    |
    */

    'providers' => [
        'zoho_siigo' => 'Zoho Books → Siigo Nube',
    ],

];
