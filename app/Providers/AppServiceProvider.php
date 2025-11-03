<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;
use App\Models\Valoracion;
use App\Observers\ValoracionObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));
        setlocale(LC_TIME, 'es_AR.UTF-8', 'es_ES.UTF-8', 'es.UTF-8');

        Valoracion::observe(\App\Observers\ValoracionObserver::class);
    }
}
