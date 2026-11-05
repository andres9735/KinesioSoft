<?php

namespace App\Services\Agenda;

use App\Enums\ResultadoGeneracionAgendaEnum;
use App\Models\EjecucionAgendaDiaria;
use App\Models\JornadaGeneracionAgenda;
use App\Models\PreparacionAgendaDiaria;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class GenerarAgendaDiariaService
{
    // Lease técnico renovable; no es la cota funcional de 60 s ni un timeout de atención.
    // Si vence durante una lectura, el trabajador pierde el derecho a registrar resultados.
    public const RECLAMO_SEGUNDOS = 300;

    public function __construct(
        private PrepararJornadaAgendaService $preparador,
        private RegistroGeneracionAgendaService $registro,
        private RelojAplicacion $reloj,
    ) {}

    public function ejecutar(?CarbonInterface $fecha = null): EjecucionAgendaDiaria
    {
        $jornada = CarbonImmutable::createFromFormat('!Y-m-d', ($fecha ?? $this->reloj->hoy())->toDateString(), config('app.timezone'));
        [$control, $ejecucion, $adquirido] = $this->reclamar($jornada);
        if (! $adquirido) {
            return $ejecucion;
        }
        try {
            $profesionales = $this->preparador->profesionalesHabilitadas();
            // Una sola lectura de resultados previos para todas las profesionales.
            $previas = PreparacionAgendaDiaria::query()
                ->where('resultado', ResultadoGeneracionAgendaEnum::COMPLETADA)
                ->whereIn('kinesiologa_id', $profesionales->modelKeys())
                ->whereHas('ejecucion', fn ($q) => $q->where('fecha_local', $jornada->toDateString())->where('zona_horaria', $control->zona_horaria))
                ->orderByDesc('id')->get()->unique('kinesiologa_id')->keyBy('kinesiologa_id');
        } catch (Throwable) {
            return $this->conReclamo($control, $ejecucion, fn () => $this->registro->fallarSeleccion($ejecucion));
        }
        foreach ($profesionales as $profesional) {
            if ($previas->has($profesional->id)) {
                $this->conReclamo($control, $ejecucion, fn () => $this->registro->reutilizarAgenda($ejecucion, $previas[$profesional->id]));

                continue;
            }
            $agenda = $this->conReclamo($control, $ejecucion, fn () => $this->registro->iniciarAgenda($ejecucion, $profesional));
            try {
                $datos = $this->preparador->preparar($profesional, $jornada);
            } catch (Throwable) {
                $this->conReclamo($control, $ejecucion, fn () => $this->registro->fallarAgenda($agenda));

                continue;
            }
            $this->conReclamo($control, $ejecucion, fn () => $this->registro->completarAgenda($agenda, $datos));
        }

        return $this->conReclamo($control, $ejecucion, fn () => $this->registro->finalizar($ejecucion));
    }

    /** @return array{JornadaGeneracionAgenda, EjecucionAgendaDiaria, bool} */
    private function reclamar(CarbonImmutable $fecha): array
    {
        return DB::transaction(function () use ($fecha): array {
            $identidad = ['fecha_local' => $fecha->toDateString(), 'zona_horaria' => (string) config('app.timezone')];
            try {
                $referencia = JornadaGeneracionAgenda::query()->firstOrCreate($identidad);
            } catch (UniqueConstraintViolationException $error) {
                // En REPEATABLE-READ, la recuperación genérica puede no ver la fila
                // que ganó el INSERT. Resolver únicamente esta colisión con lectura actual.
                if ((int) ($error->errorInfo[1] ?? 0) !== 1062
                    || ! str_contains((string) ($error->errorInfo[2] ?? ''), 'jornada_agenda_fecha_zona_unique')) {
                    throw $error;
                }
                $referencia = JornadaGeneracionAgenda::query()->where($identidad)->lockForUpdate()->first();
                if ($referencia === null) {
                    throw $error;
                }
            }
            // Lectura actual tras resolver UNIQUE: no depender de un snapshot previo en InnoDB.
            $control = JornadaGeneracionAgenda::query()->whereKey($referencia->id)->lockForUpdate()->firstOrFail();
            $actual = $control->ejecucionActual()->lockForUpdate()->first();
            $ahora = $this->reloj->ahora();
            if ($actual !== null) {
                if ($actual->resultado === ResultadoGeneracionAgendaEnum::COMPLETADA) {
                    return [$control, $actual, false];
                }
                if ($actual->resultado === ResultadoGeneracionAgendaEnum::EN_CURSO) {
                    if ($this->vigente($control, $ahora)) {
                        return [$control, $actual, false];
                    }
                    $this->registro->interrumpir($actual);
                }
            }
            $ejecucion = $this->registro->iniciar((string) Str::uuid(), $fecha);
            $control->forceFill([
                'ejecucion_actual_id' => $ejecucion->id,
                'reclamo_hasta' => $ahora->addSeconds(self::RECLAMO_SEGUNDOS)->format('Y-m-d H:i:s.u'),
            ])->saveOrFail();

            return [$control, $ejecucion, true];
        });
    }

    /**
     * Fencing: cada escritura se valida bajo lock del control y en la misma transacción.
     * Orden exclusivo de bitácora: jornada → ejecución → preparación. No bloquea Turno.
     * La lectura de citas ocurre fuera de estos locks y no hay transacciones largas.
     */
    private function conReclamo(JornadaGeneracionAgenda $control, EjecucionAgendaDiaria $ejecucion, callable $operacion): mixed
    {
        return DB::transaction(function () use ($control, $ejecucion, $operacion): mixed {
            $actual = JornadaGeneracionAgenda::query()->whereKey($control->id)->lockForUpdate()->firstOrFail();
            $ahora = $this->reloj->ahora()->setTimezone($actual->zona_horaria);
            if ((int) $actual->ejecucion_actual_id !== (int) $ejecucion->id || ! $this->vigente($actual, $ahora)) {
                throw new ReclamoGeneracionPerdidoException;
            }
            $actual->forceFill(['reclamo_hasta' => $ahora->addSeconds(self::RECLAMO_SEGUNDOS)->format('Y-m-d H:i:s.u')])->saveOrFail();

            return $operacion();
        });
    }

    private function vigente(JornadaGeneracionAgenda $control, CarbonImmutable $ahora): bool
    {
        return $control->reclamo_hasta !== null
            && $ahora->lt(CarbonImmutable::parse($control->reclamo_hasta, $control->zona_horaria));
    }
}
