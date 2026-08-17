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
     * @param  array{
     *     name: string,
     *     apellido: string,
     *     email: string
     * }  $data
     */
    public function ejecutar(
        User $user,
        array $data
    ): User {
        return DB::transaction(
            function () use ($user, $data): User {
                $nombreAnterior = (string) $user->getRawOriginal('name');
                $apellidoAnterior = (string) $user->getRawOriginal('apellido');
                $emailAnterior = (string) $user->getRawOriginal('email');

                $nombreNuevo = $data['name'];
                $apellidoNuevo = $data['apellido'];
                $emailNuevo = $data['email'];

                $valoresAnteriores = [];
                $valoresNuevos = [];

                if ($nombreAnterior !== $nombreNuevo) {
                    $valoresAnteriores['name'] = $nombreAnterior;
                    $valoresNuevos['name'] = $nombreNuevo;
                }

                if ($apellidoAnterior !== $apellidoNuevo) {
                    $valoresAnteriores['apellido'] = $apellidoAnterior;
                    $valoresNuevos['apellido'] = $apellidoNuevo;
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
                    'apellido' => $apellidoNuevo,
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
