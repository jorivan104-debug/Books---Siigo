<?php

namespace App\Services;

use App\Exceptions\ExternalApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ZohoOAuthService
{
    private const ACCESS_TOKEN_CACHE_KEY = 'zoho:access_token';

    /**
     * Obtiene un access_token válido (desde cache o refrescando el refresh_token).
     */
    public function getAccessToken(): string
    {
        $cached = Cache::get(self::ACCESS_TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->refreshAccessToken();
    }

    /**
     * Refresca el access_token usando ZOHO_REFRESH_TOKEN.
     * Funciona tanto para Self Client como para Server-based Application.
     */
    public function refreshAccessToken(): string
    {
        $this->assertClientCredentials();

        $refreshToken = (string) config('zoho.refresh_token');
        if ($refreshToken === '') {
            throw new ExternalApiException(
                'ZOHO_REFRESH_TOKEN no configurado. Para Self Client, genera un Grant Token en api-console.zoho.com y ejecuta: php artisan zoho:exchange-grant-token {code}',
                'zoho',
            );
        }

        $response = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => (string) config('zoho.client_id'),
            'client_secret' => (string) config('zoho.client_secret'),
        ]);

        return $this->storeAccessTokenFromResponse($response, 'No se pudo refrescar el access_token de Zoho.');
    }

    /**
     * Intercambia un Grant Token (Self Client) por access_token y refresh_token.
     *
     * El Grant Token se genera en api-console.zoho.com → Self Client → Generate Code.
     * Es de un solo uso y caduca en minutos.
     *
     * @return array{access_token: string, refresh_token: string|null, expires_in: int, api_domain: string|null}
     */
    public function exchangeGrantToken(string $grantToken): array
    {
        $this->assertClientCredentials();

        if (trim($grantToken) === '') {
            throw new ExternalApiException('El Grant Token no puede estar vacío.', 'zoho');
        }

        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => (string) config('zoho.client_id'),
            'client_secret' => (string) config('zoho.client_secret'),
            'code' => trim($grantToken),
        ];

        // Server-based Application requiere redirect_uri; Self Client no.
        if ($this->isServerClient()) {
            $redirectUri = (string) config('zoho.redirect_uri');
            if ($redirectUri === '') {
                throw new ExternalApiException(
                    'ZOHO_REDIRECT_URI es obligatorio cuando ZOHO_CLIENT_TYPE=server.',
                    'zoho',
                );
            }
            $payload['redirect_uri'] = $redirectUri;
        }

        $response = $this->tokenRequest($payload);

        $body = $response->json() ?? [];
        $accessToken = $body['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new ExternalApiException(
                'Zoho no devolvió access_token al intercambiar el Grant Token.',
                'zoho',
                $response->status(),
                $body,
            );
        }

        $this->cacheAccessToken($accessToken, (int) ($body['expires_in'] ?? 3600));

        return [
            'access_token' => $accessToken,
            'refresh_token' => isset($body['refresh_token']) ? (string) $body['refresh_token'] : null,
            'expires_in' => (int) ($body['expires_in'] ?? 3600),
            'api_domain' => isset($body['api_domain']) ? (string) $body['api_domain'] : null,
        ];
    }

    public function isSelfClient(): bool
    {
        return config('zoho.client_type', 'self') === 'self';
    }

    public function isServerClient(): bool
    {
        return ! $this->isSelfClient();
    }

    public function clearAccessTokenCache(): void
    {
        Cache::forget(self::ACCESS_TOKEN_CACHE_KEY);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function tokenRequest(array $payload): Response
    {
        $accountsUrl = rtrim((string) config('zoho.accounts_url'), '/');

        try {
            $response = Http::asForm()
                ->timeout((int) config('zoho.timeout', 20))
                ->post("{$accountsUrl}/oauth/v2/token", $payload);
        } catch (Throwable $e) {
            throw new ExternalApiException(
                'Error de red al contactar Zoho OAuth: '.$e->getMessage(),
                'zoho',
                null,
                [],
                $e,
            );
        }

        if ($response->failed()) {
            throw new ExternalApiException(
                $this->oauthErrorMessage($response),
                'zoho',
                $response->status(),
                is_array($response->json()) ? $response->json() : ['raw' => $response->body()],
            );
        }

        return $response;
    }

    private function storeAccessTokenFromResponse(Response $response, string $failureMessage): string
    {
        $body = $response->json() ?? [];
        $token = $body['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new ExternalApiException($failureMessage, 'zoho', $response->status(), $body);
        }

        $this->cacheAccessToken($token, (int) ($body['expires_in'] ?? 3600));

        return $token;
    }

    private function cacheAccessToken(string $token, int $expiresIn): void
    {
        Cache::put(self::ACCESS_TOKEN_CACHE_KEY, $token, max(60, $expiresIn - 120));
    }

    private function assertClientCredentials(): void
    {
        $clientId = (string) config('zoho.client_id');
        $clientSecret = (string) config('zoho.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new ExternalApiException(
                'Credenciales de Zoho no configuradas (ZOHO_CLIENT_ID / ZOHO_CLIENT_SECRET).',
                'zoho',
            );
        }
    }

    private function oauthErrorMessage(Response $response): string
    {
        $body = $response->json() ?? [];
        $error = $body['error'] ?? null;

        return match ($error) {
            'invalid_code' => 'Grant Token inválido o expirado. Genera uno nuevo en Zoho API Console → Self Client → Generate Code.',
            'invalid_client' => 'Client ID o Client Secret incorrectos. Revisa ZOHO_CLIENT_ID y ZOHO_CLIENT_SECRET.',
            'invalid_grant' => 'Refresh token inválido o revocado. Genera un nuevo Grant Token con php artisan zoho:exchange-grant-token.',
            default => 'Error OAuth de Zoho: '.($body['error'] ?? $response->body()),
        };
    }
}
