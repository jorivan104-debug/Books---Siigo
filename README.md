# integration-hub

API Laravel 12 que actúa como puente entre **Zoho Books** y **Siigo Nube** (y futuras integraciones: Shopify, Envia.com, Kommo, Chatwoot). Expone un endpoint protegido que sincroniza una factura de Zoho hacia Siigo en una sola llamada, mantiene logs en base de datos y registra el link de la factura creada en un campo personalizado de Zoho para evitar duplicados.

## Stack

- PHP 8.3
- Laravel 12
- MySQL u PostgreSQL para logs (SQLite para desarrollo local)
- Docker + nginx + php-fpm + supervisord (listo para Coolify)

## Endpoint

```
POST /api/zoho/invoice/sync
Content-Type: application/json
X-INTEGRATION-KEY: <INTEGRATION_API_KEY>

{
  "organization_id": "ZOHO_ORG_ID",
  "invoice_id": "ZOHO_INVOICE_ID"
}
```

Respuestas:

```json
// Éxito
{
  "success": true,
  "message": "Factura sincronizada correctamente.",
  "siigo_invoice_id": "...",
  "siigo_invoice_link": "..."
}

// Ya sincronizada
{
  "success": false,
  "message": "La factura ya fue sincronizada previamente.",
  "siigo_invoice_link": "...",
  "details": { "reason": "already_synced" }
}

// Error
{
  "success": false,
  "message": "Descripción clara del error",
  "details": { ... }
}
```

Si el header `X-INTEGRATION-KEY` falta o no coincide, la API responde `401`.

## Configuración

1. Copia `.env.example` a `.env` y completa las credenciales de Zoho, Siigo y la base de datos.
2. **Panel web de autenticación:** abre `https://tu-dominio/setup` e inicia sesión con `INTEGRATION_API_KEY`. Desde ahí puedes:
   - Intercambiar Grant Token de Zoho Self Client → `ZOHO_REFRESH_TOKEN`
   - Probar conexión Zoho Books
   - Autenticar Siigo y listar IDs de catálogos (`DOCUMENT_ID`, `SELLER_ID`, etc.)
3. Variables clave:

   - `INTEGRATION_API_KEY`: clave que Zoho debe enviar en el header.
   - `ZOHO_CLIENT_ID`, `ZOHO_CLIENT_SECRET`, `ZOHO_REFRESH_TOKEN`: OAuth **Self Client** de Zoho (ver [`docs/zoho-self-client.md`](docs/zoho-self-client.md))
   - `SIIGO_USERNAME`, `SIIGO_ACCESS_KEY`, `SIIGO_PARTNER_ID`: credenciales API de Siigo (Alianzas > Mi Credencial API).
   - `SIIGO_DOCUMENT_ID`, `SIIGO_SELLER_ID`, `SIIGO_PAYMENT_ID`, `SIIGO_TAX_ID_IVA_19`: IDs numéricos de catálogos Siigo.
   - `SIIGO_DEFAULT_COUNTRY_CODE` / `STATE_CODE` / `CITY_CODE`: defaults geográficos para crear clientes inline (Bogotá por defecto).
3. Genera la `APP_KEY` y ejecuta migraciones:

   ```bash
   php artisan key:generate
   php artisan migrate
   ```

### Credenciales Zoho (Self Client)

Por defecto el proyecto usa `ZOHO_CLIENT_TYPE=self`. Para obtener el `refresh_token`:

```bash
# 1. Genera un Grant Token en api-console.zoho.com → Self Client → Generate Code
# 2. Configura ZOHO_CLIENT_ID y ZOHO_CLIENT_SECRET en .env
php artisan zoho:exchange-grant-token TU_GRANT_TOKEN --show-env
php artisan zoho:test-connection
```

Guía completa: [`docs/zoho-self-client.md`](docs/zoho-self-client.md)

## Desarrollo local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --port=8000
```

Prueba manual:

```bash
curl -X POST http://localhost:8000/api/zoho/invoice/sync \
  -H "Content-Type: application/json" \
  -H "X-INTEGRATION-KEY: local-dev-key" \
  -d '{"organization_id":"123","invoice_id":"456"}'
