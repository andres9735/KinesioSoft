<?php

namespace App\Actions\Kinesiologas;

use App\Exceptions\Kinesiologas\NoSePuedeInactivarUltimaMatriculaActivaException;
use App\Exceptions\Kinesiologas\NoSePuedeReactivarMatriculaConCatalogoInactivoException;
use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CambiarEstadoMatriculaProfesionalAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * Cambia la matrícula al estado solicitado.
     *
     * La operación es idempotente: si el registro ya se encuentra
     * en el estado solicitado, no se modifica ni se audita.
     */
    public function ejecutar(
        MatriculaProfesionalKinesiologia $matricula,
        bool $activar
    ): MatriculaProfesionalKinesiologia {
        return DB::transaction(
            function () use (
                $matricula,
                $activar
            ): MatriculaProfesionalKinesiologia {
                /*
                 * Para una inactivación se bloquean primero todas las
                 * matrículas activas de la kinesióloga en un orden estable.
                 *
                 * De esta forma, comprobar que quedará al menos otra
                 * matrícula activa y realizar el cambio forman una única
                 * operación atómica.
                 */
                if (! $activar) {
                    $matriculasActivas =
                        MatriculaProfesionalKinesiologia::query()
                        ->where(
                            'kinesiologa_id',
                            $matricula->kinesiologa_id
                        )
                        ->where('activa', true)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    $matriculaActual =
                        MatriculaProfesionalKinesiologia::query()
                        ->whereKey($matricula->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    /*
                     * Si otra solicitud ya la inactivó, la operación
                     * solicitada ya se encuentra satisfecha.
                     */
                    if (! (bool) $matriculaActual->activa) {
                        return $matriculaActual;
                    }

                    if ($matriculasActivas->count() <= 1) {
                        throw new NoSePuedeInactivarUltimaMatriculaActivaException();
                    }

                    return $this->persistirCambioEstado(
                        $matriculaActual,
                        false
                    );
                }

                /*
                 * Para una reactivación se bloquea primero la matrícula
                 * objetivo para trabajar con su estado persistido más
                 * reciente.
                 */
                $matriculaActual =
                    MatriculaProfesionalKinesiologia::query()
                    ->whereKey($matricula->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Si otra solicitud ya la reactivó, no se vuelve a cambiar
                 * el estado ni se genera una auditoría duplicada.
                 */
                if ((bool) $matriculaActual->activa) {
                    return $matriculaActual;
                }

                /*
                 * La credencial solo puede volver a ser operativa si su
                 * Jurisdicción y su Colegio emisor continúan activos.
                 *
                 * Ambos registros se bloquean durante la comprobación para
                 * evitar que su estado cambie concurrentemente mientras
                 * se procesa la reactivación.
                 */
                $jurisdiccion = Jurisdiccion::query()
                    ->whereKey($matriculaActual->jurisdiccion_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $colegioEmisor = ColegioEmisor::query()
                    ->whereKey($matriculaActual->colegio_emisor_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    ! (bool) $jurisdiccion->activo
                    || ! (bool) $colegioEmisor->activo
                ) {
                    throw new NoSePuedeReactivarMatriculaConCatalogoInactivoException();
                }

                return $this->persistirCambioEstado(
                    $matriculaActual,
                    true
                );
            }
        );
    }

    private function persistirCambioEstado(
        MatriculaProfesionalKinesiologia $matricula,
        bool $activar
    ): MatriculaProfesionalKinesiologia {
        $estadoAnterior = (bool) $matricula->activa;

        $matricula->activa = $activar;
        $matricula->save();

        $this->auditoriaService->registrar(
            evento: $activar
                ? 'matricula_activada'
                : 'matricula_inactivada',
            modulo: 'Matrículas profesionales',
            auditable: $matricula,
            valoresAnteriores: [
                'activa' => $estadoAnterior,
            ],
            valoresNuevos: [
                'activa' => $activar,
            ]
        );

        return $matricula;
    }
}
