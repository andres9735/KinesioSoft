<?php

namespace App\Providers;

use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumental;
use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumentalMariaDb;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CoordinadorBloqueoDocumental::class, fn ($app) => new CoordinadorBloqueoDocumentalMariaDb(
            $app['db']->connection()
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
