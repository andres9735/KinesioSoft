<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    private const INACTIVE_MESSAGE =
        'Tu cuenta está inactiva. Contactá al administrador del sistema.';

    /**
     * Expulsa a cualquier usuario autenticado cuya cuenta esté inactiva.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (bool) $user->activo) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        /*
         * logout() puede renovar el token "remember me".
         * Como la cuenta está inactiva, lo anulamos explícitamente.
         */
        $user->forceFill([
            'remember_token' => null,
        ])->saveQuietly();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors([
                'email' => self::INACTIVE_MESSAGE,
            ]);
    }
}
