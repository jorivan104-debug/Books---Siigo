<?php

namespace App\Services;

use App\Exceptions\ExternalApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class SiigoHttpClient
{
    public function __construct(private readonly SiigoAuthService $auth)
    {
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->send('get', $path, ['query' => $query]);
    }

    public function post(string $path, array $body = []): Response
    {
        return $this->send('post', $path, ['json' => $body]);
    }

    public function put(string $path, array $body = []): Response
    {
        return $this->send('put', $path, ['json' => $body]);
    }

    /**
     * @param  array{query?: array<string, mixed>, json?: array<string, mixed>}  $options
     */
    private function send(string $method, string $path, array $options = [], bool $allowRetry = true): Response
    {
        $url = $this->url($path);

        try {
            $client = $this->authenticatedClient();
            $response = match ($method) {
                'get' => $client->get($url, $options['query'] ?? []),
                'post' => $client->post($url, $options['json'] ?? []),
                'put' => $client->put($url, $options['json'] ?? []),
                default => throw new ExternalApiException("Método HTTP no soportado: {$method}", 'siigo'),
            };
        } catch (Throwable $e) {
            throw new ExternalApiException(
                'Error de red consultando Siigo: '.$e->getMessage(),
                'siigo',
                null,
                ['path' => $path],
                $e,
            );
        }

        if ($response->status() === 401 && $allowRetry) {
            $this->auth->clearAccessTokenCache();
            return $this->send($method, $path, $options, false);
        }

        return $response;
    }

    public function authenticatedClient(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->auth->getToken(),
            'Partner-Id' => $this->auth->partnerId(),
            'Content-Type' => 'application/json',
        ])->timeout((int) config('siigo.timeout', 30));
    }

    public function url(string $path): string
    {
        return rtrim((string) config('siigo.api_base_url'), '/').'/'.ltrim($path, '/');
    }
}
