<?php

namespace App\Http\Controllers\Setup;

use App\Exceptions\ExternalApiException;
use App\Http\Controllers\Controller;
use App\Services\SiigoAuthService;
use App\Services\SiigoCatalogService;
use App\Services\ZohoOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function index(): View
    {
        return view('setup.index', [
            'configStatus' => $this->configStatus(),
            'zohoScopes' => config('zoho.oauth_scopes'),
            'siigoPartnerId' => config('siigo.partner_id'),
        ]);
    }

    public function exchangeZohoGrantToken(Request $request, ZohoOAuthService $oauth): RedirectResponse
    {
        $request->validate([
            'grant_token' => ['required', 'string', 'min:10'],
        ]);

        try {
            $result = $oauth->exchangeGrantToken($request->input('grant_token'));
        } catch (ExternalApiException $e) {
            return back()->with('zoho_error', $e->getMessage());
        }

        if ($result['refresh_token'] === null) {
            return back()->with('zoho_error', 'Zoho no devolvió refresh_token. Verifica los scopes del Grant Token.');
        }

        // Verifica Books con el access_token recién obtenido (no depende aún de Coolify).
        $orgFlash = $this->fetchZohoOrganizations($result['access_token']);

        $flash = [
            'zoho_refresh_token' => $result['refresh_token'],
            'zoho_api_domain' => $result['api_domain'],
        ];

        if (isset($orgFlash['zoho_error'])) {
            $flash['zoho_success'] = 'Grant Token intercambiado. Copia ZOHO_REFRESH_TOKEN a Coolify y reinicia.';
            $flash['zoho_error'] = $orgFlash['zoho_error'];
        } else {
            $flash['zoho_success'] = ($orgFlash['zoho_success'] ?? 'Grant Token OK.')
                .' Copia ZOHO_REFRESH_TOKEN a Coolify, reinicia el servicio y luego usa «Probar conexión».';
            $flash['zoho_organizations'] = $orgFlash['zoho_organizations'] ?? [];
        }

        return back()->with($flash);
    }

    public function testZoho(ZohoOAuthService $oauth): RedirectResponse
    {
        if ((string) config('zoho.refresh_token') === '') {
            return back()->with(
                'zoho_error',
                'Falta ZOHO_REFRESH_TOKEN en Coolify. Primero intercambia el Grant Token, copia la línea ZOHO_REFRESH_TOKEN=... a las variables de entorno, reinicia el servicio y vuelve a probar.',
            );
        }

        try {
            $oauth->clearAccessTokenCache();
            $token = $oauth->refreshAccessToken();
            $orgFlash = $this->fetchZohoOrganizations($token);

            if (isset($orgFlash['zoho_error'])) {
                return back()->with('zoho_error', $orgFlash['zoho_error']);
            }

            return back()->with(array_merge([
                'zoho_success' => 'Conexión Zoho Books OK (refresh_token del servidor).',
            ], $orgFlash));
        } catch (ExternalApiException $e) {
            return back()->with('zoho_error', $e->getMessage());
        }
    }

    /**
     * @return array{zoho_organizations?: list<string>, zoho_error?: string, zoho_success?: string}
     */
    private function fetchZohoOrganizations(string $accessToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken '.$accessToken,
        ])->timeout((int) config('zoho.timeout', 20))
            ->get(rtrim((string) config('zoho.api_base_url'), '/').'/organizations');

        if ($response->failed()) {
            return [
                'zoho_error' => 'Token OK pero Books respondió HTTP '.$response->status().'. Revisa ZOHO_API_BASE_URL (com vs eu).',
            ];
        }

        $orgs = $response->json('organizations') ?? [];
        $labels = collect($orgs)
            ->map(fn ($o) => "[{$o['organization_id']}] ".($o['name'] ?? ''))
            ->take(10)
            ->values()
            ->all();

        return [
            'zoho_organizations' => $labels,
            'zoho_success' => 'Conexión Zoho Books OK. Organizaciones: '.count($orgs),
        ];
    }

    public function testSiigo(SiigoAuthService $auth, SiigoCatalogService $catalogs): RedirectResponse
    {
        try {
            $auth->clearAccessTokenCache();
            $auth->requestNewToken();
            $catalogData = $catalogs->fetchAll();

            return back()->with([
                'siigo_success' => 'Autenticación Siigo OK. Catálogos obtenidos.',
                'siigo_catalogs' => $catalogData,
            ]);
        } catch (ExternalApiException $e) {
            return back()->with('siigo_error', $e->getMessage());
        }
    }

    /**
     * @return array<string, array{configured: bool, hint: string}>
     */
    private function configStatus(): array
    {
        return [
            'zoho_client_id' => [
                'configured' => (string) config('zoho.client_id') !== '',
                'hint' => 'ZOHO_CLIENT_ID',
            ],
            'zoho_client_secret' => [
                'configured' => (string) config('zoho.client_secret') !== '',
                'hint' => 'ZOHO_CLIENT_SECRET',
            ],
            'zoho_refresh_token' => [
                'configured' => (string) config('zoho.refresh_token') !== '',
                'hint' => 'ZOHO_REFRESH_TOKEN',
            ],
            'siigo_username' => [
                'configured' => (string) config('siigo.username') !== '',
                'hint' => 'SIIGO_USERNAME',
            ],
            'siigo_access_key' => [
                'configured' => (string) config('siigo.access_key') !== '',
                'hint' => 'SIIGO_ACCESS_KEY',
            ],
            'siigo_partner_id' => [
                'configured' => preg_match('/^[a-zA-Z0-9]{3,100}$/', (string) config('siigo.partner_id')) === 1,
                'hint' => 'SIIGO_PARTNER_ID (alfanumérico, sin guiones)',
            ],
        ];
    }
}
