@extends('layouts.setup')

@section('title', 'Integraciones')

@section('content')
    <header>
        <div>
            <h1>{{ config('app.name') }}</h1>
            <span>Autenticación y catálogos</span>
        </div>
        <form method="POST" action="{{ route('setup.logout') }}">
            @csrf
            <button type="submit" class="btn btn-ghost">Cerrar sesión</button>
        </form>
    </header>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Estado de variables de entorno --}}
    <div class="card">
        <h2>Estado de configuración</h2>
        <p class="desc">Variables detectadas en el entorno (sin mostrar valores secretos).</p>
        <div class="status-grid">
            @foreach ($configStatus as $key => $item)
                <div class="status-item">
                    <span>{{ $item['hint'] }}</span>
                    <span class="badge {{ $item['configured'] ? 'badge-ok' : 'badge-no' }}">
                        {{ $item['configured'] ? 'OK' : 'Falta' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ZOHO --}}
    <div class="card">
        <h2>Zoho Books — Self Client</h2>
        <p class="desc">Intercambia un Grant Token por <code style="display:inline;padding:0.1rem 0.35rem;">ZOHO_REFRESH_TOKEN</code>.</p>

        @if (session('zoho_error'))
            <div class="alert alert-error">{{ session('zoho_error') }}</div>
        @endif
        @if (session('zoho_success'))
            <div class="alert alert-success">{{ session('zoho_success') }}</div>
        @endif

        <ol class="steps">
            <li>Ve a <a href="https://api-console.zoho.com" target="_blank" rel="noopener" style="color:#93c5fd">api-console.zoho.com</a> → Self Client → Generate Code</li>
            <li>Scopes: <code style="display:inline;padding:0.1rem 0.35rem;font-size:0.75rem;">{{ $zohoScopes }}</code></li>
            <li>Pega el Grant Token abajo (caduca en minutos)</li>
        </ol>

        <form method="POST" action="{{ route('setup.zoho.exchange') }}">
            @csrf
            <label for="grant_token">Grant Token</label>
            <textarea id="grant_token" name="grant_token" placeholder="1000.xxxx..." required>{{ old('grant_token') }}</textarea>
            <div class="row-actions">
                <button type="submit" class="btn btn-primary">Intercambiar por refresh_token</button>
            </div>
        </form>

        @if (session('zoho_refresh_token'))
            <p style="margin-top:1rem;font-size:0.875rem;color:var(--muted);">Copia a Coolify / .env:</p>
            <code class="mono env-line">ZOHO_REFRESH_TOKEN={{ session('zoho_refresh_token') }}</code>
            @if (session('zoho_api_domain'))
                <p style="margin-top:0.5rem;font-size:0.8125rem;color:var(--muted);">API domain: {{ session('zoho_api_domain') }}</p>
            @endif
        @endif

        <hr style="border:none;border-top:1px solid var(--border);margin:1.25rem 0;">

        <form method="POST" action="{{ route('setup.zoho.test') }}">
            @csrf
            <p class="desc" style="margin-bottom:0.75rem;">Prueba la conexión con el refresh_token ya configurado en el servidor.</p>
            <button type="submit" class="btn btn-ghost">Probar conexión Zoho Books</button>
        </form>

        @if (session('zoho_organizations'))
            <ul style="margin-top:0.75rem;font-size:0.875rem;color:var(--muted);list-style:none;">
                @foreach (session('zoho_organizations') as $org)
                    <li>{{ $org }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- SIIGO --}}
    <div class="card">
        <h2>Siigo Nube</h2>
        <p class="desc">
            Verifica credenciales y obtén IDs de catálogos.
            Partner-Id actual: <strong>{{ $siigoPartnerId }}</strong>
        </p>

        @if (session('siigo_error'))
            <div class="alert alert-error">{{ session('siigo_error') }}</div>
        @endif
        @if (session('siigo_success'))
            <div class="alert alert-success">{{ session('siigo_success') }}</div>
        @endif

        <form method="POST" action="{{ route('setup.siigo.test') }}">
            @csrf
            <p class="desc" style="margin-bottom:0.75rem;">
                Requiere <code style="display:inline;padding:0.1rem 0.35rem;">SIIGO_USERNAME</code>,
                <code style="display:inline;padding:0.1rem 0.35rem;">SIIGO_ACCESS_KEY</code> y
                <code style="display:inline;padding:0.1rem 0.35rem;">SIIGO_PARTNER_ID</code> en el entorno.
            </p>
            <button type="submit" class="btn btn-primary">Autenticar y listar catálogos</button>
        </form>

        @if (session('siigo_catalogs'))
            @php $catalogs = session('siigo_catalogs'); @endphp

            <h3 style="font-size:0.9375rem;margin:1.25rem 0 0.5rem;">SIIGO_DOCUMENT_ID — Tipos comprobante FV</h3>
            @include('setup.partials.catalog-table', ['items' => $catalogs['document_types'] ?? [], 'envKey' => 'SIIGO_DOCUMENT_ID'])

            <h3 style="font-size:0.9375rem;margin:1.25rem 0 0.5rem;">SIIGO_SELLER_ID — Vendedores</h3>
            @include('setup.partials.catalog-table', ['items' => $catalogs['sellers'] ?? [], 'envKey' => 'SIIGO_SELLER_ID'])

            <h3 style="font-size:0.9375rem;margin:1.25rem 0 0.5rem;">SIIGO_PAYMENT_ID — Formas de pago</h3>
            @include('setup.partials.catalog-table', ['items' => $catalogs['payment_types'] ?? [], 'envKey' => 'SIIGO_PAYMENT_ID'])

            <h3 style="font-size:0.9375rem;margin:1.25rem 0 0.5rem;">SIIGO_TAX_ID_IVA_19 — Impuestos</h3>
            @include('setup.partials.catalog-table', ['items' => $catalogs['taxes'] ?? [], 'envKey' => 'SIIGO_TAX_ID_IVA_19'])
        @endif
    </div>

    <div class="card">
        <h2>API de sincronización</h2>
        <p class="desc">Una vez configurado todo, el endpoint para Zoho Deluge es:</p>
        <code class="mono">POST {{ url('/api/zoho/invoice/sync') }}</code>
        <p style="margin-top:0.75rem;font-size:0.8125rem;color:var(--muted);">Header: X-INTEGRATION-KEY</p>
    </div>
@endsection
