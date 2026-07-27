<?php

/*
|--------------------------------------------------------------------------
| Mapeo de productos Zoho → Siigo
|--------------------------------------------------------------------------
|
| Si SIIGO_DEFAULT_PRODUCT_CODE está definido en .env, ese código se usa
| para TODAS las líneas y este mapa se ignora.
|
| Si no hay default, permite traducir SKU Zoho → código Siigo. Si un SKU
| no aparece aquí, se usa tal cual como `items.code`.
|
| Formato:
|   'sku_zoho' => 'codigo_siigo',
|
*/

return [
    // 'ZOHO-SKU' => 'SIIGO-CODE',
];
