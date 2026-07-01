<?php

namespace App\Console\Commands;

use App\Exceptions\ExternalApiException;
use App\Services\ZohoOAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ZohoTestConnectionCommand extends Command
{
    protected $signature = 'zoho:test-connection';

    protected $description = 'Verifica que las credenciales Zoho (Self Client) puedan obtener un access_token';

    public function handle(ZohoOAuthService $oauth): int
    {
        $this->info('Tipo de cliente: '.config('zoho.client_type', 'self'));
        $this->line('Accounts URL: '.config('zoho.accounts_url'));
        $this->line('Books API: '.config('zoho.api_base_url'));
        $this->newLine();

        try {
            $token = $oauth->refreshAccessToken();
        } catch (ExternalApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Access token obtenido correctamente.');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken '.$token,
            ])->timeout((int) config('zoho.timeout', 20))
                ->get(rtrim((string) config('zoho.api_base_url'), '/').'/organizations');
        } catch (\Throwable $e) {
            $this->warn('Token OK, pero falló la consulta a Books: '.$e->getMessage());

            return self::SUCCESS;
        }

        if ($response->failed()) {
            $this->warn('Token OK, pero Books respondió con error '.$response->status());
            $this->line($response->body());

            return self::SUCCESS;
        }

        $organizations = $response->json('organizations') ?? [];
        $count = is_array($organizations) ? count($organizations) : 0;
        $this->info("Conexión a Zoho Books OK. Organizaciones accesibles: {$count}");

        foreach ($organizations as $org) {
            if (isset($org['organization_id'], $org['name'])) {
                $this->line("  - [{$org['organization_id']}] {$org['name']}");
            }
        }

        return self::SUCCESS;
    }
}
