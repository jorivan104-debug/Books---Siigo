<?php

namespace App\Enums;

enum IntegrationType: string
{
    case ZohoSiigo = 'zoho_siigo';
    case Shopify = 'shopify';
    case Envia = 'envia';
    case Kommo = 'kommo';
    case Chatwoot = 'chatwoot';
}
