<?php

namespace App\Actions\Agenda;

use App\Exceptions\Agenda\ConfiguracionAgendaNoElegibleException;
use App\Exceptions\Agenda\ConflictoTemporalAgendaException;
use App\Models\ConfiguracionAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\Agenda\RelojAplicacion;
use App\Services\Agenda\ValidadorMatrizAgenda;
use App\Services\AuditoriaService;
use App\Services\Turnos\RevalidarOfertasPorCambioAgendaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Throwable;

final class CrearConfiguracionAgendaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService,
        private readonly ValidadorMatrizAgenda $validadorMatriz,
        private readonly RelojAplicacion $reloj,
        private readonly RevalidarOfertasPorCambioAgendaService $ofertas,
    ) {}

    public function ejecutar(User $actor, array $data): ConfiguracionAgenda
    {
        return DB::transaction(function () use ($actor, $data): ConfiguracionAgenda {
            $kinesiologa = $this->bloquearKinesiologa($actor);
            $configuraciones = $this->bloquearConfiguraciones($kinesiologa);
            $ahora = $this->reloj->ahora();
            $hoy = $this->reloj->hoy($ahora);

            $this->validarIntegridadActual($configuraciones);

            $desde = $this->fecha($data['vigencia_desde'] ?? null, 'vigencia_desde');
            $hasta = $this->fechaNullable($data['vigencia_hasta'] ?? null, 'vigencia_hasta');
            $duracion = $this->duracion($data['duracion_turno_minutos'] ?? null);
            $franjasPayload = $data['franjas'] ?? null;
            if (! is_iterable($franjasPayload)) {
                throw new InvalidArgumentException('Las franjas deben informarse como una matriz.');
            }
            $franjas = $this->validadorMatriz->validar($franjasPayload, $duracion);

            if ($hasta !== null && $hasta->lt($desde)) {
                throw new InvalidArgumentException('La vigencia final no puede ser anterior a la inicial.');
            }

            $vigentesHoy = $this->activas($configuraciones)
                ->filter(fn (ConfiguracionAgenda $configuracion): bool => $this->aplicaEn($configuracion, $hoy));

            if ($vigentesHoy->count() > 1) {
                throw new LogicException('Existe más de una configuración aplicable hoy.');
            }

            if ($desde->lt($hoy) || ($vigentesHoy->isNotEmpty() && ! $desde->gt($hoy))) {
                throw new ConfiguracionAgendaNoElegibleException(
                    'La fecha de inicio propuesta no es elegible para crear la configuración.'
                );
            }

            $activas = $this->activas($configuraciones);
            $predecesora = $this->predecesora($activas, $desde);
            $sucesora = $this->sucesora($activas, $desde);
            $finesProyectados = $this->finesOriginales($activas);

            if ($predecesora !== null && $this->alcanza($predecesora, $desde)) {
                $nuevoFin = $desde->subDay();

                if ($nuevoFin->lt($predecesora->vigencia_desde)) {
                    throw new ConflictoTemporalAgendaException('La configuración no puede insertarse en ese período.');
                }

                $finesProyectados[$predecesora->getKey()] = $nuevoFin->toDateString();
            }

            if ($sucesora !== null
                && ($hasta === null || ! $hasta->lt($sucesora->vigencia_desde))) {
                throw new ConflictoTemporalAgendaException(
                    'La vigencia propuesta se superpone con una configuración posterior.'
                );
            }

            $proyeccion = $this->proyectar($activas, $finesProyectados);
            $proyeccion[] = [
                'id' => null,
                'desde' => $desde->toDateString(),
                'hasta' => $hasta?->toDateString(),
            ];
            $this->validarSinSolapamientos($proyeccion);

            $huboReconciliacion = false;
            $reconciliacionAnterior = null;

            if ($predecesora !== null) {
                $finAnterior = $predecesora->vigencia_hasta?->toDateString();
                $finNuevo = $finesProyectados[$predecesora->getKey()];

                if ($finAnterior !== $finNuevo) {
                    $huboReconciliacion = true;
                    $reconciliacionAnterior = $finAnterior;
                    $predecesora->vigencia_hasta = $finNuevo;
                    $predecesora->save();
                }
            }

            $configuracion = $kinesiologa->configuracionesAgenda()->create([
                'vigencia_desde' => $desde->toDateString(),
                'vigencia_hasta' => $hasta?->toDateString(),
                'duracion_turno_minutos' => $duracion,
            ]);
            $configuracion->franjasHorarias()->createMany($franjas);
            $configuracion->load('franjasHorarias');

            $this->auditoriaService->registrar(
                evento: 'configuracion_agenda_creada',
                modulo: 'Agenda',
                auditable: $configuracion,
                valoresNuevos: $this->snapshotAuditoria($configuracion),
                actor: $actor
            );

            if ($predecesora !== null && $huboReconciliacion) {
                $this->auditarReconciliacion(
                    $actor,
                    $predecesora,
                    $reconciliacionAnterior,
                    $predecesora->vigencia_hasta?->toDateString(),
                    $configuracion->getKey()
                );
            }

            $this->ofertas->ejecutar($kinesiologa, $actor);

            return $configuracion;
        });
    }

    private function bloquearKinesiologa(User $actor): Kinesiologa
    {
        $kinesiologa = Kinesiologa::query()
            ->where('user_id', $actor->getKey())
            ->lockForUpdate()
            ->first();

        if ($kinesiologa === null) {
            throw new ConfiguracionAgendaNoElegibleException('El actor no posee un perfil de kinesióloga.');
        }

        return $kinesiologa;
    }

    /** @return Collection<int, ConfiguracionAgenda> */
    private function bloquearConfiguraciones(Kinesiologa $kinesiologa): Collection
    {
        return ConfiguracionAgenda::query()
            ->where('kinesiologa_id', $kinesiologa->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** @param Collection<int, ConfiguracionAgenda> $configuraciones */
    private function validarIntegridadActual(Collection $configuraciones): void
    {
        $this->validarSinSolapamientos($this->proyectar(
            $this->activas($configuraciones),
            $this->finesOriginales($this->activas($configuraciones))
        ), LogicException::class);
    }

    /** @param Collection<int, ConfiguracionAgenda> $configuraciones */
    private function activas(Collection $configuraciones): Collection
    {
        return $configuraciones->filter(
            fn (ConfiguracionAgenda $configuracion): bool => $configuracion->anulado_at === null
        )->values();
    }

    private function aplicaEn(ConfiguracionAgenda $configuracion, CarbonImmutable $fecha): bool
    {
        return $configuracion->vigencia_desde->lte($fecha)
            && ($configuracion->vigencia_hasta === null || $configuracion->vigencia_hasta->gte($fecha));
    }

    /** @param Collection<int, ConfiguracionAgenda> $configuraciones */
    private function predecesora(Collection $configuraciones, CarbonImmutable $desde): ?ConfiguracionAgenda
    {
        return $configuraciones
            ->filter(fn (ConfiguracionAgenda $item): bool => $item->vigencia_desde->lt($desde))
            ->sortByDesc(fn (ConfiguracionAgenda $item): string => $item->vigencia_desde->toDateString())
            ->first();
    }

    /** @param Collection<int, ConfiguracionAgenda> $configuraciones */
    private function sucesora(Collection $configuraciones, CarbonImmutable $desde): ?ConfiguracionAgenda
    {
        return $configuraciones
            ->filter(fn (ConfiguracionAgenda $item): bool => $item->vigencia_desde->gt($desde))
            ->sortBy(fn (ConfiguracionAgenda $item): string => $item->vigencia_desde->toDateString())
            ->first();
    }

    private function alcanza(ConfiguracionAgenda $configuracion, CarbonImmutable $desde): bool
    {
        return $configuracion->vigencia_hasta === null || $configuracion->vigencia_hasta->gte($desde);
    }

    /** @param Collection<int, ConfiguracionAgenda> $configuraciones @return array<int, ?string> */
    private function finesOriginales(Collection $configuraciones): array
    {
        return $configuraciones->mapWithKeys(fn (ConfiguracionAgenda $item): array => [
            $item->getKey() => $item->vigencia_hasta?->toDateString(),
        ])->all();
    }

    /**
     * @param  Collection<int, ConfiguracionAgenda>  $configuraciones
     * @param  array<int, ?string>  $fines
     * @return list<array{id: ?int, desde: string, hasta: ?string}>
     */
    private function proyectar(Collection $configuraciones, array $fines): array
    {
        return $configuraciones->map(fn (ConfiguracionAgenda $item): array => [
            'id' => $item->getKey(),
            'desde' => $item->vigencia_desde->toDateString(),
            'hasta' => $fines[$item->getKey()],
        ])->values()->all();
    }

    /** @param list<array{id: ?int, desde: string, hasta: ?string}> $periodos */
    private function validarSinSolapamientos(array $periodos, string $exceptionClass = ConflictoTemporalAgendaException::class): void
    {
        usort($periodos, static fn (array $a, array $b): int => [$a['desde'], $a['id'] ?? PHP_INT_MAX] <=> [$b['desde'], $b['id'] ?? PHP_INT_MAX]);
        $anterior = null;

        foreach ($periodos as $periodo) {
            if ($periodo['hasta'] !== null && $periodo['hasta'] < $periodo['desde']) {
                throw new $exceptionClass('Existe una configuración con un período temporal inválido.');
            }

            if ($anterior !== null && ($anterior['hasta'] === null || $anterior['hasta'] >= $periodo['desde'])) {
                throw new $exceptionClass('Existen configuraciones de agenda no anuladas superpuestas.');
            }

            $anterior = $periodo;
        }
    }

    private function fecha(mixed $valor, string $campo): CarbonImmutable
    {
        if (! is_string($valor)) {
            throw new InvalidArgumentException("{$campo} debe usar el formato Y-m-d.");
        }

        try {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $valor, config('app.timezone'));
        } catch (Throwable) {
            throw new InvalidArgumentException("{$campo} debe usar el formato Y-m-d.");
        }

        if ($fecha->format('Y-m-d') !== $valor) {
            throw new InvalidArgumentException("{$campo} debe usar el formato Y-m-d.");
        }

        return $fecha;
    }

    private function fechaNullable(mixed $valor, string $campo): ?CarbonImmutable
    {
        return $valor === null ? null : $this->fecha($valor, $campo);
    }

    private function duracion(mixed $valor): int
    {
        if (is_string($valor) && ctype_digit($valor)) {
            $valor = (int) $valor;
        }

        if (! is_int($valor) || $valor < 15 || $valor > 180 || $valor % 5 !== 0) {
            throw new InvalidArgumentException('La duración debe ser un entero entre 15 y 180 y múltiplo de 5.');
        }

        return $valor;
    }

    /** @return array<string, mixed> */
    private function snapshotAuditoria(ConfiguracionAgenda $configuracion): array
    {
        return [
            'vigencia_desde' => $configuracion->vigencia_desde->toDateString(),
            'vigencia_hasta' => $configuracion->vigencia_hasta?->toDateString(),
            'duracion_turno_minutos' => (int) $configuracion->duracion_turno_minutos,
            'franjas' => $configuracion->franjasHorarias
                ->map(fn ($franja): array => [
                    'dia_semana' => (int) $franja->dia_semana->value,
                    'hora_inicio' => (string) $franja->hora_inicio,
                    'hora_fin' => (string) $franja->hora_fin,
                ])->sortBy(fn (array $franja): array => array_values($franja))->values()->all(),
        ];
    }

    private function auditarReconciliacion(
        User $actor,
        ConfiguracionAgenda $configuracion,
        ?string $antes,
        ?string $despues,
        int $causanteId
    ): void {
        $this->auditoriaService->registrar(
            evento: 'configuracion_vigencia_reconciliada',
            modulo: 'Agenda',
            auditable: $configuracion,
            valoresAnteriores: ['vigencia_hasta' => $antes],
            valoresNuevos: [
                'vigencia_hasta' => $despues,
                'causa_configuracion_id' => $causanteId,
            ],
            actor: $actor
        );
    }
}
