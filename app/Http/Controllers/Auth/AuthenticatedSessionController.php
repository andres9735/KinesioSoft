<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $request->user()->forceFill(['last_login_at' => now()])->save();

        $user = $request->user();

        if ($user->hasRole('Administrador')) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        if ($user->hasRole('Kinesiologa')) {
            return redirect()->route('filament.kinesiologa.pages.dashboard');
        }

        if ($user->hasRole('Paciente')) {
            return redirect()->route('filament.paciente.pages.dashboard');
        }

        Auth::logout();
        return redirect('/login')->withErrors([
            'email' => 'Tu cuenta no tiene un rol asignado válido.',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
