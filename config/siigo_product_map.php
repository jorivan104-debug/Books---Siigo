<?php

/*
|--------------------------------------------------------------------------
| Mapeo de productos Zoho → Siigo
|--------------------------------------------------------------------------
|
| Permite traducir el SKU/Item de Zoho Books al código de producto en Siigo
| Nube cuando no son iguales. Si un SKU no aparece aquí, se usa tal cual
| como `items.code` en el POST /v1/invoices.
|
| Formato:
|   'sku_zoho' => 'codigo_siigo',
|
| Ejemplo:
|   'ZOHO-001' => 'SIIGO-PROD-1',
|
*/

return [
    // 'ZOHO-SKU' => 'SIIGO-CODE',
];
