<?php

namespace App\Actions\Turnos;

use App\Models\IntencionEvaluacionCupo;
use App\Models\OfertaAdelanto;
use App\Models\Turno;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use App\Services\Turnos\TransaccionTurnoService;
use App\Services\Turnos\ValidarDestinoAdelantoService;
use Carbon\CarbonImmutable;

final class ActivarOfertaAdelantoAction
{
    public function __construct(
        private readonly TransaccionTurnoService $transacciones,
        private readonly ValidarDestinoAdelantoService $validador,
        private readonly AgendaQueryService $agenda,
        private readonly RelojAplicacion $reloj,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * Operación interna: el futuro consumidor selecciona primero; aquí se relee todo.
     * null = no activable. No consume la intención ni envía correo.
     * El mutex de kinesióloga serializa también reservas de distintos cupos que se cruzan.
     */
    public function ejecutar(int $intencionId, int $turnoId): ?OfertaAdelanto
    {
        return $this->transacciones->ejecutar($turnoId, function (Turno $candidato) use ($intencionId): ?OfertaAdelanto {
            $intencion = IntencionEvaluacionCupo::query()->lockForUpdate()->findOrFail($intencionId);
            $profesional = $candidato->kinesiologa;
            if ((int) $intencion->kinesiologa_id !== (int) $profesional->id) {
                return null;
            }

            $anteriores = OfertaAdelanto::query()->where('intencion_evaluacion_cupo_id', $intencionId)
                ->orderBy('id')->lockForUpdate()->get();
            $destino = $this->validador->validar($candidato, $profesional, $intencion->inicio_at, $intencion->fin_at, bloquear: true);
            // Para reservar el cupo completo también se comprueba su porción sobrante.
            $turnos = $this->agenda->turnosAfectadosPorIntervalo($profesional, $intencion->inicio_at, $intencion->fin_at)
                ->lockForUpdate()->get();
            $bloqueos = $this->agenda->bloqueosSuperpuestosCon($profesional, $intencion->inicio_at, $intencion->fin_at)
                ->lockForUpdate()->get();
            $reservas = $this->agenda->reservasSuperpuestasCon($profesional, $intencion->inicio_at, $intencion->fin_at)
                ->lockForUpdate()->get();
            // Plazo efectivo después de todas las lecturas potencialmente bloqueantes.
            $ahora = $this->reloj->ahora();
            $limiteBusqueda = $intencion->inicio_at->utc()->addHours(168);
            if ($destino === null || $intencion->inicio_at->utc()->lt($ahora->utc()->addHours(4))
                || ! $candidato->inicio_at->gt($intencion->inicio_at) || $candidato->inicio_at->gt($limiteBusqueda)
                || $turnos->isNotEmpty() || $bloqueos->isNotEmpty()) {
                return null;
            }

            // Repetición de la misma activación: no renueva el plazo ni duplica filas.
            $misma = $anteriores->firstWhere('paciente_id', $candidato->paciente_id);
            if ($misma !== null) {
                return (int) $misma->turno_id === (int) $candidato->id && $misma->reservaVigenteEn($ahora)
                    && $misma->origen_inicio_at->equalTo($candidato->inicio_at)
                    && $misma->origen_fin_at->equalTo($candidato->fin_at) ? $misma : null;
            }
            if ($reservas->contains(fn (OfertaAdelanto $oferta): bool => $oferta->reservaVigenteEn($ahora))) {
                return null;
            }

            $oferta = OfertaAdelanto::query()->create([
                'intencion_evaluacion_cupo_id' => $intencionId,
                'turno_id' => $candidato->id, 'paciente_id' => $candidato->paciente_id,
                'kinesiologa_id' => $profesional->id,
                'inicio_at' => $intencion->inicio_at, 'fin_at' => $intencion->fin_at,
                'destino_fin_at' => $destino['fin_at'],
                'origen_inicio_at' => $candidato->inicio_at, 'origen_fin_at' => $candidato->fin_at,
                'estado' => OfertaAdelanto::ACTIVA, 'activada_at' => $ahora,
                'vence_at' => CarbonImmutable::instance($ahora)->utc()->addHours(2)->setTimezone(config('app.timezone')),
            ]);
            $this->auditoria->registrar(
                evento: 'oferta_adelanto_activada', modulo: 'Turnos', auditable: $oferta,
                valoresNuevos: ['intencion_id' => $intencionId, 'turno_id' => $candidato->id,
                    'vence_at' => $oferta->vence_at->toIso8601String()],
            );

            return $oferta;
        });
    }
}
