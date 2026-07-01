@extends('layouts.setup')

@section('title', 'Acceso')

@section('content')
    <header>
        <div>
            <h1>{{ config('app.name') }}</h1>
            <span>Panel de configuración de integraciones</span>
        </div>
    </header>

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card" style="max-width: 420px; margin: 2rem auto;">
        <h2>Iniciar sesión</h2>
        <p class="desc">Usa la misma clave configurada en <code style="display:inline;padding:0.1rem 0.35rem;">INTEGRATION_API_KEY</code>.</p>

        <form method="POST" action="{{ route('setup.login') }}">
            @csrf
            <label for="integration_key">Integration API Key</label>
            <input
                type="password"
                id="integration_key"
                name="integration_key"
                value="{{ old('integration_key') }}"
                required
                autofocus
                autocomplete="off"
            >
            @error('integration_key')
                <div class="alert alert-error">{{ $message }}</div>
            @enderror
            <button type="submit" class="btn btn-primary">Entrar</button>
        </form>
    </div>
@endsection
