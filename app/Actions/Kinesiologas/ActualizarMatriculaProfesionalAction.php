<?php

namespace App\Actions\Kinesiologas;

use App\Models\MatriculaProfesionalKinesiologia;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class ActualizarMatriculaProfesionalAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * Actualiza únicamente el número de matrícula profesional.
     *
     * La Jurisdicción y el Colegio emisor son datos inmutables
     * y no forman parte del contrato de esta operación.
     *
     * @param  array{
     *     nro_matricula_profesional: string
     * }  $data
     */
    public function ejecutar(
        MatriculaProfesionalKinesiologia $matricula,
        array $data
    ): MatriculaProfesionalKinesiologia {
        return DB::transaction(
            function () use (
                $matricula,
                $data
            ): MatriculaProfesionalKinesiologia {
                /*
                 * Se vuelve a obtener y bloquear el registro para trabajar
                 * con sus valores persistidos más recientes durante toda
                 * la actualización y su correspondiente auditoría.
                 */
                $matriculaActual = MatriculaProfesionalKinesiologia::query()
                    ->whereKey($matricula->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $valorAnterior = (string) $matriculaActual
                    ->getRawOriginal('nro_matricula_profesional');

                $valorNuevo = $data['nro_matricula_profesional'];

                /*
                 * Si el número no cambió, no se modifica el registro
                 * ni se genera un evento de auditoría.
                 */
                if ($valorAnterior === $valorNuevo) {
                    return $matriculaActual;
                }

                $matriculaActual->update([
                    'nro_matricula_profesional' => $valorNuevo,
                ]);

                $this->auditoriaService->registrar(
                    evento: 'matricula_actualizada',
                    modulo: 'Matrículas profesionales',
                    auditable: $matriculaActual,
                    valoresAnteriores: [
                        'nro_matricula_profesional' => $valorAnterior,
                    ],
                    valoresNuevos: [
                        'nro_matricula_profesional' =>
                        $matriculaActual->nro_matricula_profesional,
                    ]
                );

                return $matriculaActual;
            }
        );
    }
}
