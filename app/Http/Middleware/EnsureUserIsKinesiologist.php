<?php

namespace App\Http\Middleware;

use App\Enums\RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsKinesiologist
{
    /**
     * Permite el acceso únicamente a usuarios con rol kinesiólogo.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(RoleEnum::KINESIOLOGO->value)) {
            abort(403);
        }

        return $next($request);
    }
}
