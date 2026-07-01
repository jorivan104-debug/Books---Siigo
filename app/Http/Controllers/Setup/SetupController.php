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

        return back()->with([
            'zoho_success' => 'Grant Token intercambiado correctamente. Copia el refresh_token a Coolify (.env).',
            'zoho_refresh_token' => $result['refresh_token'],
            'zoho_api_domain' => $result['api_domain'],
        ]);
    }

    public function testZoho(ZohoOAuthService $oauth): RedirectResponse
    {
        try {
            $oauth->clearAccessTokenCache();
            $token = $oauth->refreshAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken '.$token,
            ])->timeout((int) config('zoho.timeout', 20))
                ->get(rtrim((string) config('zoho.api_base_url'), '/').'/organizations');

            if ($response->failed()) {
                return back()->with('zoho_error', 'Token OK pero Books respondió HTTP '.$response->status());
            }

            $orgs = $response->json('organizations') ?? [];
            $labels = collect($orgs)->map(fn ($o) => "[{$o['organization_id']}] ".($o['name'] ?? ''))->take(10)->values()->all();

            return back()->with([
                'zoho_success' => 'Conexión Zoho Books OK. Organizaciones: '.count($orgs),
                'zoho_organizations' => $labels,
            ]);
        } catch (ExternalApiException $e) {
            return back()->with('zoho_error', $e->getMessage());
        }
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
