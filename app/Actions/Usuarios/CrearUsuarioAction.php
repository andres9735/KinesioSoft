<?php

namespace App\Actions\Usuarios;

use App\Actions\Pacientes\CrearPerfilPacienteAction;
use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class CrearUsuarioAction
{
    public function __construct(
        private readonly CrearPerfilPacienteAction $crearPerfilPacienteAction,
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * @param array{
     *     name: string,
     *     apellido: string,
     *     email: string,
     *     password: string
     * } $data
     */
    public function ejecutar(array $data, RoleEnum $rol): User
    {
        return DB::transaction(function () use ($data, $rol): User {
            $user = User::create([
                'name' => $data['name'],
                'apellido' => $data['apellido'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'activo' => true,
            ]);

            $user->assignRole($rol->value);

            if ($rol === RoleEnum::PACIENTE) {
                $this->crearPerfilPacienteAction->ejecutar($user);
            }

            $this->auditoriaService->registrar(
                evento: 'usuario_creado',
                modulo: 'Usuarios',
                auditable: $user,
                valoresNuevos: [
                    'name' => $user->name,
                    'apellido' => $user->apellido,
                    'email' => $user->email,
                    'activo' => (bool) $user->activo,
                    'rol' => $rol->value,
                ]
            );

            return $user;
        });
    }
}