```

## Despliegue con Docker / Coolify

```bash
docker compose up -d --build
```

La imagen expone el puerto `8080` (nginx) y se publica en `8000` del host. Para Coolify:

1. Apunta el repositorio y elige `Dockerfile` como build type.
2. Define el puerto público `8080`.
3. Configura todas las variables de entorno listadas en `.env.example`.
4. Tras desplegar, Coolify ejecutará automáticamente `php artisan migrate --force` vía el entrypoint.

## Estructura

```
app/
  Http/
    Controllers/ZohoInvoiceSyncController.php
    Middleware/ValidateIntegrationKey.php
    Requests/SyncZohoInvoiceRequest.php
  Services/
    ZohoBooksService.php
    ZohoOAuthService.php
    SiigoAuthService.php
    SiigoCustomerService.php
    SiigoInvoiceService.php
    ZohoToSiigoInvoiceSyncService.php
    IntegrationLogService.php
  Support/
    ZohoCustomFieldHelper.php
    SiigoIdTypeMapper.php
  DTOs/SiigoInvoicePayload.php
  Enums/{IntegrationType,SyncStatus}.php
  Exceptions/{ExternalApiException,InvoiceAlreadySyncedException}.php
  Models/IntegrationLog.php
config/
  integration.php
  zoho.php
  siigo.php
  siigo_product_map.php
database/migrations/2026_06_26_120000_create_integration_logs_table.php
docs/deluge/zoho-invoice-sync.deluge
docker/{nginx,supervisor,entrypoint.sh}
Dockerfile
docker-compose.yml
```

## Flujo de sincronización

1. Zoho llama al endpoint con `organization_id` + `invoice_id`.
2. La API valida el header `X-INTEGRATION-KEY`.
3. Se crea un log `pending` en `integration_logs`.
4. Se refresca el `access_token` de Zoho y se consultan la factura y su contacto.
5. Se leen los custom fields del contacto: `cf_tipo_de_identificaci_n` y `cf_n_mero_de_identificaci_n`.
6. Si la factura ya tiene `cf_linkdefacturasiigo`, se marca como `skipped` y se devuelve el link existente.
7. Se autentica en Siigo. Si el cliente no existe por número de identificación, se incluye el objeto `customer` completo en el POST de la factura para crearlo inline.
8. Se mapean los `line_items` a `items` de Siigo aplicando `siigo_product_map` y el IVA 19% cuando `tax_percentage > 0`.
9. Se crea la factura en Siigo (`POST /v1/invoices`).
10. Se actualiza Zoho con el link de la factura Siigo en el custom field `cf_linkdefacturasiigo`.
11. Se devuelve el JSON estándar a Zoho y se persiste el log `success`.

## Logs

Tabla `integration_logs`:

| Columna | Uso |
|---|---|
| integration | `zoho_siigo`, `shopify`, etc. |
| operation | `invoice_sync` |
| status | `pending` / `success` / `failed` / `skipped` |
| organization_id | ID de la organización Zoho |
| external_id | `invoice_id` de Zoho |
| siigo_invoice_id | ID devuelto por Siigo |
| request_payload | JSON recibido |
| response_payload | JSON devuelto |
| message | Mensaje humano |
| error_details | JSON con contexto del error |

## Llamar desde Zoho Books

Ejemplo Deluge listo para asignar a un botón personalizado: [`docs/deluge/zoho-invoice-sync.deluge`](docs/deluge/zoho-invoice-sync.deluge).

## Extender a otras integraciones

- Agrega rutas en `routes/api.php` bajo prefijos por proveedor: `/api/shopify/*`, `/api/kommo/*`, etc.
- Agrega valores al enum `App\Enums\IntegrationType`.
- Crea un nuevo servicio orquestador siguiendo el patrón de `ZohoToSiigoInvoiceSyncService` y reutiliza `IntegrationLogService`.
