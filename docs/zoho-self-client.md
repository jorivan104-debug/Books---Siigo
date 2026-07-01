# Zoho Self Client — Guía de credenciales

Este proyecto usa **Zoho Self Client** por defecto (`ZOHO_CLIENT_TYPE=self`), ideal para despliegues backend (Coolify) sin redirect URL.

## 1. Crear Self Client

1. Entra a [https://api-console.zoho.com](https://api-console.zoho.com)
2. **Add Client** → **Self Client**
3. Copia **Client ID** y **Client Secret**

## 2. Generar Grant Token

En el Self Client → **Generate Code**:

**Scopes** (copia exactamente):

```
ZohoBooks.invoices.ALL,ZohoBooks.contacts.READ,ZohoBooks.settings.READ
```

Elige la duración máxima y pulsa **Create**. Copia el Grant Token (caduca en minutos, un solo uso).

## 3. Intercambiar por refresh_token

En tu máquina local, con `.env` configurado con `ZOHO_CLIENT_ID` y `ZOHO_CLIENT_SECRET`:

```bash
php artisan zoho:exchange-grant-token TU_GRANT_TOKEN --show-env
```

Copia la línea `ZOHO_REFRESH_TOKEN=...` al `.env` local o a las variables de entorno de Coolify.

## 4. Verificar conexión

```bash
php artisan zoho:test-connection
```

Debería listar las organizaciones de Zoho Books accesibles.

## Variables en Coolify / .env

```env
ZOHO_CLIENT_TYPE=self
ZOHO_CLIENT_ID=1000.xxxx
ZOHO_CLIENT_SECRET=xxxx
ZOHO_REFRESH_TOKEN=1000.xxxx
ZOHO_ACCOUNTS_URL=https://accounts.zoho.com
ZOHO_API_BASE_URL=https://www.zohoapis.com/books/v3
```

## Errores frecuentes

| Error | Solución |
|---|---|
| `invalid_code` | Grant Token expiró o ya se usó → genera uno nuevo |
| `invalid_client` | Revisa Client ID y Secret |
| `invalid_grant` | Refresh token revocado → repite pasos 2 y 3 |
| Organizaciones vacías | Scopes insuficientes → incluye los 3 scopes de arriba |

## Server-based Application (opcional)

Si prefieres OAuth con redirect URL:

```env
ZOHO_CLIENT_TYPE=server
ZOHO_REDIRECT_URI=https://tu-dominio/callback
```

El flujo de autorización en navegador es distinto; en runtime se sigue usando `ZOHO_REFRESH_TOKEN`.
