<?php

namespace App\Actions\Usuarios;

use App\Enums\RoleEnum;
use App\Exceptions\Usuarios\NoSePuedeDesactivarUnicoSuperadminException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CambiarEstadoUsuarioAction
{
    public function ejecutar(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            /*
             * Se bloquean los superadmins activos en un orden estable.
             * De esta forma, la comprobación del "último superadmin"
             * y el cambio de estado forman una única operación atómica.
             */
            $superadminsActivos = User::query()
                ->where('activo', true)
                ->whereHas('roles', function ($query) {
                    $query->where(
                        'name',
                        RoleEnum::SUPERADMIN->value
                    );
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            /*
             * También se vuelve a obtener y bloquear el usuario objetivo
             * para trabajar con su estado más reciente dentro
             * de la transacción.
             */
            $usuario = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $usuario->load('roles');

            if (
                $usuario->esSuperadmin()
                && $usuario->activo
                && $superadminsActivos->count() <= 1
            ) {
                throw new NoSePuedeDesactivarUnicoSuperadminException();
            }

            $usuario->activo = ! (bool) $usuario->activo;

            if (! $usuario->activo) {
                $usuario->remember_token = null;
            }

            $usuario->save();

            if (
                ! $usuario->activo
                && config('session.driver') === 'database'
            ) {
                $sessionTable = config('session.table') ?: 'sessions';

                DB::table($sessionTable)
                    ->where('user_id', $usuario->getKey())
                    ->delete();
            }

            return $usuario;
        });
    }
}
