<?php

namespace App\Services;

use App\Jobs\ProcesarExpiracionOfertaAdelantoTurnoJob;
use App\Mail\OfertaAdelantoTurnoMail;
use App\Models\OfertaAdelantoTurno;
use App\Models\Turno;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AsignacionAutomaticaDeTurnosService
{
    /**
     * Maneja la cancelación temprana de un turno:
     * - Verifica que el turno realmente está cancelado/cancelado_tarde.
     * - Chequea que no haya ya una oferta pendiente para ese hueco.
     * - Busca el primer candidato elegible (misma semana, futuro, pendiente, más tarde).
     * - Crea la oferta con token y fecha límite (TTL dinámico).
     * - Encola el mail de oferta de adelanto.
     * - Encola el Job de expiración para pasar al siguiente si no responde.
     *
     * Devuelve la Oferta creada o null si no se generó ninguna.
     */
    public function generarPrimeraOferta(Turno $turnoCancelado): ?OfertaAdelantoTurno
    {
        // 1) Asegurarnos de que realmente es un turno cancelado
        if (! in_array($turnoCancelado->estado, [
            Turno::ESTADO_CANCELADO,
            Turno::ESTADO_CANCELADO_TARDE,
        ], true)) {
            return null;
        }

        // 2) Si ya existe una oferta pendiente para este turno_ofertado, no hacemos nada
        $yaTienePendiente = OfertaAdelantoTurno::paraTurnoOfertado($turnoCancelado)
            ->pendientes()
            ->exists();

        if ($yaTienePendiente) {
            return null;
        }

        // 3) Buscar el primer candidato elegible
        $candidato = $this->buscarCandidatoPrincipal($turnoCancelado);

        if (! $candidato) {
            // No hay nadie a quien ofrecerle este hueco
            return null;
        }

        // 4) Calcular el orden en la "cola" para este turno_ofertado
        $ultimoOrden = (int) OfertaAdelantoTurno::paraTurnoOfertado($turnoCancelado)
            ->max('orden_cola');

        $orden = $ultimoOrden + 1;

        // 5) Generar token y fechas (TTL dinámico según cuántos días falten)
        $token    = Str::random(64);
        $now      = now();
        $ttlHours = $this->calcularTtlHorasParaAdelanto($turnoCancelado);

        $fechaLimite = $now->copy()->addHours($ttlHours);

        // 6) Crear la oferta de adelanto
        $oferta = OfertaAdelantoTurno::create([
            'turno_ofertado_id'          => $turnoCancelado->id_turno,
            'turno_original_paciente_id' => $candidato->id_turno,
            'turno_resultante_id'        => null,

            'profesional_id'     => $turnoCancelado->profesional_id,
            'paciente_id'        => $candidato->paciente_id,
            'paciente_perfil_id' => $candidato->paciente_perfil_id,

            'estado'                 => OfertaAdelantoTurno::ESTADO_PENDIENTE,
            'orden_cola'             => $orden,
            'oferta_token'           => $token,
            'oferta_enviada_at'      => $now,
            'fecha_limite_respuesta' => $fechaLimite,
            'respondida_at'          => null,
        ]);

        // 7) Enviar mail al paciente (en cola)
        // Nos aseguramos de tener cargada la relación Turno::paciente() -> User
        $candidato->loadMissing('paciente');

        $emailDestino = $candidato->paciente?->email;

        if ($emailDestino) {
            Mail::to($emailDestino)
                ->queue(new OfertaAdelantoTurnoMail(
                    oferta: $oferta,
                    turnoOfertado: $turnoCancelado,
                    turnoOriginal: $candidato,
                ));
        }

        // 8) Encolar job de expiración (marca sin_respuesta y pasa al siguiente)
        ProcesarExpiracionOfertaAdelantoTurnoJob::dispatch($oferta->id)
            ->delay($fechaLimite);

        return $oferta;
    }

    /**
     * Genera la oferta para el siguiente candidato en la cola, si existe.
     *
     * Se asume que la oferta anterior YA NO está en estado pendiente
     * (por ejemplo, fue marcada como sin_respuesta por el Job de expiración).
     */
    public function generarSiguienteOferta(OfertaAdelantoTurno $ofertaAnterior): ?OfertaAdelantoTurno
    {
        $ofertaAnterior->refresh();

        $turnoCancelado = $ofertaAnterior->turnoOfertado;

        if (! $turnoCancelado) {
            return null;
        }

        // Reutilizamos la lógica general: como ya no hay ofertas pendientes
        // para este turno_ofertado, generarPrimeraOferta buscará el siguiente
        // candidato elegible y creará una nueva oferta (si lo hubiera).
        return $this->generarPrimeraOferta($turnoCancelado);
    }

    /**
     * Busca el primer turno candidato para ADELANTAR:
     * - Mismo profesional.
     * - Misma semana que el turno cancelado.
     * - Turnos futuros.
     * - Estado pendiente.
     * - No sea el mismo turno cancelado.
     * - No haya sido usado ya como candidato en ofertas anteriores para ese hueco.
     * - IMPORTANTE: el turno original del paciente debe ser DESPUÉS del hueco libre.
     */
    protected function buscarCandidatoPrincipal(Turno $turnoCancelado): ?Turno
    {
        // Fecha del turno cancelado (hueco)
        $fechaBase = $turnoCancelado->fecha instanceof Carbon
            ? $turnoCancelado->fecha->copy()
            : Carbon::parse($turnoCancelado->fecha);

        // Límites de la semana (lunes - domingo por defecto)
        $inicioSemana = $fechaBase->copy()->startOfWeek(); // lunes
        $finSemana    = $fechaBase->copy()->endOfWeek();   // domingo

        // Fecha y hora del hueco (turno cancelado)
        $fechaHueco = $fechaBase->toDateString();
        $horaHueco  = $turnoCancelado->getRawOriginal('hora_desde') ?? (string) $turnoCancelado->hora_desde;

        // Turnos ya utilizados como candidatos para este turno_ofertado
        $turnosYaUsados = OfertaAdelantoTurno::paraTurnoOfertado($turnoCancelado)
            ->pluck('turno_original_paciente_id')
            ->filter()
            ->all();

        $query = Turno::query()
            ->deProfesional($turnoCancelado->profesional_id)
            ->entreFechas($inicioSemana, $finSemana)
            ->futuros()
            ->where('estado', Turno::ESTADO_PENDIENTE)
            ->where('id_turno', '!=', $turnoCancelado->id_turno);

        // 🔐 Solo candidatos cuyo turno original sea DESPUÉS del hueco
        $query->where(function ($qq) use ($fechaHueco, $horaHueco) {
            $qq->whereDate('fecha', '>', $fechaHueco)
                ->orWhere(function ($qq2) use ($fechaHueco, $horaHueco) {
                    $qq2->whereDate('fecha', $fechaHueco)
                        ->where('hora_desde', '>', $horaHueco);
                });
        });

        if (! empty($turnosYaUsados)) {
            $query->whereNotIn('id_turno', $turnosYaUsados);
        }

        // Opcional: si quisieras limitar al mismo consultorio:
        // $query->where('id_consultorio', $turnoCancelado->id_consultorio);

        return $query
            ->orderBy('fecha')
            ->orderBy('hora_desde')
            ->first();
    }

    /**
     * Procesa la aceptación de una oferta:
     * - Verifica estado pendiente y que no esté vencida.
     * - Reutiliza el turno cancelado (turno_ofertado) asignándolo al paciente original.
     * - Cancela el turno original del paciente.
     * - Marca la oferta como aceptada.
     */
    public function aceptarOferta(OfertaAdelantoTurno $oferta): OfertaAdelantoTurno
    {
        return DB::transaction(function () use ($oferta) {
            $oferta->refresh();

            // Si ya no está pendiente, no hacemos nada
            if ($oferta->estado !== OfertaAdelantoTurno::ESTADO_PENDIENTE) {
                return $oferta;
            }

            // Si está vencida, la marcamos como expirada
            if (
                $oferta->fecha_limite_respuesta &&
                now()->greaterThan($oferta->fecha_limite_respuesta)
            ) {
                $oferta->update([
                    'estado'        => OfertaAdelantoTurno::ESTADO_EXPIRADA,
                    'respondida_at' => now(),
                ]);

                return $oferta;
            }

            $turnoOfertado = Turno::lockForUpdate()->findOrFail($oferta->turno_ofertado_id);
            $turnoOriginal = Turno::lockForUpdate()->findOrFail($oferta->turno_original_paciente_id);

            // Sanidad mínima: esperamos hueco cancelado + original pendiente
            if (
                ! in_array($turnoOfertado->estado, [
                    Turno::ESTADO_CANCELADO,
                    Turno::ESTADO_CANCELADO_TARDE,
                ], true)
                || $turnoOriginal->estado !== Turno::ESTADO_PENDIENTE
            ) {
                $oferta->update([
                    'estado'        => OfertaAdelantoTurno::ESTADO_CANCELADA_SISTEMA,
                    'respondida_at' => now(),
                ]);

                return $oferta;
            }

            // 1) Cancelar el turno original del paciente
            $turnoOriginal->update([
                'estado' => Turno::ESTADO_CANCELADO,
                'motivo' => 'Reemplazado por adelanto de turno automático',
            ]);

            // 2) Reasignar el hueco libre al paciente original, y dejarlo confirmado
            $turnoOfertado->update([
                'paciente_id'            => $turnoOriginal->paciente_id,
                'paciente_perfil_id'     => $turnoOriginal->paciente_perfil_id,
                'estado'                 => Turno::ESTADO_CONFIRMADO, // confirmamos directo
                'motivo'                 => 'Turno generado por adelanto automático',
                'es_adelanto_automatico' => true,                     // clave para Agenda diaria
                'reminder_status'        => 'advanced',                // opcional, pero claro
            ]);

            // 3) Actualizar la oferta
            $oferta->update([
                'estado'              => OfertaAdelantoTurno::ESTADO_ACEPTADA,
                'respondida_at'       => now(),
                'turno_resultante_id' => $turnoOfertado->id_turno,
            ]);

            return $oferta;
        });
    }

    /**
     * Procesa el rechazo de una oferta:
     * - Verifica estado pendiente y que no esté vencida.
     * - No toca los turnos, solo marca la oferta.
     * - Más adelante se podría encadenar al siguiente candidato (opcional).
     */
    public function rechazarOferta(OfertaAdelantoTurno $oferta): OfertaAdelantoTurno
    {
        return DB::transaction(function () use ($oferta) {
            $oferta->refresh();

            if ($oferta->estado !== OfertaAdelantoTurno::ESTADO_PENDIENTE) {
                return $oferta;
            }

            if (
                $oferta->fecha_limite_respuesta &&
                now()->greaterThan($oferta->fecha_limite_respuesta)
            ) {
                $oferta->update([
                    'estado'        => OfertaAdelantoTurno::ESTADO_EXPIRADA,
                    'respondida_at' => now(),
                ]);

                return $oferta;
            }

            $oferta->update([
                'estado'        => OfertaAdelantoTurno::ESTADO_RECHAZADA,
                'respondida_at' => now(),
            ]);

            // Si quisieras encadenar manualmente al siguiente candidato
            // cuando el paciente RECHAZA explícitamente, podrías hacer:
            // $this->generarSiguienteOferta($oferta);

            return $oferta;
        });
    }

    /**
     * Calcula el TTL (en horas) para la oferta de adelanto según
     * cuántos días falten para el turno ofrecido:
     *
     * - Si es para MAÑANA → 2 horas (config: adelanto.ttl_next_day_hours)
     * - Si es para dentro de ≥ 2 días → 12 horas (config: adelanto.ttl_many_days_hours)
     *
     * En la práctica no deberías tener casos de "hoy", porque la cancelación
     * temprana exige >= 24h, pero por seguridad devolvemos siempre algo válido.
     */
    protected function calcularTtlHorasParaAdelanto(Turno $turnoCancelado): int
    {
        // Normalizamos la fecha del turno cancelado (hueco)
        $fechaTurno = $turnoCancelado->fecha instanceof Carbon
            ? $turnoCancelado->fecha->copy()->startOfDay()
            : Carbon::parse($turnoCancelado->fecha)->startOfDay();

        $hoy = now()->startOfDay();

        // Si por algún motivo el turno está en el pasado o es hoy, tomamos 0 días
        if ($fechaTurno->lessThanOrEqualTo($hoy)) {
            $diasHastaTurno = 0;
        } else {
            $diasHastaTurno = $hoy->diffInDays($fechaTurno);
        }

        if ($diasHastaTurno === 1) {
            // Adelanto para MAÑANA → 2h (o lo que diga el config)
            return (int) config('turnos.adelanto.ttl_next_day_hours', 2);
        }

        if ($diasHastaTurno >= 2) {
            // Adelanto con varios días de anticipación → 12h (o config)
            return (int) config('turnos.adelanto.ttl_many_days_hours', 12);
        }

        // Fallback para cualquier caso raro: usamos el de "mañana"
        return (int) config('turnos.adelanto.ttl_next_day_hours', 2);
    }
}
