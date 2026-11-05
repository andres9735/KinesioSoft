<?php

namespace App\Services\Agenda;

use App\Enums\IncidenciaGeneracionAgendaEnum;
use App\Enums\ResultadoGeneracionAgendaEnum;
use App\Models\EjecucionAgendaDiaria;
use App\Models\Kinesiologa;
use App\Models\PreparacionAgendaDiaria;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Bitácora interna GB-06: ejecución → resultados profesionales, sin snapshots de citas.
 * El control de jornada y del reclamo pertenece a GenerarAgendaDiariaService.
 * El consumidor inicia el registro antes de preparar y registra sólo resultados conocidos.
 * Una interrupción deja EN_CURSO y cantidades NULL, nunca una agenda vacía ficticia.
 */
final class RegistroGeneracionAgendaService
{
    public function __construct(private RelojAplicacion $reloj) {}

    public function iniciar(string $claveEjecucion, ?CarbonInterface $fecha = null): EjecucionAgendaDiaria
    {
        if (! Str::isUuid($claveEjecucion)) {
            throw new InvalidArgumentException('La ejecución requiere una UUID válida.');
        }
        $zona = (string) config('app.timezone');
        $jornada = CarbonImmutable::createFromFormat('!Y-m-d', ($fecha ?? $this->reloj->hoy())->toDateString(), $zona);
        $ejecucion = new EjecucionAgendaDiaria;
        $ejecucion->forceFill([
            'clave_ejecucion' => strtolower($claveEjecucion), 'fecha_local' => $jornada->toDateString(),
            'zona_horaria' => $zona, 'resultado' => ResultadoGeneracionAgendaEnum::EN_CURSO,
            'iniciado_at' => $this->reloj->ahora(),
        ])->saveOrFail();

        return $ejecucion;
    }

    public function iniciarAgenda(EjecucionAgendaDiaria $ejecucion, Kinesiologa $kinesiologa): PreparacionAgendaDiaria
    {
        return DB::transaction(function () use ($ejecucion, $kinesiologa): PreparacionAgendaDiaria {
            $actual = $this->ejecucionAbierta($ejecucion);
            if (! $kinesiologa->exists || (int) $kinesiologa->getKey() <= 0) {
                throw new InvalidArgumentException('La profesional debe estar persistida.');
            }
            $preparacion = new PreparacionAgendaDiaria;
            $preparacion->forceFill([
                'ejecucion_id' => $actual->id, 'kinesiologa_id' => $kinesiologa->getKey(),
                'resultado' => ResultadoGeneracionAgendaEnum::EN_CURSO,
                'iniciado_at' => $this->momento($actual),
            ])->saveOrFail();

            return $preparacion;
        });
    }

    public function completarAgenda(PreparacionAgendaDiaria $preparacion, JornadaAgendaPreparada $jornada): PreparacionAgendaDiaria
    {
        return $this->cerrarAgenda($preparacion, function (PreparacionAgendaDiaria $actual, EjecucionAgendaDiaria $ejecucion) use ($jornada): array {
            if ($jornada->kinesiologaId !== (int) $actual->kinesiologa_id
                || $jornada->fechaLocal->toDateString() !== $ejecucion->fecha_local->toDateString()
                || $jornada->zonaHoraria !== $ejecucion->zona_horaria) {
                throw new InvalidArgumentException('La jornada preparada no corresponde al ámbito de la ejecución.');
            }
            $cantidades = $jornada->cantidades();

            return [
                'resultado' => ResultadoGeneracionAgendaEnum::COMPLETADA,
                'total_turnos' => $cantidades['total'], 'turnos_operativos' => $cantidades['operativos'],
                'turnos_en_atencion' => $cantidades['en_atencion'], 'otros_turnos' => $cantidades['otros'],
            ];
        });
    }

    public function fallarAgenda(
        PreparacionAgendaDiaria $preparacion,
        IncidenciaGeneracionAgendaEnum $incidencia = IncidenciaGeneracionAgendaEnum::PREPARACION_JORNADA,
    ): PreparacionAgendaDiaria {
        return $this->cerrarAgenda($preparacion, fn (): array => [
            'resultado' => ResultadoGeneracionAgendaEnum::FALLIDA,
            'codigo_incidencia' => $incidencia,
        ]);
    }

    /** Resultado de otro intento de la misma jornada: referencia y cantidades, sin nuevas lecturas de citas. */
    public function reutilizarAgenda(EjecucionAgendaDiaria $ejecucion, PreparacionAgendaDiaria $anterior): PreparacionAgendaDiaria
    {
        return DB::transaction(function () use ($ejecucion, $anterior): PreparacionAgendaDiaria {
            $actual = $this->ejecucionAbierta($ejecucion);
            $origen = PreparacionAgendaDiaria::query()->with('ejecucion')->findOrFail($anterior->getKey());
            if ($origen->resultado !== ResultadoGeneracionAgendaEnum::COMPLETADA
                || $origen->ejecucion->fecha_local->toDateString() !== $actual->fecha_local->toDateString()
                || $origen->ejecucion->zona_horaria !== $actual->zona_horaria
                || (int) $origen->ejecucion_id === (int) $actual->id) {
                throw new InvalidArgumentException('No corresponde reutilizar ese resultado para esta ejecución.');
            }
            $momento = $this->momento($actual);
            $preparacion = new PreparacionAgendaDiaria;
            $preparacion->forceFill([
                'ejecucion_id' => $actual->id, 'kinesiologa_id' => $origen->kinesiologa_id,
                'preparacion_origen_id' => $origen->preparacion_origen_id ?? $origen->id,
                'resultado' => ResultadoGeneracionAgendaEnum::COMPLETADA,
                'iniciado_at' => $momento, 'finalizado_at' => $momento,
                'total_turnos' => $origen->total_turnos, 'turnos_operativos' => $origen->turnos_operativos,
                'turnos_en_atencion' => $origen->turnos_en_atencion, 'otros_turnos' => $origen->otros_turnos,
            ])->saveOrFail();

            return $preparacion;
        });
    }

