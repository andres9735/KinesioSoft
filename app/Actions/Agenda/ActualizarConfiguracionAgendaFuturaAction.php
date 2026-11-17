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

final class ActualizarConfiguracionAgendaFuturaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService,
        private readonly AgendaSnapshotService $snapshotService,
        private readonly ValidadorMatrizAgenda $validadorMatriz,
        private readonly RelojAplicacion $reloj,
        private readonly RevalidarOfertasPorCambioAgendaService $ofertas,
    ) {}

    public function ejecutar(
        User $actor,
        ConfiguracionAgenda $configuracion,
        array $data
    ): ConfiguracionAgenda {
        return DB::transaction(function () use ($actor, $configuracion, $data): ConfiguracionAgenda {
            $kinesiologa = $this->bloquearKinesiologa($actor);
            $configuraciones = $this->bloquearConfiguraciones($kinesiologa);
            $ahora = $this->reloj->ahora();
            $hoy = $this->reloj->hoy($ahora);
            $this->validarIntegridadActual($configuraciones);

            $target = $configuraciones->firstWhere('id', $configuracion->getKey());

            if (! $target instanceof ConfiguracionAgenda
                || $target->kinesiologa_id !== $kinesiologa->getKey()) {
                throw new ConfiguracionAgendaNoElegibleException('La configuración no pertenece a la kinesióloga.');
            }

            $franjasActuales = FranjaHorariaAgenda::query()
                ->where('configuracion_agenda_id', $target->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $target->setRelation('franjasHorarias', $franjasActuales);

            if ($target->anulado_at !== null || ! $target->vigencia_desde->gt($hoy)) {
                throw new ConfiguracionAgendaNoElegibleException('Sólo puede editarse una configuración futura no anulada.');
            }

            $token = $data['snapshot'] ?? null;

            if (! is_string($token)) {
                throw new InvalidArgumentException('El snapshot de la configuración es obligatorio.');
            }

            $this->snapshotService->verificarConfiguracion($token, $target);

            $desde = $this->fecha($data['vigencia_desde'] ?? null, 'vigencia_desde');
            $hasta = $this->fechaNullable($data['vigencia_hasta'] ?? null, 'vigencia_hasta');
            $duracion = $this->duracion($data['duracion_turno_minutos'] ?? null);
            $franjasPayload = $data['franjas'] ?? null;
            if (! is_iterable($franjasPayload)) {
                throw new InvalidArgumentException('Las franjas deben informarse como una matriz.');
            }
            $franjasNuevas = $this->validadorMatriz->validar($franjasPayload, $duracion);

            if (! $desde->gt($hoy)) {
                throw new ConfiguracionAgendaNoElegibleException('La configuración editada debe continuar siendo futura.');
            }

            if ($hasta !== null && $hasta->lt($desde)) {
                throw new InvalidArgumentException('La vigencia final no puede ser anterior a la inicial.');
            }

            $activas = $this->activas($configuraciones);
            $sinTarget = $activas->reject(fn (ConfiguracionAgenda $item): bool => $item->is($target))->values();
            $aOriginal = $this->predecesora($sinTarget, $target->vigencia_desde);
            $cOriginal = $this->sucesora($sinTarget, $target->vigencia_desde);
            $fines = $this->finesOriginales($sinTarget);

            if ($aOriginal !== null && $this->estanVinculadas($aOriginal, $target)) {
                $fines[$aOriginal->getKey()] = $cOriginal?->vigencia_desde->subDay()->toDateString();
            }

            $aNueva = $this->predecesora($sinTarget, $desde);
            $cNueva = $this->sucesora($sinTarget, $desde);

            if ($aNueva !== null && $this->alcanzaProyectado($aNueva, $fines, $desde)) {
                $nuevoFin = $desde->subDay();

                if ($nuevoFin->lt($aNueva->vigencia_desde)) {
                    throw new ConflictoTemporalAgendaException('La configuración no puede reinsertarse en ese período.');
                }

                $fines[$aNueva->getKey()] = $nuevoFin->toDateString();
            }

            if ($cNueva !== null && ($hasta === null || ! $hasta->lt($cNueva->vigencia_desde))) {
                throw new ConflictoTemporalAgendaException(
                    'La vigencia editada se superpone con una configuración posterior.'
                );
            }

            $proyeccion = $this->proyectar($sinTarget, $fines);
            $proyeccion[] = [
                'id' => $target->getKey(),
                'desde' => $desde->toDateString(),
                'hasta' => $hasta?->toDateString(),
            ];
            $this->validarSinSolapamientos($proyeccion);

            $matrizAnterior = $this->matrizSemantica($franjasActuales);
            $matrizNueva = $this->matrizSemantica($franjasNuevas);
            $matrizCambio = $matrizAnterior !== $matrizNueva;
            $antesTarget = $this->snapshotAuditoria($target, $matrizAnterior);
            $cabeceraCambio = $target->vigencia_desde->toDateString() !== $desde->toDateString()
                || $target->vigencia_hasta?->toDateString() !== $hasta?->toDateString()
                || (int) $target->duracion_turno_minutos !== $duracion;

            $reconciliaciones = [];

            foreach ($sinTarget as $vecina) {
                $antes = $vecina->vigencia_hasta?->toDateString();
                $despues = $fines[$vecina->getKey()];

                if ($antes !== $despues) {
                    $reconciliaciones[] = [$vecina, $antes, $despues];
                }
            }

            if (! $cabeceraCambio && ! $matrizCambio && $reconciliaciones === []) {
                return $target;
            }

            foreach ($reconciliaciones as [$vecina, , $despues]) {
                $vecina->vigencia_hasta = $despues;
                $vecina->save();
            }

            if ($cabeceraCambio) {
                $target->vigencia_desde = $desde;
                $target->vigencia_hasta = $hasta;
                $target->duracion_turno_minutos = $duracion;
                $target->save();
            }

            if ($matrizCambio) {
                $target->franjasHorarias()->delete();
                $target->franjasHorarias()->createMany($franjasNuevas);
            }

            $target->load('franjasHorarias');

            if ($cabeceraCambio || $matrizCambio) {
                $this->auditoriaService->registrar(
                    evento: 'configuracion_futura_actualizada',
                    modulo: 'Agenda',
                    auditable: $target,
                    valoresAnteriores: $antesTarget,
                    valoresNuevos: $this->snapshotAuditoria($target, $this->matrizSemantica($target->franjasHorarias)),
                    actor: $actor
                );
            }

            foreach ($reconciliaciones as [$vecina, $antes, $despues]) {
                $this->auditarReconciliacion($actor, $vecina, $antes, $despues, $target->getKey());
            }

            $this->ofertas->ejecutar($kinesiologa, $actor);

            return $target;
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
        return ConfiguracionAgenda::query()
            ->where('kinesiologa_id', $kinesiologa->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
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
        $this->validarSinSolapamientos(
            $this->proyectar($activas, $this->finesOriginales($activas)),
            LogicException::class
        );
    }

    /** @param Collection<int, ConfiguracionAgenda> $configuraciones */
    private function predecesora(Collection $configuraciones, CarbonImmutable $desde): ?ConfiguracionAgenda
    {
        return $configuraciones->filter(fn (ConfiguracionAgenda $item): bool => $item->vigencia_desde->lt($desde))
            ->sortByDesc(fn (ConfiguracionAgenda $item): string => $item->vigencia_desde->toDateString())->first();
    }

    /** @param Collection<int, ConfiguracionAgenda> $configuraciones */
    private function sucesora(Collection $configuraciones, CarbonImmutable $desde): ?ConfiguracionAgenda
    {
        return $configuraciones->filter(fn (ConfiguracionAgenda $item): bool => $item->vigencia_desde->gt($desde))
            ->sortBy(fn (ConfiguracionAgenda $item): string => $item->vigencia_desde->toDateString())->first();
    }

    private function estanVinculadas(ConfiguracionAgenda $a, ConfiguracionAgenda $b): bool
    {
        return $a->vigencia_hasta?->toDateString() === $b->vigencia_desde->subDay()->toDateString();
    }

    /** @param array<int, ?string> $fines */
    private function alcanzaProyectado(ConfiguracionAgenda $configuracion, array $fines, CarbonImmutable $desde): bool
    {
        $fin = $fines[$configuracion->getKey()];

        return $fin === null || $fin >= $desde->toDateString();
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

    /** @param Collection<int, FranjaHorariaAgenda>|array<int, array<string, mixed>> $franjas */
    private function matrizSemantica(Collection|array $franjas): array
    {
        return collect($franjas)->map(function ($franja): array {
            if ($franja instanceof FranjaHorariaAgenda) {
                return [
                    'dia_semana' => (int) $franja->dia_semana->value,
                    'hora_inicio' => (string) $franja->hora_inicio,
                    'hora_fin' => (string) $franja->hora_fin,
                ];
            }

            return [
                'dia_semana' => (int) $franja['dia_semana'],
                'hora_inicio' => (string) $franja['hora_inicio'],
                'hora_fin' => (string) $franja['hora_fin'],
            ];
        })->sortBy(fn (array $franja): array => array_values($franja))->values()->all();
    }

    /** @param array<int, array<string, mixed>> $matriz */
    private function snapshotAuditoria(ConfiguracionAgenda $configuracion, array $matriz): array
    {
        return [
            'vigencia_desde' => $configuracion->vigencia_desde->toDateString(),
            'vigencia_hasta' => $configuracion->vigencia_hasta?->toDateString(),
            'duracion_turno_minutos' => (int) $configuracion->duracion_turno_minutos,
            'franjas' => $matriz,
        ];
    }

    private function auditarReconciliacion(User $actor, ConfiguracionAgenda $configuracion, ?string $antes, ?string $despues, int $causanteId): void
    {
        $this->auditoriaService->registrar(
            evento: 'configuracion_vigencia_reconciliada',
            modulo: 'Agenda',
            auditable: $configuracion,
            valoresAnteriores: ['vigencia_hasta' => $antes],
            valoresNuevos: ['vigencia_hasta' => $despues, 'causa_configuracion_id' => $causanteId],
            actor: $actor
        );
    }
}
