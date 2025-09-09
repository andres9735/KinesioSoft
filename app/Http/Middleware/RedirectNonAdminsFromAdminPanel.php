<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectNonAdminsFromAdminPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        // Si no está autenticado, que siga al login normal del panel
        if (! auth()->check()) {
            return $next($request);
        }

        // Si NO tiene rol Administrador y está intentando entrar a /admin/* -> redirige a /paciente
        if (! auth()->user()->hasRole('Administrador')) {
            return redirect()->route('filament.paciente.pages.dashboard');
        }

        // Es Admin: dejarlo pasar
        return $next($request);
    }
}