    /** Sólo el titular del control de jornada puede recuperar una ejecución interrumpida. */
    public function interrumpir(EjecucionAgendaDiaria $ejecucion): EjecucionAgendaDiaria
    {
        return DB::transaction(function () use ($ejecucion): EjecucionAgendaDiaria {
            $actual = $this->ejecucionAbierta($ejecucion);
            foreach ($actual->preparaciones()->where('resultado', ResultadoGeneracionAgendaEnum::EN_CURSO)->orderBy('id')->get() as $agenda) {
                $this->fallarAgenda($agenda, IncidenciaGeneracionAgendaEnum::EJECUCION_INTERRUMPIDA);
            }
            $actual->forceFill([
                'resultado' => ResultadoGeneracionAgendaEnum::FALLIDA,
                'codigo_incidencia' => IncidenciaGeneracionAgendaEnum::EJECUCION_INTERRUMPIDA,
                'finalizado_at' => $this->momento($actual),
            ])->saveOrFail();

            return $actual;
        });
    }

    /** Sin profesionales = resultado completo sin filas ficticias. Un fallo parcial no borra éxitos. */
    public function finalizar(EjecucionAgendaDiaria $ejecucion): EjecucionAgendaDiaria
    {
        return DB::transaction(function () use ($ejecucion): EjecucionAgendaDiaria {
            $actual = $this->ejecucionAbierta($ejecucion);
            if ($actual->preparaciones()->where('resultado', ResultadoGeneracionAgendaEnum::EN_CURSO)->exists()) {
                throw new LogicException('Existen preparaciones sin resultado conocido.');
            }
            $fallida = $actual->preparaciones()->where('resultado', ResultadoGeneracionAgendaEnum::FALLIDA)->exists();
            $actual->forceFill([
                'resultado' => $fallida ? ResultadoGeneracionAgendaEnum::FALLIDA : ResultadoGeneracionAgendaEnum::COMPLETADA,
                'codigo_incidencia' => $fallida ? IncidenciaGeneracionAgendaEnum::PREPARACION_JORNADA : null,
                'finalizado_at' => $this->momento($actual),
            ])->saveOrFail();

            return $actual;
        });
    }

    /** Fallo de selección: no confundirlo con ausencia real de profesionales habilitadas. */
    public function fallarSeleccion(EjecucionAgendaDiaria $ejecucion): EjecucionAgendaDiaria
    {
        return DB::transaction(function () use ($ejecucion): EjecucionAgendaDiaria {
            $actual = $this->ejecucionAbierta($ejecucion);
            if ($actual->preparaciones()->exists()) {
                throw new LogicException('La selección ya produjo registros profesionales.');
            }
            $actual->forceFill([
                'resultado' => ResultadoGeneracionAgendaEnum::FALLIDA,
                'codigo_incidencia' => IncidenciaGeneracionAgendaEnum::SELECCION_PROFESIONALES,
                'finalizado_at' => $this->momento($actual),
            ])->saveOrFail();

            return $actual;
        });
    }

    private function cerrarAgenda(PreparacionAgendaDiaria $preparacion, callable $datos): PreparacionAgendaDiaria
    {
        // Releer el ámbito persistido antes de bloquear siempre ejecución → preparación.
        $referencia = PreparacionAgendaDiaria::query()->findOrFail($preparacion->getKey());

        return DB::transaction(function () use ($referencia, $datos): PreparacionAgendaDiaria {
            $ejecucion = $this->ejecucionAbierta($referencia->ejecucion);
            $actual = PreparacionAgendaDiaria::query()->whereKey($referencia->id)->lockForUpdate()->firstOrFail();
            if ($actual->resultado !== ResultadoGeneracionAgendaEnum::EN_CURSO) {
                throw new LogicException('La preparación ya tiene un resultado final.');
            }
            $momento = $this->momento($ejecucion);
            if ($momento->lt(CarbonImmutable::parse($actual->getRawOriginal('iniciado_at'), $ejecucion->zona_horaria))) {
                throw new LogicException('La finalización no puede preceder al inicio.');
            }
            $actual->forceFill($datos($actual, $ejecucion) + ['finalizado_at' => $momento])->saveOrFail();

            return $actual;
        });
    }

    private function ejecucionAbierta(EjecucionAgendaDiaria $ejecucion): EjecucionAgendaDiaria
    {
        $actual = EjecucionAgendaDiaria::query()->whereKey($ejecucion->getKey())->lockForUpdate()->firstOrFail();
        if ($actual->resultado !== ResultadoGeneracionAgendaEnum::EN_CURSO) {
            throw new LogicException('La ejecución ya tiene un resultado final.');
        }

        return $actual;
    }

    private function momento(EjecucionAgendaDiaria $ejecucion): CarbonImmutable
    {
        $ahora = $this->reloj->ahora()->setTimezone($ejecucion->zona_horaria);
        // Los DATETIME persistidos son locales a la zona registrada, no a la zona
        // implícita del parser (que puede diferir en un proceso de recuperación).
        if ($ahora->lt(CarbonImmutable::parse($ejecucion->getRawOriginal('iniciado_at'), $ejecucion->zona_horaria))) {
            throw new LogicException('El procesamiento no puede preceder al inicio de la ejecución.');
        }

        return $ahora;
    }
}
