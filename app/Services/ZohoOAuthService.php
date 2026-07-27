<?php

namespace App\Services;

use App\Exceptions\ExternalApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ZohoOAuthService
{
    private const ACCESS_TOKEN_CACHE_KEY = 'zoho:access_token';

    private const REFRESH_TOKEN_STORAGE = 'zoho_oauth.json';

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
     * Refresca el access_token usando ZOHO_REFRESH_TOKEN (env o archivo de /setup).
     */
    public function refreshAccessToken(): string
    {
        $this->assertClientCredentials();

        $refreshToken = $this->resolveRefreshToken();
        if ($refreshToken === '') {
            throw new ExternalApiException(
                'ZOHO_REFRESH_TOKEN no configurado. En /setup intercambia un Grant Token (Self Client) o define la variable en Coolify.',
                'zoho',
            );
        }

        $response = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);

        return $this->storeAccessTokenFromResponse($response, 'No se pudo refrescar el access_token de Zoho.');
    }

    /**
     * Intercambia un Grant Token (Self Client) por access_token y refresh_token.
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
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code' => $this->sanitizeSecret($grantToken),
        ];

        if ($this->isServerClient()) {
            $redirectUri = trim((string) config('zoho.redirect_uri'));
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
                is_array($body) ? $body : [],
            );
        }

        $this->cacheAccessToken($accessToken, (int) ($body['expires_in'] ?? 3600));

        $refreshToken = isset($body['refresh_token']) ? $this->sanitizeSecret((string) $body['refresh_token']) : null;
        $apiDomain = isset($body['api_domain']) ? (string) $body['api_domain'] : null;

        if ($refreshToken !== null && $refreshToken !== '') {
            $this->persistOAuthSecrets($refreshToken, $apiDomain);
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => (int) ($body['expires_in'] ?? 3600),
            'api_domain' => $apiDomain,
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

    public function resolveRefreshToken(): string
    {
        $fromEnv = $this->sanitizeSecret((string) config('zoho.refresh_token'));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $stored = $this->readStoredOAuth();

        return $this->sanitizeSecret((string) ($stored['refresh_token'] ?? ''));
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function tokenRequest(array $payload): Response
    {
        $accountsCandidates = $this->accountsUrlCandidates();
        $lastResponse = null;
        $errors = [];

        foreach ($accountsCandidates as $accountsUrl) {
            try {
                // Estilo documentado por Zoho: POST con parámetros en query string.
                $response = Http::asForm()
                    ->timeout((int) config('zoho.timeout', 20))
                    ->withOptions(['allow_redirects' => false])
                    ->post($accountsUrl.'/oauth/v2/token?'.http_build_query($payload));

                if ($this->isSuccessfulTokenResponse($response)) {
                    return $response;
                }

                // Fallback: body form-urlencoded.
                $response = Http::asForm()
                    ->timeout((int) config('zoho.timeout', 20))
                    ->withOptions(['allow_redirects' => false])
                    ->post($accountsUrl.'/oauth/v2/token', $payload);

                if ($this->isSuccessfulTokenResponse($response)) {
                    return $response;
                }

                $lastResponse = $response;
                $errors[] = $accountsUrl.' → HTTP '.$response->status();
            } catch (Throwable $e) {
                $errors[] = $accountsUrl.' → '.$this->redactSecrets($e->getMessage());
                Log::warning('Zoho OAuth network error', [
                    'accounts_url' => $accountsUrl,
                    'error' => $this->redactSecrets($e->getMessage()),
                ]);
            }
        }

        if ($lastResponse instanceof Response) {
            throw new ExternalApiException(
                $this->oauthErrorMessage($lastResponse, $errors),
                'zoho',
                $lastResponse->status(),
                ['tried' => $errors, 'raw_preview' => mb_substr((string) $lastResponse->body(), 0, 200)],
            );
        }

        throw new ExternalApiException(
            'Error de red al contactar Zoho OAuth. Intentos: '.implode('; ', $errors),
            'zoho',
        );
    }

    private function isSuccessfulTokenResponse(Response $response): bool
    {
        $body = $response->json();
        $raw = (string) $response->body();
        $looksLikeHtml = str_starts_with(ltrim($raw), '<')
            || str_contains(strtolower((string) $response->header('Content-Type')), 'text/html');

        return ! $response->failed()
            && ! $looksLikeHtml
            && is_array($body)
            && ! isset($body['error'])
            && ! empty($body['access_token']);
    }

    /**
     * @return list<string>
     */
    private function accountsUrlCandidates(): array
    {
        $primary = rtrim((string) config('zoho.accounts_url', 'https://accounts.zoho.com'), '/');
        $candidates = [$primary];

        // Fallback regional habitual en LATAM / multi-DC.
        foreach ([
            'https://accounts.zoho.com',
            'https://accounts.zoho.eu',
        ] as $url) {
            if (! in_array($url, $candidates, true)) {
                $candidates[] = $url;
            }
        }

        return $candidates;
    }

    private function storeAccessTokenFromResponse(Response $response, string $failureMessage): string
    {
        $body = $response->json() ?? [];
        $token = $body['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new ExternalApiException(
                $failureMessage,
                'zoho',
                $response->status(),
                is_array($body) ? $body : [],
            );
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
        if ($this->clientId() === '' || $this->clientSecret() === '') {
            throw new ExternalApiException(
                'Credenciales de Zoho no configuradas (ZOHO_CLIENT_ID / ZOHO_CLIENT_SECRET).',
                'zoho',
            );
        }
    }

    private function clientId(): string
    {
        return $this->sanitizeSecret((string) config('zoho.client_id'));
    }

    private function clientSecret(): string
    {
        return $this->sanitizeSecret((string) config('zoho.client_secret'));
    }

    private function sanitizeSecret(string $value): string
    {
        $value = trim($value);
        $value = trim($value, "\"'");

        // Coolify/usuarios a veces pegan "ZOHO_REFRESH_TOKEN=1000.xxx" completo.
        if (preg_match('/^(?:ZOHO_[A-Z0-9_]+|CLIENT_ID|CLIENT_SECRET|REFRESH_TOKEN)\s*=\s*(.+)$/i', $value, $matches)) {
            $value = trim($matches[1], " \t\"'");
        }

        // Doble prefijo residual.
        $value = preg_replace('/^ZOHO_REFRESH_TOKEN=/i', '', $value) ?? $value;

        return preg_replace('/\s+/', '', $value) ?? $value;
    }

    private function redactSecrets(string $message): string
    {
        $patterns = [
            '/refresh_token=[^&\s]+/i' => 'refresh_token=***',
            '/client_secret=[^&\s]+/i' => 'client_secret=***',
            '/client_id=[^&\s]+/i' => 'client_id=***',
            '/(?<=[&?])code=[^&\s]+/i' => 'code=***',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message) ?? $message;
        }

        return $message;
    }

    private function persistOAuthSecrets(string $refreshToken, ?string $apiDomain): void
    {
        $path = storage_path('app/'.self::REFRESH_TOKEN_STORAGE);
        $payload = [
            'refresh_token' => $this->sanitizeSecret($refreshToken),
            'api_domain' => $apiDomain,
            'updated_at' => now()->toIso8601String(),
        ];

        if (@file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            Log::warning('No se pudo persistir zoho_oauth.json en storage/app.');
        }
    }

    /**
     * @return array{refresh_token?: string, api_domain?: string}
     */
    private function readStoredOAuth(): array
    {
        $path = storage_path('app/'.self::REFRESH_TOKEN_STORAGE);
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  list<string>  $tried
     */
    private function oauthErrorMessage(Response $response, array $tried = []): string
    {
        $body = $response->json();
        $raw = (string) $response->body();
        $error = is_array($body) ? ($body['error'] ?? null) : null;
        $accountsUrl = rtrim((string) config('zoho.accounts_url'), '/');
        $suffix = $tried !== [] ? ' Intentos: '.implode('; ', $tried).'.' : '';

        if (str_starts_with(ltrim($raw), '<') || str_contains(strtolower((string) $response->header('Content-Type')), 'text/html')) {
            return 'Zoho Accounts rechazó el OAuth (HTML/400). Suele ser refresh_token mal pegado (no incluyas ZOHO_REFRESH_TOKEN=) o CLIENT_ID/SECRET incorrectos. URL: '.$accountsUrl.'.'.$suffix;
        }

        return match ($error) {
            'invalid_code' => 'Grant/refresh token inválido o ya usado. Genera un Grant Token nuevo en Self Client → Generate Code.'.$suffix,
            'invalid_client', 'invalid_client_secret' => 'Client ID o Client Secret incorrectos, o datacenter distinto al del Self Client.'.$suffix,
            'invalid_grant' => 'Refresh token inválido o revocado. En Coolify pega SOLO el valor 1000.xxx (sin ZOHO_REFRESH_TOKEN=) o vuelve a intercambiar en /setup.'.$suffix,
            default => 'Error OAuth de Zoho: '.(is_string($error) ? $error : 'HTTP '.$response->status()).'.'.$suffix,
        };
    }
}
