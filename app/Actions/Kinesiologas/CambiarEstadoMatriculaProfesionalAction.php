<?php

namespace App\Actions\Kinesiologas;

use App\Exceptions\Kinesiologas\NoSePuedeInactivarUltimaMatriculaActivaException;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CambiarEstadoMatriculaProfesionalAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(
        MatriculaProfesionalKinesiologia $matricula
    ): MatriculaProfesionalKinesiologia {
        return DB::transaction(
            function () use ($matricula): MatriculaProfesionalKinesiologia {
                /*
                 * Se bloquean las matrículas activas de la kinesióloga
                 * en un orden estable para que la comprobación de la
                 * "última matrícula activa" y el cambio de estado sean
                 * una única operación atómica.
                 */
                $matriculasActivas = MatriculaProfesionalKinesiologia::query()
                    ->where('kinesiologa_id', $matricula->kinesiologa_id)
                    ->where('activa', true)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                /*
                 * Se vuelve a obtener y bloquear la matrícula objetivo
                 * para trabajar con su estado más reciente.
                 */
                $matriculaActual = MatriculaProfesionalKinesiologia::query()
                    ->whereKey($matricula->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $estadoAnterior = (bool) $matriculaActual->activa;

                if (
                    $estadoAnterior
                    && $matriculasActivas->count() <= 1
                ) {
                    throw new NoSePuedeInactivarUltimaMatriculaActivaException();
                }

                $matriculaActual->activa = ! $estadoAnterior;
                $matriculaActual->save();

                $this->auditoriaService->registrar(
                    evento: $matriculaActual->activa
                        ? 'matricula_activada'
                        : 'matricula_inactivada',
                    modulo: 'Matrículas profesionales',
                    auditable: $matriculaActual,
                    valoresAnteriores: [
                        'activa' => $estadoAnterior,
                    ],
                    valoresNuevos: [
                        'activa' => (bool) $matriculaActual->activa,
                    ]
                );

                return $matriculaActual;
            }
        );
    }
}
