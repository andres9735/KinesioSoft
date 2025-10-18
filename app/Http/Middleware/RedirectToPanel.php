<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectToPanel
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->hasRole('Administrador')) {
                return redirect()->intended('/admin');
            }

            if ($user->hasRole('Kinesiologa')) {
                return redirect()->intended('/kinesiologa');
            }

            if ($user->hasRole('Paciente')) {
                return redirect()->intended('/paciente');
            }
        }

        return $next($request);
    }
}
