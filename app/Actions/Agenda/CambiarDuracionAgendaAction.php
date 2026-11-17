<?php

namespace App\Actions\Agenda;

use App\Exceptions\Agenda\ConfiguracionAgendaNoElegibleException;
use App\Exceptions\Agenda\ConflictoTemporalAgendaException;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\Agenda\AgendaSnapshotService;
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

final class CambiarDuracionAgendaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService,
        private readonly AgendaSnapshotService $snapshotService,
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

            $fechaEfecto = $this->fecha($data['vigencia_desde'] ?? null);
            $duracion = $this->duracion($data['duracion_turno_minutos'] ?? null);

            if (! $fechaEfecto->gt($hoy)) {
                throw new ConfiguracionAgendaNoElegibleException(
                    'El cambio de duración debe comenzar después de hoy.'
                );
            }

            $activas = $this->activas($configuraciones);
            $fechaOrigen = $fechaEfecto->subDay();
            $origenes = $activas->filter(
                fn (ConfiguracionAgenda $item): bool => $this->aplicaEn($item, $fechaOrigen)
            );

            if ($origenes->count() !== 1) {
                throw new ConfiguracionAgendaNoElegibleException(
                    'No existe una única configuración de origen aplicable al día anterior.'
                );
            }

            /** @var ConfiguracionAgenda $origen */
            $origen = $origenes->first();
            $franjasOrigen = FranjaHorariaAgenda::query()
                ->where('configuracion_agenda_id', $origen->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $origen->setRelation('franjasHorarias', $franjasOrigen);

            $token = $data['snapshot'] ?? null;
            if (! is_string($token)) {
                throw new InvalidArgumentException('El snapshot de la configuración de origen es obligatorio.');
            }
            $this->snapshotService->verificarConfiguracion($token, $origen);

            $franjasPayload = $data['franjas'] ?? null;
            if (! is_iterable($franjasPayload)) {
                throw new InvalidArgumentException('Las franjas deben informarse como una matriz.');
            }
            $franjasNuevas = $this->validadorMatriz->validar($franjasPayload, $duracion);
            $sucesora = $activas
                ->filter(fn (ConfiguracionAgenda $item): bool => $item->vigencia_desde->gt($fechaEfecto))
                ->sortBy(fn (ConfiguracionAgenda $item): string => $item->vigencia_desde->toDateString())
                ->first();
            $finNueva = $sucesora?->vigencia_desde->subDay();
            $nuevoFinOrigen = $fechaOrigen;
            $fines = $this->finesOriginales($activas);
            $fines[$origen->getKey()] = $nuevoFinOrigen->toDateString();
            $proyeccion = $this->proyectar($activas, $fines);
            $proyeccion[] = [
                'id' => null,
                'desde' => $fechaEfecto->toDateString(),
                'hasta' => $finNueva?->toDateString(),
            ];
            $this->validarSinSolapamientos($proyeccion);

            $finOrigenAnterior = $origen->vigencia_hasta?->toDateString();
            $origenCambio = $finOrigenAnterior !== $nuevoFinOrigen->toDateString();
            $snapshotOrigen = $this->snapshotAuditoria($origen, $this->matrizSemantica($franjasOrigen));

            if ($origenCambio) {
                $origen->vigencia_hasta = $nuevoFinOrigen;
                $origen->save();
            }

            $nueva = $kinesiologa->configuracionesAgenda()->create([
                'vigencia_desde' => $fechaEfecto->toDateString(),
                'vigencia_hasta' => $finNueva?->toDateString(),
                'duracion_turno_minutos' => $duracion,
            ]);
            $nueva->franjasHorarias()->createMany($franjasNuevas);
            $nueva->load('franjasHorarias');

            $this->auditoriaService->registrar(
                evento: 'duracion_agenda_programada',
                modulo: 'Agenda',
                auditable: $nueva,
                valoresAnteriores: [
                    'configuracion_origen_id' => $origen->getKey(),
                    'configuracion_origen' => $snapshotOrigen,
                ],
                valoresNuevos: $this->snapshotAuditoria($nueva, $this->matrizSemantica($nueva->franjasHorarias)),
                actor: $actor
            );

            if ($origenCambio) {
                $this->auditoriaService->registrar(
                    evento: 'configuracion_vigencia_reconciliada',
                    modulo: 'Agenda',
                    auditable: $origen,
                    valoresAnteriores: ['vigencia_hasta' => $finOrigenAnterior],
                    valoresNuevos: [
                        'vigencia_hasta' => $nuevoFinOrigen->toDateString(),
                        'causa_configuracion_id' => $nueva->getKey(),
                    ],
                    actor: $actor
                );
            }

            $this->ofertas->ejecutar($kinesiologa, $actor);

            return $nueva;
        });
    }

    private function bloquearKinesiologa(User $actor): Kinesiologa
    {
        $kinesiologa = Kinesiologa::query()->where('user_id', $actor->getKey())->lockForUpdate()->first();
        if ($kinesiologa === null) {
            throw new ConfiguracionAgendaNoElegibleException('El actor no posee un perfil de kinesióloga.');
        }

        return $kinesiologa;
    }

    /** @return Collection<int, ConfiguracionAgenda> */
    private function bloquearConfiguraciones(Kinesiologa $kinesiologa): Collection
    {
        return ConfiguracionAgenda::query()->where('kinesiologa_id', $kinesiologa->getKey())
            ->orderBy('id')->lockForUpdate()->get();
    }

    /** @param Collection<int, ConfiguracionAgenda> $configuraciones */
    private function activas(Collection $configuraciones): Collection
    {
        return $configuraciones->filter(fn (ConfiguracionAgenda $item): bool => $item->anulado_at === null)->values();
    }

    /** @param Collection<int, ConfiguracionAgenda> $configuraciones */
    private function validarIntegridadActual(Collection $configuraciones): void
    {
        $activas = $this->activas($configuraciones);
        $this->validarSinSolapamientos($this->proyectar($activas, $this->finesOriginales($activas)), LogicException::class);
    }

    private function aplicaEn(ConfiguracionAgenda $configuracion, CarbonImmutable $fecha): bool
    {
        return $configuracion->vigencia_desde->lte($fecha)
            && ($configuracion->vigencia_hasta === null || $configuracion->vigencia_hasta->gte($fecha));
    }

    /** @param Collection<int, ConfiguracionAgenda> $configuraciones @return array<int, ?string> */
    private function finesOriginales(Collection $configuraciones): array
    {
        return $configuraciones->mapWithKeys(fn (ConfiguracionAgenda $item): array => [
            $item->getKey() => $item->vigencia_hasta?->toDateString(),
        ])->all();
    }

    /** @param Collection<int, ConfiguracionAgenda> $configuraciones @param array<int, ?string> $fines */
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

    private function fecha(mixed $valor): CarbonImmutable
    {
        if (! is_string($valor)) {
            throw new InvalidArgumentException('vigencia_desde debe usar el formato Y-m-d.');
        }
        try {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $valor, config('app.timezone'));
        } catch (Throwable) {
            throw new InvalidArgumentException('vigencia_desde debe usar el formato Y-m-d.');
        }
        if ($fecha->format('Y-m-d') !== $valor) {
            throw new InvalidArgumentException('vigencia_desde debe usar el formato Y-m-d.');
        }

        return $fecha;
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

    private function matrizSemantica(Collection $franjas): array
    {
        return $franjas->map(fn (FranjaHorariaAgenda $franja): array => [
            'dia_semana' => (int) $franja->dia_semana->value,
            'hora_inicio' => (string) $franja->hora_inicio,
            'hora_fin' => (string) $franja->hora_fin,
        ])->sortBy(fn (array $franja): array => array_values($franja))->values()->all();
    }

    private function snapshotAuditoria(ConfiguracionAgenda $configuracion, array $matriz): array
    {
        return [
            'vigencia_desde' => $configuracion->vigencia_desde->toDateString(),
            'vigencia_hasta' => $configuracion->vigencia_hasta?->toDateString(),
            'duracion_turno_minutos' => (int) $configuracion->duracion_turno_minutos,
            'franjas' => $matriz,
        ];
    }
}
