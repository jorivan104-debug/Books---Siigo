<?php

namespace App\Console\Commands;

use App\Exceptions\ExternalApiException;
use App\Services\SiigoAuthService;
use App\Services\SiigoHttpClient;
use Illuminate\Console\Command;

class SiigoTestConnectionCommand extends Command
{
    protected $signature = 'siigo:test-connection
                            {--catalog : Muestra IDs sugeridos para SIIGO_DOCUMENT_ID, SELLER_ID, PAYMENT_ID y TAX_ID_IVA_19}';

    protected $description = 'Verifica autenticación Siigo y opcionalmente lista catálogos para .env';

    public function handle(SiigoAuthService $auth, SiigoHttpClient $http): int
    {
        $this->info('Partner-Id: '.$auth->partnerId());
        $this->line('API: '.config('siigo.api_base_url'));
        $this->line('Username: '.config('siigo.username'));
        $this->newLine();

        try {
            $auth->assertPartnerIdIsValid();
        } catch (ExternalApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            $auth->clearAccessTokenCache();
            $token = $auth->requestNewToken();
        } catch (ExternalApiException $e) {
            $this->error('Autenticación fallida: '.$e->getMessage());
            if ($e->responseBody !== []) {
                $this->line(json_encode($e->responseBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            $this->newLine();
            $this->comment('Checklist 401/unauthorized:');
            $this->line('  1. SIIGO_USERNAME = email/usuario de Mi Credencial API (no el access_key)');
            $this->line('  2. SIIGO_ACCESS_KEY = clave de Mi Credencial API (no va en Authorization Bearer)');
            $this->line('  3. SIIGO_PARTNER_ID = exactamente el registrado en Siigo (sin guiones)');
            $this->line('  4. En GET /v1/* usa: Authorization: Bearer {access_token del POST /auth}');

            return self::FAILURE;
        }

        $this->info('Autenticación OK. Token obtenido ('.strlen($token).' chars).');

        if (! $this->option('catalog')) {
            $this->comment('Ejecuta con --catalog para ver IDs de catálogos.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Catálogos (copia los id a tu .env / Coolify):');
        $this->newLine();

        $this->printCatalog('SIIGO_DOCUMENT_ID', 'Tipos de comprobante FV', function () use ($http) {
            return $http->get('v1/document-types', ['type' => 'FV']);
        }, fn ($row) => isset($row['id'], $row['name']) ? "[{$row['id']}] {$row['name']}" : null);

        $this->printCatalog('SIIGO_SELLER_ID', 'Vendedores', function () use ($http) {
            return $http->get('v1/users', ['page' => 1, 'page_size' => 25]);
        }, fn ($row) => isset($row['id'], $row['first_name']) ? "[{$row['id']}] {$row['first_name']} {$row['last_name']}" : null);

        $this->printCatalog('SIIGO_PAYMENT_ID', 'Formas de pago FV', function () use ($http) {
            return $http->get('v1/payment-types', ['document_type' => 'FV']);
        }, fn ($row) => isset($row['id'], $row['name']) ? "[{$row['id']}] {$row['name']}" : null);

        $this->printCatalog('SIIGO_TAX_ID_IVA_19', 'Impuestos (busca IVA 19%)', function () use ($http) {
            return $http->get('v1/taxes');
        }, function ($row) {
            if (! isset($row['id'], $row['name'])) {
                return null;
            }
            $pct = $row['percentage'] ?? '?';

            return "[{$row['id']}] {$row['name']} ({$pct}%)";
        });

        return self::SUCCESS;
    }

    /**
     * @param  callable(): \Illuminate\Http\Client\Response  $fetch
     * @param  callable(array<string, mixed>): ?string  $format
     */
    private function printCatalog(string $envKey, string $title, callable $fetch, callable $format): void
    {
        $this->line("<fg=cyan>{$envKey}</> — {$title}");

        try {
            $response = $fetch();
        } catch (ExternalApiException $e) {
            $this->warn('  Error: '.$e->getMessage());

            return;
        }

        if ($response->status() === 401) {
            $this->warn('  401 unauthorized — el token no fue aceptado en este endpoint.');
            $this->line('  Verifica que Partner-Id sea el mismo en /auth y en /v1/*.');

            return;
        }

        if ($response->failed()) {
            $this->warn('  HTTP '.$response->status().': '.$response->body());

            return;
        }

        $results = $response->json('results') ?? $response->json() ?? [];
        if (! is_array($results)) {
            $this->warn('  Respuesta inesperada.');

            return;
        }

        $rows = isset($results[0]) ? $results : [$results];
        $printed = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $line = $format($row);
            if ($line !== null) {
                $this->line('  '.$line);
                $printed++;
            }
        }

        if ($printed === 0) {
            $this->warn('  Sin resultados.');
        }

        $this->newLine();
    }
}
