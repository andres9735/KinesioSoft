<?php

namespace App\Actions\Usuarios;

use App\Actions\Pacientes\CrearPerfilPacienteAction;
use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ActualizarUsuarioAction
{
    public function __construct(
        private readonly CrearPerfilPacienteAction $crearPerfilPacienteAction
    ) {
    }

    /**
     * @param  array{
     *     name: string,
     *     apellido: string,
     *     email: string
     * }  $data
     */
    public function ejecutar(
        User $user,
        array $data,
        RoleEnum $rol
    ): User {
        return DB::transaction(function () use ($user, $data, $rol): User {
            $user->load('roles');

            $rolAnterior = $user->roles->pluck('name')->first();

            $user->update([
                'name' => $data['name'],
                'apellido' => $data['apellido'],
                'email' => $data['email'],
            ]);

            if ($rolAnterior !== $rol->value) {
                $user->syncRoles([$rol->value]);

                if (
                    $rol === RoleEnum::PACIENTE
                    && ! $user->paciente()->exists()
                ) {
                    $this->crearPerfilPacienteAction->ejecutar($user);
                }
            }

            return $user;
        });
    }
}
