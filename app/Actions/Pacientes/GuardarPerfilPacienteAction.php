<?php

namespace App\Actions\Pacientes;

use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class GuardarPerfilPacienteAction
{
    private const CAMPOS_PERFIL = [
        'dni',
        'fecha_nacimiento',
        'sexo',
        'telefono',
    ];

    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * @param  array{
     *     dni: string,
     *     fecha_nacimiento: string,
     *     sexo: string,
     *     telefono: string
     * }  $data
     */
    public function ejecutar(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $perfilEstabaCompleto = $this->perfilCompleto($user);

            $valoresAnteriores = [];
            $valoresNuevos = [];

            foreach (self::CAMPOS_PERFIL as $campo) {
                $valorAnterior = $user->getRawOriginal($campo);
                $valorNuevo = $data[$campo];

                if ($valorAnterior !== $valorNuevo) {
                    $valoresAnteriores[$campo] = $valorAnterior;
                    $valoresNuevos[$campo] = $valorNuevo;
                }
            }

            if ($valoresAnteriores === []) {
                return $user;
            }

            $user->update([
                'dni' => $data['dni'],
                'fecha_nacimiento' => $data['fecha_nacimiento'],
                'sexo' => $data['sexo'],
                'telefono' => $data['telefono'],
            ]);

            $this->auditoriaService->registrar(
                evento: $perfilEstabaCompleto
                    ? 'perfil_paciente_actualizado'
                    : 'perfil_paciente_completado',
                modulo: 'Perfiles',
                auditable: $user,
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $valoresNuevos
            );

            return $user;
        });
    }

    private function perfilCompleto(User $user): bool
    {
        foreach (self::CAMPOS_PERFIL as $campo) {
            $valor = $user->getRawOriginal($campo);

            if ($valor === null || $valor === '') {
                return false;
            }
        }

        return true;
    }
}
