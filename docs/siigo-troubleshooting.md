# Siigo API — Solución de errores

## Error `invalid_partner_id`

**Causa:** `SIIGO_PARTNER_ID` con formato inválido (guión, espacio, caracteres especiales).

**Solución:**
- Usa solo letras y números, 3–100 caracteres: `integrationHub`, `zohoBooksSiigo`
- Debe ser **exactamente** el Partner-Id registrado en Siigo Nube → **Alianzas → Mi Credencial API**
- **No uses:** `integration-hub`, `prueba`, `test`, `sandbox`

```env
SIIGO_PARTNER_ID=integrationHub
```

---

## Error `unauthorized` (401)

Este error aparece en **dos momentos distintos**:

### A) Al hacer `POST /auth` (obtener token)

**Causas:**
- `SIIGO_USERNAME` incorrecto (debe ser el usuario/email de Mi Credencial API)
- `SIIGO_ACCESS_KEY` incorrecto o copiado con espacios
- Usuario API bloqueado en Siigo
- `Partner-Id` no coincide con el registrado al crear la credencial

**Importante:** En `POST /auth` **NO** envíes `Authorization: Bearer ...`. Solo:

```bash
curl -X POST "https://api.siigo.com/auth" \
  -H "Content-Type: application/json" \
  -H "Partner-Id: integrationHub" \
  -d '{"username":"TU_USERNAME","access_key":"TU_ACCESS_KEY"}'
```

### B) Al llamar `/v1/users`, `/v1/taxes`, etc.

**Causas más comunes:**
1. Usaste el **access_key** en `Authorization` en lugar del **access_token** devuelto por `/auth`
2. Token expirado (dura ~24h)
3. `Partner-Id` distinto entre `/auth` y la llamada `/v1/*`

**Formato correcto:**

```bash
# Paso 1: obtener token
TOKEN=$(curl -s -X POST "https://api.siigo.com/auth" \
  -H "Content-Type: application/json" \
  -H "Partner-Id: integrationHub" \
  -d '{"username":"...","access_key":"..."}' | jq -r .access_token)

# Paso 2: usar el access_token (NO el access_key)
curl -X GET "https://api.siigo.com/v1/users?page=1&page_size=25" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Partner-Id: integrationHub"
```

---

## Verificar desde el proyecto

Con `.env` configurado:

```bash
php artisan siigo:test-connection
php artisan siigo:test-connection --catalog
```

El flag `--catalog` lista los IDs para:
- `SIIGO_DOCUMENT_ID`
- `SIIGO_SELLER_ID`
- `SIIGO_PAYMENT_ID`
- `SIIGO_TAX_ID_IVA_19`

---

## Variables en Coolify

```env
SIIGO_USERNAME=email@empresa.com
SIIGO_ACCESS_KEY=xxxxxxxxxxxxxxxx
SIIGO_PARTNER_ID=integrationHub
```

Tras cambiar variables en Coolify, **redeploy** la aplicación.
