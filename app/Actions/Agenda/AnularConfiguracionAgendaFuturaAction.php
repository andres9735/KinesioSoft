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
use App\Services\AuditoriaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class AnularConfiguracionAgendaFuturaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService,
        private readonly AgendaSnapshotService $snapshotService,
        private readonly RelojAplicacion $reloj
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

            $franjas = FranjaHorariaAgenda::query()
                ->where('configuracion_agenda_id', $target->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $target->setRelation('franjasHorarias', $franjas);

            if ($target->anulado_at !== null || ! $target->vigencia_desde->gt($hoy)) {
                throw new ConfiguracionAgendaNoElegibleException('Sólo puede anularse una configuración futura no anulada.');
            }

            $token = $data['snapshot'] ?? null;
            if (! is_string($token)) {
                throw new InvalidArgumentException('El snapshot de la configuración es obligatorio.');
            }
            $this->snapshotService->verificarConfiguracion($token, $target);

            $motivo = $data['motivo_anulacion'] ?? null;
            if (! is_string($motivo) || trim($motivo) === '' || mb_strlen(trim($motivo)) > 500) {
                throw new InvalidArgumentException('El motivo de anulación es obligatorio y no puede superar 500 caracteres.');
            }
            $motivo = trim($motivo);

            $activas = $this->activas($configuraciones);
            $sinTarget = $activas->reject(fn (ConfiguracionAgenda $item): bool => $item->is($target))->values();
            $predecesora = $this->predecesora($sinTarget, $target->vigencia_desde);
            $sucesora = $this->sucesora($sinTarget, $target->vigencia_desde);
            $fines = $this->finesOriginales($sinTarget);
            $finAnterior = $predecesora?->vigencia_hasta?->toDateString();
            $reconciliar = $predecesora !== null && $this->estanVinculadas($predecesora, $target);

            if ($reconciliar) {
                $fines[$predecesora->getKey()] = $sucesora?->vigencia_desde->subDay()->toDateString();
            }

            $this->validarSinSolapamientos($this->proyectar($sinTarget, $fines));

            $target->anulado_at = $ahora;
            $target->motivo_anulacion = $motivo;
            $target->save();

            $finNuevo = $predecesora === null ? null : $fines[$predecesora->getKey()];
            $predecesoraCambio = $reconciliar && $finAnterior !== $finNuevo;
            if ($predecesoraCambio) {
                $predecesora->vigencia_hasta = $finNuevo;
                $predecesora->save();
            }

            $this->auditoriaService->registrar(
                evento: 'configuracion_futura_anulada',
                modulo: 'Agenda',
                auditable: $target,
                valoresAnteriores: [
                    'anulado_at' => null,
                    'motivo_anulacion' => null,
                ],
                valoresNuevos: [
                    'anulado_at' => $target->anulado_at->format('Y-m-d H:i:sP'),
                    'motivo_anulacion' => $motivo,
                ],
                actor: $actor
            );

            if ($predecesoraCambio) {
                $this->auditoriaService->registrar(
                    evento: 'configuracion_vigencia_reconciliada',
                    modulo: 'Agenda',
                    auditable: $predecesora,
                    valoresAnteriores: ['vigencia_hasta' => $finAnterior],
                    valoresNuevos: [
                        'vigencia_hasta' => $finNuevo,
                        'causa_configuracion_id' => $target->getKey(),
                    ],
                    actor: $actor
                );
            }

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
}
