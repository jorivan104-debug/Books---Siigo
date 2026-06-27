<?php

namespace App\Services;

use App\Exceptions\ExternalApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ZohoBooksService
{
    private const ACCESS_TOKEN_CACHE_KEY = 'zoho:access_token';

    public function getAccessToken(): string
    {
        $cached = Cache::get(self::ACCESS_TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $accountsUrl = (string) config('zoho.accounts_url');
        $clientId = (string) config('zoho.client_id');
        $clientSecret = (string) config('zoho.client_secret');
        $refreshToken = (string) config('zoho.refresh_token');

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new ExternalApiException(
                'Credenciales de Zoho no configuradas (ZOHO_CLIENT_ID / SECRET / REFRESH_TOKEN).',
                'zoho',
            );
        }

        $response = $this->safeRequest(
            fn () => Http::asForm()
                ->timeout((int) config('zoho.timeout', 20))
                ->post($accountsUrl.'/oauth/v2/token', [
                    'refresh_token' => $refreshToken,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'refresh_token',
                ]),
            'No se pudo refrescar el access_token de Zoho.',
        );

        $body = $response->json() ?? [];
        $token = $body['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new ExternalApiException(
                'Zoho no devolvió access_token al refrescar.',
                'zoho',
                $response->status(),
                $body,
            );
        }

        $expiresIn = (int) ($body['expires_in'] ?? 3600);
        Cache::put(self::ACCESS_TOKEN_CACHE_KEY, $token, max(60, $expiresIn - 120));

        return $token;
    }

    public function getInvoice(string $organizationId, string $invoiceId): array
    {
        $response = $this->safeRequest(
            fn () => $this->client()->get($this->endpoint("/invoices/{$invoiceId}"), [
                'organization_id' => $organizationId,
            ]),
            "No se pudo obtener la factura {$invoiceId} de Zoho.",
        );

        $invoice = $response->json('invoice');
        if (! is_array($invoice)) {
            throw new ExternalApiException(
                'Respuesta inválida de Zoho al obtener la factura.',
                'zoho',
                $response->status(),
                $response->json() ?? [],
            );
        }

        return $invoice;
    }

    public function getContact(string $organizationId, string $contactId): array
    {
        $response = $this->safeRequest(
            fn () => $this->client()->get($this->endpoint("/contacts/{$contactId}"), [
                'organization_id' => $organizationId,
            ]),
            "No se pudo obtener el contacto {$contactId} de Zoho.",
        );

        $contact = $response->json('contact');
        if (! is_array($contact)) {
            throw new ExternalApiException(
                'Respuesta inválida de Zoho al obtener el contacto.',
                'zoho',
                $response->status(),
                $response->json() ?? [],
            );
        }

        return $contact;
    }

    /**
     * Devuelve la definición de un custom field por su api_name.
     * Cachea por organización durante 24h.
     */
    public function findCustomFieldId(string $organizationId, string $module, string $apiName): ?string
    {
        $cacheKey = "zoho:cf:{$organizationId}:{$module}";

        $definitions = Cache::remember($cacheKey, now()->addHours(24), function () use ($organizationId, $module) {
            $response = $this->safeRequest(
                fn () => $this->client()->get($this->endpoint('/settings/customfields'), [
                    'organization_id' => $organizationId,
                    'entity' => $module,
                ]),
                'No se pudo obtener la definición de custom fields de Zoho.',
            );

            return $response->json('customfields') ?? [];
        });

        foreach ($definitions as $definition) {
            if (($definition['api_name'] ?? null) === $apiName
                || ($definition['placeholder'] ?? null) === $apiName) {
                return isset($definition['customfield_id'])
                    ? (string) $definition['customfield_id']
                    : null;
            }
        }

        return null;
    }

    public function updateInvoiceCustomField(
        string $organizationId,
        string $invoiceId,
        string $customFieldId,
        string $value
    ): array {
        $response = $this->safeRequest(
            fn () => $this->client()->put(
                $this->endpoint("/invoice/{$invoiceId}/customfields").'?organization_id='.urlencode($organizationId),
                [
                    [
                        'customfield_id' => $customFieldId,
                        'value' => $value,
                    ],
                ],
            ),
            "No se pudo actualizar el custom field de la factura {$invoiceId} en Zoho.",
        );

        return $response->json() ?? [];
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $token = $this->getAccessToken();

        return Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken '.$token,
            'Content-Type' => 'application/json',
        ])->timeout((int) config('zoho.timeout', 20));
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('zoho.api_base_url'), '/').$path;
    }

    /**
     * Ejecuta el callable y lanza ExternalApiException si la respuesta no es exitosa.
     *
     * @param  callable():Response  $callable
     */
    private function safeRequest(callable $callable, string $message): Response
    {
        try {
            $response = $callable();
        } catch (Throwable $e) {
            throw new ExternalApiException($message.' '.$e->getMessage(), 'zoho', null, [], $e);
        }

        if ($response->failed()) {
            throw new ExternalApiException(
                $message,
                'zoho',
                $response->status(),
                is_array($response->json()) ? $response->json() : ['raw' => $response->body()],
            );
        }

        return $response;
    }
}
