<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Paciente;

class UserObserver
{
    public function created(User $user): void
    {
        if ($user->hasRole('Paciente')) {
            Paciente::firstOrCreate(
                ['user_id' => $user->id],
                ['nombre'  => $user->name]
            );
        }
    }
}
