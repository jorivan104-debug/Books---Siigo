<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SetupAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (session('setup_authenticated') === true) {
            return redirect()->route('setup.index');
        }

        return view('setup.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'integration_key' => ['required', 'string'],
        ]);

        $expected = (string) config('integration.api_key', '');
        $provided = (string) $request->input('integration_key');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return back()
                ->withInput()
                ->with('error', 'Clave incorrecta. Usa el valor de INTEGRATION_API_KEY.');
        }

        $request->session()->regenerate();
        $request->session()->put('setup_authenticated', true);

        return redirect()->route('setup.index')
            ->with('success', 'Sesión iniciada correctamente.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('setup_authenticated');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('setup.login')
            ->with('success', 'Sesión cerrada.');
    }
}
