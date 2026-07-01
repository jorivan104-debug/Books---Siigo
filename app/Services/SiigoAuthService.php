<?php

namespace App\Services;

use App\Exceptions\ExternalApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class SiigoAuthService
{
    private const ACCESS_TOKEN_CACHE_KEY = 'siigo:access_token';

    public function getToken(): string
    {
        $this->assertPartnerIdIsValid();

        $cached = Cache::get(self::ACCESS_TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->requestNewToken();
    }

    public function partnerId(): string
    {
        $partnerId = trim((string) config('siigo.partner_id', 'integrationHub'));

        return $partnerId !== '' ? $partnerId : 'integrationHub';
    }

    public function clearAccessTokenCache(): void
    {
        Cache::forget(self::ACCESS_TOKEN_CACHE_KEY);
    }

    /**
     * Fuerza una nueva autenticación (útil para diagnóstico).
     */
    public function requestNewToken(): string
    {
        $this->assertPartnerIdIsValid();

        $username = trim((string) config('siigo.username'));
        $accessKey = trim((string) config('siigo.access_key'));

        if ($username === '' || $accessKey === '') {
            throw new ExternalApiException(
                'Credenciales de Siigo no configuradas (SIIGO_USERNAME / SIIGO_ACCESS_KEY).',
                'siigo',
            );
        }

        try {
            // POST /auth NO lleva Authorization Bearer; solo Partner-Id + credenciales.
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Partner-Id' => $this->partnerId(),
            ])
                ->timeout((int) config('siigo.timeout', 30))
                ->post(rtrim((string) config('siigo.api_base_url'), '/').'/auth', [
                    'username' => $username,
                    'access_key' => $accessKey,
                ]);
        } catch (Throwable $e) {
            throw new ExternalApiException(
                'Error de red autenticando en Siigo: '.$e->getMessage(),
                'siigo',
                null,
                [],
                $e,
            );
        }

        if ($response->failed()) {
            throw new ExternalApiException(
                $this->authErrorMessage($response),
                'siigo',
                $response->status(),
                is_array($response->json()) ? $response->json() : ['raw' => $response->body()],
            );
        }

        $body = $response->json() ?? [];
        $token = $body['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new ExternalApiException(
                'Siigo no devolvió access_token.',
                'siigo',
                $response->status(),
                $body,
            );
        }

        $expiresIn = (int) ($body['expires_in'] ?? 3600);
        Cache::put(self::ACCESS_TOKEN_CACHE_KEY, $token, max(60, $expiresIn - 120));

        return $token;
    }

    public function assertPartnerIdIsValid(): void
    {
        $partnerId = $this->partnerId();

        if (preg_match('/^[a-zA-Z0-9]{3,100}$/', $partnerId) !== 1) {
            throw new ExternalApiException(
                "SIIGO_PARTNER_ID inválido: \"{$partnerId}\". Debe ser alfanumérico (3-100 chars), sin guiones ni espacios. Ejemplo: integrationHub. Debe coincidir con el registrado en Siigo Nube → Alianzas → Mi Credencial API.",
                'siigo',
            );
        }
    }

    /**
     * @param  \Illuminate\Http\Client\Response  $response
     */
    private function authErrorMessage($response): string
    {
        $body = $response->json() ?? [];
        $errors = $body['Errors'] ?? [];
        $first = is_array($errors) && isset($errors[0]) ? $errors[0] : [];
        $code = $first['Code'] ?? null;

        return match ($code) {
            'invalid_partner_id' => 'Partner-Id inválido. Usa el valor registrado en Siigo (solo letras/números, sin guiones). Ej: integrationHub',
            'unauthorized' => 'Credenciales Siigo rechazadas. Verifica SIIGO_USERNAME y SIIGO_ACCESS_KEY en Siigo Nube → Alianzas → Mi Credencial API.',
            default => 'No se pudo autenticar en Siigo.',
        };
    }
}
