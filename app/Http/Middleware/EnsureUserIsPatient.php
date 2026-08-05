<?php

namespace App\Http\Middleware;

use App\Enums\RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsPatient
{
    /**
     * Permite el acceso únicamente a usuarios con rol paciente.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(RoleEnum::PACIENTE->value)) {
            abort(403);
        }

        return $next($request);
    }
}
