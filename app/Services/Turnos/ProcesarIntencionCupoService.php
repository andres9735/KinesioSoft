<?php

namespace App\Services\Turnos;

use App\Actions\Turnos\ActivarOfertaAdelantoAction;
use App\Models\EvaluacionAdelanto;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Kinesiologa;
use App\Models\OfertaAdelanto;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class ProcesarIntencionCupoService
{
    public function __construct(
        private readonly RegistrarIntencionEvaluacionCupoService $intenciones,
        private readonly SeleccionarCandidatoAdelantoService $selector,
        private readonly ActivarOfertaAdelantoAction $activar,
        private readonly PrepararDespachoOfertaAdelantoService $despachos,
        private readonly RelojAplicacion $reloj,
        private readonly AuditoriaService $auditoria,
    ) {}

    public function procesar(int $id): EvaluacionAdelanto
    {
        if (! app()->runningInConsole() || Auth::user() !== null) {
            throw new LogicException('El consumidor requiere un proceso interno sin sesión.');
        }
        // Sólo localiza el mutex; ninguna lectura preliminar acredita elegibilidad.
        $referencia = IntencionEvaluacionCupo::query()->useWritePdo()->findOrFail($id);

        return DB::transaction(function () use ($id, $referencia): EvaluacionAdelanto {
            $profesional = Kinesiologa::query()->lockForUpdate()->findOrFail($referencia->kinesiologa_id);
            $intencion = IntencionEvaluacionCupo::query()->lockForUpdate()->findOrFail($id);
            if ((int) $intencion->kinesiologa_id !== (int) $profesional->id) {
                throw new LogicException('La intención cambió su contexto de agenda.');
            }
            $existente = EvaluacionAdelanto::query()->where('intencion_evaluacion_cupo_id', $id)->lockForUpdate()->first();
            if ($existente !== null) {
                return $existente;
            }
            // Reutiliza la validación íntegra de la auditoría y la intención existentes:
            // doce horas desde ocurrido_at/liberado_at, nunca desde el reloj del worker.
            $elegible = true;
            try {
                $this->intenciones->registrar($intencion->cancelacion()->firstOrFail());
            } catch (InvalidArgumentException) {
                $elegible = false;
            }
            $oferta = null;
            $resultado = 'cancelacion_no_elegible';
            if ($elegible) {
                // Recupera también ofertas consolidadas antes de integrar este consumidor.
                $oferta = OfertaAdelanto::query()->where('intencion_evaluacion_cupo_id', $id)->orderBy('id')->lockForUpdate()->first();
                if ($oferta === null) {
                    $excluidos = OfertaAdelanto::query()->where('intencion_evaluacion_cupo_id', $id)->pluck('paciente_id')->map(fn ($valor): int => (int) $valor)->all();
                    $candidato = $this->selector->seleccionar($profesional, $intencion->inicio_at, $intencion->fin_at, $excluidos);
                    $resultado = 'sin_candidatos';
                    if ($candidato !== null) {
                        // La Action mantiene K -> P -> T, relee y revalida bajo el mismo K.
                        $oferta = $this->activar->ejecutar($id, $candidato->id);
                        $resultado = 'no_activable';
                    }
                }
                if ($oferta !== null) {
                    $this->despachos->preparar($oferta);
                    $resultado = 'oferta_preparada';
                }
            }
            $evaluacion = EvaluacionAdelanto::query()->create([
                'intencion_evaluacion_cupo_id' => $id, 'oferta_id' => $oferta?->id,
                'resultado' => $resultado, 'procesada_at' => $this->reloj->ahora(),
            ]);
            $intencion->procesado_at = $evaluacion->procesada_at;
            $intencion->saveOrFail();
            $this->auditoria->registrar(
                evento: 'cupo_primera_evaluacion_procesada', modulo: 'Turnos', auditable: $evaluacion,
                valoresNuevos: ['intencion_id' => $id, 'resultado' => $resultado, 'oferta_id' => $oferta?->id],
                url: 'urn:kinesiosoft:evaluacion-cupo', ipAddress: '', userAgent: 'Proceso interno',
            );

            return $evaluacion;
        }, attempts: 1);
    }
}
