<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class RestablecerContrasenaAction
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
                $user->forceFill([
                    'password' => Hash::make($nuevaContrasena),
                    'remember_token' => Str::random(60),
                ])->save();

                $this->auditoriaService->registrar(
                    evento: 'contrasena_restablecida',
                    modulo: 'Cuenta',
                    auditable: $user
                );

                return $user;
            }
        );
    }
}
