<?php

namespace App\Services\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Models\DespachoOfertaAdelanto;
use App\Models\EvaluacionAdelanto;
use App\Models\OfertaAdelanto;
use App\Models\Paciente;
use App\Models\SalidaOfertaAdelanto;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use SensitiveParameter;

final class CerrarOfertaAdelantoService
{
    public function __construct(
        private readonly TransaccionOfertaAdelantoService $transacciones,
        private readonly RelojAplicacion $reloj,
        private readonly AuditoriaService $auditoria,
    ) {}

    /** Sin traslado ni selección bajo los locks del paciente original. */
    public function cerrar(int $id, string $motivo, #[SensitiveParameter] ?array $autorizacion = null): ?SalidaOfertaAdelanto
    {
        if (! in_array($motivo, [OfertaAdelanto::RECHAZADA, OfertaAdelanto::VENCIDA, OfertaAdelanto::FALLO_ENVIO, OfertaAdelanto::ORIGINAL_MODIFICADO, OfertaAdelanto::CUPO_NO_DISPONIBLE, OfertaAdelanto::DESTINO_INCOMPATIBLE], true)) {
            throw new InvalidArgumentException('Motivo de salida no permitido.');
        }

        return $this->transacciones->ejecutar($id, function (OfertaAdelanto $oferta, Turno $turno, ?DespachoOfertaAdelanto $despacho, $profesional, Paciente $paciente) use ($id, $motivo, $autorizacion): ?SalidaOfertaAdelanto {
            $actor = null;
            if ($motivo === OfertaAdelanto::RECHAZADA) {
                $this->comprobarAutorizacion($oferta, $despacho, $autorizacion);
                $actor = $paciente->user()->lockForUpdate()->firstOrFail();
            }
            $existente = SalidaOfertaAdelanto::query()->where('oferta_id', $id)->lockForUpdate()->first();
            if ($existente !== null) {
                return $existente;
            }
            $ahora = $this->reloj->ahora();
            $resultado = $oferta->estado;
            if ($resultado === OfertaAdelanto::ACTIVA) {
                $resultado = match (true) {
                    ! $ahora->lt($oferta->vence_at) => OfertaAdelanto::VENCIDA,
                    ! $oferta->origen_inicio_at->equalTo($turno->inicio_at) || ! $oferta->origen_fin_at->equalTo($turno->fin_at)
                        || (int) $oferta->paciente_id !== (int) $turno->paciente_id
                        || (int) $oferta->kinesiologa_id !== (int) $turno->kinesiologa_id
                        || ! in_array($turno->estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true) => OfertaAdelanto::ORIGINAL_MODIFICADO,
                    $motivo === OfertaAdelanto::VENCIDA => null,
                    default => $motivo,
                };
                if ($resultado === null) {
                    return null;
                }
            }

            return $this->registrarResultadoBloqueado($oferta, $despacho, $resultado, $actor);
        });
    }

    /** Referencias autenticadas por el middleware, comprobadas otra vez bajo locks. */
    public function comprobarAutorizacion(OfertaAdelanto $oferta, ?DespachoOfertaAdelanto $despacho, #[SensitiveParameter] ?array $datos, bool $navegador = false): void
    {
        if ($datos === null || $despacho === null
            || (int) ($datos['despacho'] ?? 0) !== (int) $despacho->id
            || (int) ($datos['oferta'] ?? 0) !== (int) $oferta->id
            || (int) ($datos['paciente'] ?? 0) !== (int) $oferta->paciente_id
            || (int) $despacho->oferta_id !== (int) $oferta->id
            || (int) $despacho->paciente_id !== (int) $oferta->paciente_id
            || ! $despacho->vence_at->equalTo($oferta->vence_at)
            || (($navegador || array_key_exists('uso', $datos)) && (($datos['uso'] ?? null) !== 'adelanto-navegador-v1'
                || ! is_string($datos['csrf'] ?? null) || ! is_int($datos['exp'] ?? null)
                || $this->reloj->ahora()->getTimestamp() >= $datos['exp']))) {
            throw new AuthorizationException('La autorización no corresponde a la oferta.');
        }
    }

    /** Sólo desde la transacción canónica, después de escribir el traslado. */
    public function registrarAceptacionBloqueada(OfertaAdelanto $oferta, DespachoOfertaAdelanto $despacho, User $actor, EstadoTurnoEnum $estado): SalidaOfertaAdelanto
    {
        if ($oferta->estado !== OfertaAdelanto::ACTIVA || ! in_array($estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)) {
            throw new LogicException('La aceptación requiere una oferta activa y un estado elegible.');
        }

        return $this->registrarResultadoBloqueado($oferta, $despacho, OfertaAdelanto::ACEPTADA, $actor, $estado);
    }

    private function registrarResultadoBloqueado(OfertaAdelanto $oferta, ?DespachoOfertaAdelanto $despacho, string $resultado, ?User $actor, ?EstadoTurnoEnum $estadoAceptado = null): SalidaOfertaAdelanto
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('El resultado requiere una transacción de oferta.');
        }
        $evaluacion = EvaluacionAdelanto::query()->where('intencion_evaluacion_cupo_id', $oferta->intencion_evaluacion_cupo_id)->lockForUpdate()->firstOrFail();
        $ahora = $this->reloj->ahora();
        if ($oferta->estado === OfertaAdelanto::ACTIVA) {
            $oferta->estado = $resultado;
            $oferta->cerrada_at = $ahora;
            $oferta->saveOrFail();
        }
        $aceptada = $resultado === OfertaAdelanto::ACEPTADA;
        $salida = SalidaOfertaAdelanto::query()->create([
            'oferta_id' => $oferta->id, 'evaluacion_id' => $evaluacion->id, 'resultado' => $resultado,
            'registrada_at' => $ahora, 'continuacion' => $aceptada ? 'no_corresponde' : 'pendiente',
            'continuada_at' => $aceptada ? $ahora : null, 'estado_turno_aceptado' => $estadoAceptado?->value,
        ]);
        if ($despacho !== null && $despacho->material_cifrado !== null) {
            $despacho->material_cifrado = null;
            $despacho->saveOrFail();
        }
        $this->auditoria->registrar(
            evento: 'oferta_adelanto_salida_registrada', modulo: 'Turnos', auditable: $salida,
            valoresNuevos: ['oferta_id' => $oferta->id, 'evaluacion_id' => $evaluacion->id, 'resultado' => $resultado],
            actor: $actor,
            url: 'urn:kinesiosoft:salida-oferta', ipAddress: '', userAgent: 'Resolución de oferta',
        );

        return $salida;
    }
}
