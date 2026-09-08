<?php

namespace App\Actions\Pacientes;

use App\Models\Paciente;
use App\Models\User;
use LogicException;

final class CrearPerfilPacienteAction
{
    public function ejecutar(User $user): Paciente
    {
        if (! $user->exists || $user->getKey() === null) {
            throw new LogicException(
                'El usuario debe estar persistido antes de crear su perfil de paciente.'
            );
        }

        if ($user->paciente()->exists()) {
            throw new LogicException(
                'El usuario ya tiene un perfil de paciente.'
            );
        }

        return Paciente::create([
            'user_id' => $user->getKey(),
            'direccion' => null,
            'contacto_emergencia_nombre' => null,
            'contacto_emergencia_telefono' => null,
            'activo' => true,
        ]);
    }
}
