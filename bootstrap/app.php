<?php

use App\Http\Middleware\EnsureAdminOrSuperadmin;
use App\Http\Middleware\EnsureUserIsKinesiologist;
use App\Http\Middleware\EnsureUserIsPatient;
use App\Http\Middleware\EnsureUserProfileIsComplete;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'perfil.completo' => EnsureUserProfileIsComplete::class,
            'perfil.paciente' => EnsureUserIsPatient::class,
            'perfil.kinesiologo' => EnsureUserIsKinesiologist::class,
            'admin.or.superadmin' => EnsureAdminOrSuperadmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
