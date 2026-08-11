<?php

namespace App\Actions\Usuarios;

use App\Actions\Pacientes\CrearPerfilPacienteAction;
use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class ActualizarUsuarioAction
{
    public function __construct(
        private readonly CrearPerfilPacienteAction $crearPerfilPacienteAction,
        private readonly AuditoriaService $auditoriaService
    ) {}

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

            $valoresAnteriores = [];
            $valoresNuevos = [];

            foreach (['name', 'apellido', 'email'] as $campo) {
                $valorAnterior = $user->getAttribute($campo);
                $valorNuevo = $data[$campo];

                if ($valorAnterior !== $valorNuevo) {
                    $valoresAnteriores[$campo] = $valorAnterior;
                    $valoresNuevos[$campo] = $valorNuevo;
                }
            }

            $user->update([
                'name' => $data['name'],
                'apellido' => $data['apellido'],
                'email' => $data['email'],
            ]);

            $cambioRol = $rolAnterior !== $rol->value;

            if ($cambioRol) {
                $user->syncRoles([$rol->value]);

                if (
                    $rol === RoleEnum::PACIENTE
                    && ! $user->paciente()->exists()
                ) {
                    $this->crearPerfilPacienteAction->ejecutar($user);
                }
            }

            if ($valoresAnteriores !== []) {
                $this->auditoriaService->registrar(
                    evento: 'usuario_actualizado',
                    modulo: 'Usuarios',
                    auditable: $user,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos
                );
            }

            if ($cambioRol) {
                $this->auditoriaService->registrar(
                    evento: 'rol_cambiado',
                    modulo: 'Usuarios',
                    auditable: $user,
                    valoresAnteriores: [
                        'rol' => $rolAnterior,
                    ],
                    valoresNuevos: [
                        'rol' => $rol->value,
                    ]
                );
            }

            return $user;
        });
    }
}
