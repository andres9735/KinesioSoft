<?php

namespace App\Actions\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Models\DespachoOfertaAdelanto;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\EvaluacionAdelanto;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Kinesiologa;
use App\Models\OfertaAdelanto;
use App\Models\Paciente;
use App\Models\SalidaOfertaAdelanto;
use App\Models\Turno;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use App\Services\Turnos\CerrarOfertaAdelantoService;
use App\Services\Turnos\PrepararConfirmacionAdelantoService;
use App\Services\Turnos\TransaccionOfertaAdelantoService;
use App\Services\Turnos\ValidarDestinoAdelantoService;
use LogicException;
use SensitiveParameter;

final class AceptarOfertaAdelantoAction
{
    public function __construct(
        private readonly TransaccionOfertaAdelantoService $transacciones,
        private readonly CerrarOfertaAdelantoService $cierres,
        private readonly ValidarDestinoAdelantoService $validador,
        private readonly AgendaQueryService $agenda,
        private readonly RelojAplicacion $reloj,
        private readonly AuditoriaService $auditoria,
        private readonly PrepararConfirmacionAdelantoService $confirmacion,
    ) {}

    /** La autorización proviene de la cookie autenticada del canal, nunca del formulario. */
    public function ejecutar(int $id, #[SensitiveParameter] array $autorizacion): SalidaOfertaAdelanto
    {
        return $this->transacciones->ejecutar($id, function (OfertaAdelanto $oferta, Turno $turno, ?DespachoOfertaAdelanto $despacho, Kinesiologa $profesional, Paciente $paciente) use ($autorizacion): SalidaOfertaAdelanto {
            $this->cierres->comprobarAutorizacion($oferta, $despacho, $autorizacion, navegador: true);
            $existente = SalidaOfertaAdelanto::query()->where('oferta_id', $oferta->id)->lockForUpdate()->first();
            if ($existente !== null) {
                if ($existente->resultado === OfertaAdelanto::ACEPTADA) {
                    $this->confirmacion->preparar($existente->id);
                }

                return $existente;
            }
            $actor = $paciente->user()->lockForUpdate()->firstOrFail();
            if ($oferta->estado !== OfertaAdelanto::ACTIVA || ! $this->reloj->ahora()->lt($oferta->vence_at)) {
                return $this->cierres->cerrar($oferta->id, OfertaAdelanto::VENCIDA);
            }
            if (! $oferta->origen_inicio_at->equalTo($turno->inicio_at) || ! $oferta->origen_fin_at->equalTo($turno->fin_at)
                || (int) $turno->kinesiologa_id !== (int) $oferta->kinesiologa_id
                || (int) $turno->paciente_id !== (int) $oferta->paciente_id
                || ! in_array($turno->estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)) {
                return $this->cierres->cerrar($oferta->id, OfertaAdelanto::ORIGINAL_MODIFICADO);
            }

            $intencion = IntencionEvaluacionCupo::query()->lockForUpdate()->findOrFail($oferta->intencion_evaluacion_cupo_id);
            EvaluacionAdelanto::query()->where('intencion_evaluacion_cupo_id', $intencion->id)->lockForUpdate()->firstOrFail();
            $turnos = $this->agenda->turnosAfectadosPorIntervalo($profesional, $oferta->inicio_at, $oferta->fin_at)
                ->whereKeyNot($turno->id)->lockForUpdate()->get();
            $reservas = $this->agenda->reservasSuperpuestasCon($profesional, $oferta->inicio_at, $oferta->fin_at)
                ->whereKeyNot($oferta->id)->lockForUpdate()->get();
            try {
                $destino = $this->validador->validar($turno, $profesional, $oferta->inicio_at, $oferta->fin_at, bloquear: true);
            } catch (LogicException) {
                // CA-R06: una restricción que no puede verificarse nunca habilita un traslado.
                $destino = null;
            }
            $revisionAnterior = EnlaceAsistenciaTurno::versionActualDe($turno);
            $this->cierres->comprobarAutorizacion($oferta, $despacho, $autorizacion, navegador: true);
            $ahora = $this->reloj->ahora();
            if (! $ahora->lt($oferta->vence_at)) {
                return $this->cierres->cerrar($oferta->id, OfertaAdelanto::VENCIDA);
            }
            if (! $oferta->reservaVigenteEn($ahora) || (int) $intencion->kinesiologa_id !== (int) $profesional->id
                || ! $intencion->inicio_at->equalTo($oferta->inicio_at) || ! $intencion->fin_at->equalTo($oferta->fin_at)
                || $turnos->isNotEmpty() || $reservas->contains(fn (OfertaAdelanto $otra): bool => $otra->reservaVigenteEn($ahora))) {
                return $this->cierres->cerrar($oferta->id, OfertaAdelanto::CUPO_NO_DISPONIBLE);
            }
            if ($destino === null || ! $destino['fin_at']->equalTo($oferta->destino_fin_at)) {
                return $this->cierres->cerrar($oferta->id, OfertaAdelanto::DESTINO_INCOMPATIBLE);
            }

            $estado = $turno->estado;
            $anterior = ['inicio_at' => $turno->inicio_at->toIso8601String(), 'fin_at' => $turno->fin_at->toIso8601String(), 'estado' => $estado->value];
            $turno->inicio_at = $destino['inicio_at'];
            $turno->fin_at = $destino['fin_at'];
            $turno->saveOrFail();
            // Los triggers existentes invalidan la programación anterior en el mismo commit.
            if (EnlaceAsistenciaTurno::versionActualDe($turno) <= $revisionAnterior) {
                throw new LogicException('No se pudo invalidar la autorización de asistencia anterior.');
            }
            $salida = $this->cierres->registrarAceptacionBloqueada($oferta, $despacho, $actor, $estado);
            $this->confirmacion->preparar($salida->id);
            $this->auditoria->registrar(
                evento: 'turno_adelantado_por_oferta', modulo: 'Turnos', auditable: $turno,
                valoresAnteriores: $anterior,
                valoresNuevos: ['oferta_id' => $oferta->id, 'inicio_at' => $turno->inicio_at->toIso8601String(),
                    'fin_at' => $turno->fin_at->toIso8601String(), 'estado' => $estado->value],
                actor: $actor, url: 'urn:kinesiosoft:aceptar-adelanto', ipAddress: '', userAgent: 'Resolución de oferta',
            );

            return $salida;
        });
    }
}
