<?php

namespace App\Actions\Pacientes;

use App\Models\Paciente;
use App\Models\User;
use App\Services\GeneradorNumeroHistoriaClinica;
use LogicException;

final class CrearPerfilPacienteAction
{
    public function __construct(
        private readonly GeneradorNumeroHistoriaClinica $generadorNumeroHistoriaClinica
    ) {}

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

        $paciente = Paciente::create([
            'user_id' => $user->getKey(),
            'nro_historia_clinica' => 'TEMP-' . $user->getKey(),
            'observaciones' => null,
        ]);

        $paciente->update([
            'nro_historia_clinica' =>
            $this->generadorNumeroHistoriaClinica->generar($paciente),
        ]);

        return $paciente;
    }
}
