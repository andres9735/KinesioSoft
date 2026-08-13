<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class CambiarContrasenaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(
        User $user,
        string $nuevaContrasena
    ): User {
        return DB::transaction(
            function () use ($user, $nuevaContrasena): User {
                $user->update([
                    'password' => Hash::make($nuevaContrasena),
                ]);

                $this->auditoriaService->registrar(
                    evento: 'contrasena_actualizada',
                    modulo: 'Cuenta',
                    auditable: $user
                );

                return $user;
            }
        );
    }
}
