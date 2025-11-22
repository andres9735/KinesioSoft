<?php

namespace App\Providers;

use App\Models\Alergia;
use App\Models\AntecedenteFamiliar;
use App\Models\AntecedentePersonal;
use App\Policies\AlergiaPolicy;
use App\Policies\AntecedenteFamiliarPolicy;
use App\Policies\AntecedentePersonalPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        AntecedentePersonal::class  => AntecedentePersonalPolicy::class,
        AntecedenteFamiliar::class  => AntecedenteFamiliarPolicy::class,
        Alergia::class              => AlergiaPolicy::class,
    ];

    public function boot(): void
    {
        // En Laravel 11/12, con $policies alcanza.
        // Si querés, podés dejar explícito:
        $this->registerPolicies();
    }
}
