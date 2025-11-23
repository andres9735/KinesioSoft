<?php

namespace App\Services;

use App\Models\User;
use App\Models\Paciente;

class PacienteService
{
    /** Crea el perfil de paciente si el user tiene el rol Paciente */
    public static function ensureProfile(User $user): void
    {
        if ($user->hasRole('Paciente')) {
            Paciente::firstOrCreate(
                ['user_id' => $user->id],
                ['nombre'  => $user->name]
            );
        }
    }
}
