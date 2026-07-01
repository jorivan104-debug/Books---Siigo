<?php

namespace App\Console\Commands;

use App\Exceptions\ExternalApiException;
use App\Services\ZohoOAuthService;
use Illuminate\Console\Command;

class ZohoExchangeGrantTokenCommand extends Command
{
    protected $signature = 'zoho:exchange-grant-token
                            {code : Grant Token generado en Zoho API Console (Self Client → Generate Code)}
                            {--show-env : Muestra la línea ZOHO_REFRESH_TOKEN lista para copiar al .env}';

    protected $description = 'Intercambia un Grant Token de Zoho Self Client por refresh_token y access_token';

    public function handle(ZohoOAuthService $oauth): int
    {
        $clientType = config('zoho.client_type', 'self');
        $this->info("Tipo de cliente OAuth: {$clientType}");

        if ($oauth->isSelfClient()) {
            $this->line('Scopes recomendados para este proyecto:');
            $this->line('  '.config('zoho.oauth_scopes'));
            $this->newLine();
        }

        try {
            $result = $oauth->exchangeGrantToken((string) $this->argument('code'));
        } catch (ExternalApiException $e) {
            $this->error($e->getMessage());
            if ($e->responseBody !== []) {
                $this->line(json_encode($e->responseBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            return self::FAILURE;
        }

        $this->info('Intercambio exitoso.');
        $this->line('Access token obtenido (válido ~'.$result['expires_in'].' segundos).');

        if ($result['api_domain'] !== null) {
            $this->line('API domain: '.$result['api_domain']);
        }

        if ($result['refresh_token'] === null) {
            $this->warn('Zoho no devolvió refresh_token. Verifica los scopes del Grant Token.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Guarda este refresh_token en tu .env o en Coolify:');

        if ($this->option('show-env')) {
            $this->line('ZOHO_REFRESH_TOKEN='.$result['refresh_token']);
        } else {
            $this->line($result['refresh_token']);
        }

        $this->newLine();
        $this->comment('El refresh_token no expira. No lo compartas ni lo subas a Git.');

        return self::SUCCESS;
    }
}
