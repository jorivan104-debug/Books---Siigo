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
        $cached = Cache::get(self::ACCESS_TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $username = (string) config('siigo.username');
        $accessKey = (string) config('siigo.access_key');

        if ($username === '' || $accessKey === '') {
            throw new ExternalApiException(
                'Credenciales de Siigo no configuradas (SIIGO_USERNAME / SIIGO_ACCESS_KEY).',
                'siigo',
            );
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Partner-Id' => (string) config('siigo.partner_id', 'integration-hub'),
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
                'No se pudo autenticar en Siigo.',
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
}
