<?php

namespace App\Services\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Models\DespachoOfertaAdelanto;
use App\Models\OfertaAdelanto;
use App\Models\SalidaOfertaAdelanto;
use App\Models\Turno;
use App\Services\Agenda\RelojAplicacion;
use SensitiveParameter;

final class ConsultarOfertaAdelantoService
{
    public function __construct(private readonly RelojAplicacion $reloj) {}

    public function porToken(#[SensitiveParameter] mixed $token): ?array
    {
        if (! is_string($token) || ! preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            return null;
        }
        $despacho = DespachoOfertaAdelanto::query()->useWritePdo()->where('token_hash', hash('sha256', $token))->first();

        return $despacho === null ? null : $this->porReferencia([
            'despacho' => (int) $despacho->id, 'oferta' => (int) $despacho->oferta_id, 'paciente' => (int) $despacho->paciente_id,
        ]);
    }

    /** Sólo para referencias previamente autenticadas por la cookie de este propósito. */
    public function porReferencia(array $datos): ?array
    {
        $despacho = DespachoOfertaAdelanto::query()->useWritePdo()->find($datos['despacho'] ?? 0);
        $oferta = OfertaAdelanto::query()->useWritePdo()->find($datos['oferta'] ?? 0);
        if ($despacho === null || $oferta === null || (int) $despacho->oferta_id !== (int) $oferta->id
            || (int) $despacho->paciente_id !== (int) $oferta->paciente_id
            || (int) ($datos['paciente'] ?? 0) !== (int) $oferta->paciente_id || ! $despacho->vence_at->equalTo($oferta->vence_at)) {
            return null;
        }
        $turno = Turno::query()->useWritePdo()->find($oferta->turno_id);
        if ($turno === null) {
            return null;
        }
        $estado = $oferta->estado;
        if ($estado === OfertaAdelanto::ACTIVA) {
            $estado = match (true) {
                ! $this->reloj->ahora()->lt($oferta->vence_at) => OfertaAdelanto::VENCIDA,
                ! $oferta->origen_inicio_at->equalTo($turno->inicio_at) || ! $oferta->origen_fin_at->equalTo($turno->fin_at)
                    || (int) $turno->paciente_id !== (int) $oferta->paciente_id
                    || (int) $turno->kinesiologa_id !== (int) $oferta->kinesiologa_id
                    || ! in_array($turno->estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true) => OfertaAdelanto::ORIGINAL_MODIFICADO,
                default => OfertaAdelanto::ACTIVA,
            };
        }
        $resultado = ['despacho' => (int) $despacho->id, 'oferta' => (int) $oferta->id,
            'paciente' => (int) $oferta->paciente_id, 'estado' => $estado];
        if ($estado === OfertaAdelanto::ACEPTADA) {
            $salida = SalidaOfertaAdelanto::query()->useWritePdo()->where('oferta_id', $oferta->id)->first();

            return $salida === null ? null : $resultado + $this->resultadoDe($salida);
        }
        if ($estado === OfertaAdelanto::ACTIVA) {
            $profesional = $turno->kinesiologa->user;
            $resultado += [
                'original' => $oferta->origen_inicio_at->format('d/m/Y H:i:s').'–'.$oferta->origen_fin_at->format('H:i:s'),
                'ofrecido' => $oferta->inicio_at->format('d/m/Y H:i:s').'–'.$oferta->destino_fin_at->format('H:i:s'),
                'profesional' => trim($profesional->name.' '.$profesional->apellido),
                'vencimiento' => $oferta->vence_at->format('d/m/Y H:i:s.u P'), 'zona' => config('app.timezone'),
            ];
        }

        return $resultado;
    }

    /** Resultado histórico mínimo: no inventa una confirmación de asistencia. */
    public function resultadoDe(SalidaOfertaAdelanto $salida): array
    {
        $resultado = ['estado' => $salida->resultado];
        if ($salida->resultado === OfertaAdelanto::ACEPTADA) {
            $oferta = OfertaAdelanto::query()->useWritePdo()->findOrFail($salida->oferta_id);
            $resultado += [
                'horario_nuevo' => $oferta->inicio_at->format('d/m/Y H:i:s').'–'.$oferta->destino_fin_at->format('H:i:s'),
                'estado_turno' => $salida->estado_turno_aceptado === 'PENDIENTE' ? 'Pendiente' : 'Confirmado',
                'estado_codigo' => $salida->estado_turno_aceptado, 'zona' => config('app.timezone'),
            ];
        }

        return $resultado;
    }
}
