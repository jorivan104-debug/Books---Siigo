<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetupAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('setup_authenticated') !== true) {
            return redirect()->route('setup.login')
                ->with('error', 'Inicia sesión con tu INTEGRATION_API_KEY para acceder al panel de configuración.');
        }

        return $next($request);
    }
}
