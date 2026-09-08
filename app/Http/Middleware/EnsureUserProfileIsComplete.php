<?php

namespace App\Http\Middleware;

use App\Enums\RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserProfileIsComplete
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->hasRole(RoleEnum::PACIENTE->value)) {
            $estaEnRutaPermitida =
                $request->routeIs('perfil.completar') ||
                $request->routeIs('perfil.validar-paso-1') ||
                $request->routeIs('perfil.validar-paso-2') ||
                $request->routeIs('perfil.actualizar') ||
                $request->routeIs('logout');

            if ($user->perfilCompleto() === false && ! $estaEnRutaPermitida) {
                return redirect()->route('perfil.completar');
            }
        }

        if ($user->hasRole(RoleEnum::KINESIOLOGO->value)) {
            $estaEnRutaPermitidaKinesiologo =
                $request->routeIs('perfil.kinesiologa.completar') ||
                $request->routeIs('perfil.kinesiologa.actualizar') ||
                $request->routeIs('logout');

            if ($user->perfilCompleto() === false && ! $estaEnRutaPermitidaKinesiologo) {
                return redirect()->route('perfil.kinesiologa.completar');
            }
        }

        return $next($request);
    }
}
