<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class ActualizarCuentaPropiaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * @param  array{name: string, email: string}  $data
     */
    public function ejecutar(
        User $user,
        array $data
    ): User {
        return DB::transaction(
            function () use ($user, $data): User {
                $nombreAnterior = (string) $user->getRawOriginal('name');
                $emailAnterior = (string) $user->getRawOriginal('email');

                $nombreNuevo = $data['name'];
                $emailNuevo = $data['email'];

                $valoresAnteriores = [];
                $valoresNuevos = [];

                if ($nombreAnterior !== $nombreNuevo) {
                    $valoresAnteriores['name'] = $nombreAnterior;
                    $valoresNuevos['name'] = $nombreNuevo;
                }

                if ($emailAnterior !== $emailNuevo) {
                    $valoresAnteriores['email'] = $emailAnterior;
                    $valoresNuevos['email'] = $emailNuevo;
                }

                if ($valoresNuevos === []) {
                    return $user;
                }

                $user->fill([
                    'name' => $nombreNuevo,
                    'email' => $emailNuevo,
                ]);

                if ($emailAnterior !== $emailNuevo) {
                    $user->email_verified_at = null;
                }

                $user->save();

                $this->auditoriaService->registrar(
                    evento: 'cuenta_actualizada',
                    modulo: 'Cuenta',
                    auditable: $user,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos
                );

                return $user;
            }
        );
    }
}
