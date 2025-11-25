<?php

namespace App\Providers;

use App\Models\AntecedentePersonal;
use App\Policies\AntecedentePersonalPolicy;
use App\Models\AntecedenteFamiliar;
use App\Policies\AntecedenteFamiliarPolicy;
use App\Models\Alergia;
use App\Policies\AlergiaPolicy;
use App\Models\Cirugia;
use App\Policies\CirugiaPolicy;
use App\Models\MedicacionActual;
use App\Policies\MedicacionActualPolicy;
use App\Models\Antropometria;
use App\Policies\AntropometriaPolicy;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        AntecedentePersonal::class  => AntecedentePersonalPolicy::class,
        AntecedenteFamiliar::class  => AntecedenteFamiliarPolicy::class,
        Alergia::class              => AlergiaPolicy::class,
        Cirugia::class              => CirugiaPolicy::class,
        MedicacionActual::class     => MedicacionActualPolicy::class,
        Antropometria::class        => AntropometriaPolicy::class,
    ];

    public function boot(): void
    {
        // En Laravel 11/12, con $policies alcanza.
        // Si querés, podés dejar explícito:
        $this->registerPolicies();
    }
}
