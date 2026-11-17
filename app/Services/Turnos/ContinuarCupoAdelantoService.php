<?php

namespace App\Services\Turnos;

use App\Actions\Turnos\ActivarOfertaAdelantoAction;
use App\Models\EvaluacionAdelanto;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Kinesiologa;
use App\Models\OfertaAdelanto;
use App\Models\SalidaOfertaAdelanto;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class ContinuarCupoAdelantoService
{
    public function __construct(
        private readonly SeleccionarCandidatoAdelantoService $selector,
        private readonly ActivarOfertaAdelantoAction $activar,
        private readonly PrepararDespachoOfertaAdelantoService $despachos,
        private readonly RelojAplicacion $reloj,
        private readonly AuditoriaService $auditoria,
    ) {}

    public function continuar(int $id): SalidaOfertaAdelanto
    {
        $referencia = SalidaOfertaAdelanto::query()->useWritePdo()->findOrFail($id);
        $origen = OfertaAdelanto::query()->useWritePdo()->findOrFail($referencia->oferta_id);

        return DB::transaction(function () use ($id, $origen): SalidaOfertaAdelanto {
            // Nueva transacción: no se conserva el lock del paciente de la oferta anterior.
            $profesional = Kinesiologa::query()->lockForUpdate()->findOrFail($origen->kinesiologa_id);
            $intencion = IntencionEvaluacionCupo::query()->lockForUpdate()->findOrFail($origen->intencion_evaluacion_cupo_id);
            $evaluacion = EvaluacionAdelanto::query()->where('intencion_evaluacion_cupo_id', $intencion->id)->lockForUpdate()->firstOrFail();
            $salida = SalidaOfertaAdelanto::query()->where('evaluacion_id', $evaluacion->id)->lockForUpdate()->findOrFail($id);
            if ($salida->continuacion !== 'pendiente') {
                return $salida;
            }
            $siguiente = OfertaAdelanto::query()->where('intencion_evaluacion_cupo_id', $intencion->id)
                ->where('estado', OfertaAdelanto::ACTIVA)->orderBy('id')->lockForUpdate()->get()
                ->first(fn (OfertaAdelanto $item): bool => $item->reservaVigenteEn($this->reloj->ahora()));
            $resultado = 'oferta_preparada';
            if ($siguiente === null) {
                $resultado = 'plazo_agotado';
                if ($intencion->inicio_at->utc()->gte($this->reloj->ahora()->utc()->addHours(4))) {
                    $excluidos = OfertaAdelanto::query()->where('intencion_evaluacion_cupo_id', $intencion->id)->pluck('paciente_id')->map(fn ($valor): int => (int) $valor)->all();
                    $candidato = $this->selector->seleccionar($profesional, $intencion->inicio_at, $intencion->fin_at, $excluidos);
                    $resultado = 'sin_candidatos';
                    if ($candidato !== null) {
                        $siguiente = $this->activar->ejecutar($intencion->id, $candidato->id);
                        $resultado = $siguiente === null ? 'no_activable' : 'oferta_preparada';
                    }
                }
            }
            if ($siguiente !== null) {
                $this->despachos->preparar($siguiente);
            }
            $salida->continuacion = $resultado;
            $salida->oferta_siguiente_id = $siguiente?->id;
            $salida->continuada_at = $this->reloj->ahora();
            $salida->saveOrFail();
            $this->auditoria->registrar(
                evento: 'cupo_adelanto_continuado', modulo: 'Turnos', auditable: $salida,
                valoresNuevos: ['evaluacion_id' => $evaluacion->id, 'resultado' => $resultado, 'oferta_siguiente_id' => $siguiente?->id],
                url: 'urn:kinesiosoft:continuacion-cupo', ipAddress: '', userAgent: 'Proceso interno',
            );

            return $salida;
        }, attempts: 1);
    }
}
