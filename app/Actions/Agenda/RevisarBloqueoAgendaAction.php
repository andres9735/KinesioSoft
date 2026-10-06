<?php

namespace App\Actions\Agenda;

use App\Enums\EstadoTurnoEnum;
use App\Exceptions\Agenda\BloqueoAgendaNoElegibleException;
use App\Models\BloqueoAgenda;
use App\Models\Kinesiologa;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\AgendaSnapshotService;
use App\Services\Agenda\RelojAplicacion;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

final class RevisarBloqueoAgendaAction
{
    public function __construct(
        private readonly AgendaQueryService $queryService,
        private readonly AgendaSnapshotService $snapshotService,
        private readonly RelojAplicacion $reloj
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(User $actor, array $data): array
    {
        $kinesiologa = $this->resolverKinesiologa($actor);
        $ahora = $this->reloj->ahora();
        $candidato = $this->normalizarCandidato($data, $ahora);
        $turnos = $this->queryService->turnosAfectadosPorIntervalo(
            $kinesiologa,
            $candidato['inicio_at'],
            $candidato['fin_at']
        )->get();
        $bloqueos = $this->queryService->bloqueosSuperpuestosCon(
            $kinesiologa,
            $candidato['inicio_at'],
            $candidato['fin_at']
        )->get();

        return $this->construirResultado($kinesiologa, $candidato, $turnos, $bloqueos);
    }

    /**
     * @return array{inicio_at: CarbonImmutable, fin_at: CarbonImmutable, motivo: string}
     */
    public function normalizarCandidato(array $data, CarbonInterface $ahora): array
    {
        $inicio = $this->fechaHora($data['inicio_at'] ?? null, 'inicio_at');
        $fin = $this->fechaHora($data['fin_at'] ?? null, 'fin_at');
        $motivo = $data['motivo'] ?? null;

        if (! $inicio->isBefore($fin)) {
            throw new InvalidArgumentException('La finalización del bloqueo debe ser posterior al inicio.');
        }

        if (! $fin->isAfter($ahora)) {
            throw new InvalidArgumentException('No puede registrarse un bloqueo completamente histórico.');
        }

        if (! is_string($motivo) || trim($motivo) === '' || mb_strlen(trim($motivo)) > 500) {
            throw new InvalidArgumentException('El motivo es obligatorio y no puede superar 500 caracteres.');
        }

        return [
            'inicio_at' => $inicio,
            'fin_at' => $fin,
            'motivo' => trim($motivo),
        ];
    }

    /**
     * @param  array{inicio_at: CarbonInterface, fin_at: CarbonInterface, motivo: string}  $candidato
     * @param  Collection<int, Turno>  $turnos
     * @param  Collection<int, BloqueoAgenda>  $bloqueos
     * @return array<string, mixed>
     */
    public function construirResultado(
        Kinesiologa $kinesiologa,
        array $candidato,
        Collection $turnos,
        Collection $bloqueos
    ): array {
        $turnosCanonicos = $turnos
            ->map(fn (Turno $turno): array => [
                'id' => (int) $turno->getKey(),
                'estado' => $turno->estado instanceof EstadoTurnoEnum
                    ? $turno->estado->value
                    : (string) $turno->estado,
                'inicio_at' => $this->fechaHoraCanonica($turno->inicio_at),
                'fin_at' => $this->fechaHoraCanonica($turno->fin_at),
            ])
            ->sortBy(fn (array $turno): array => [
                $turno['inicio_at'],
                $turno['fin_at'],
                $turno['id'],
            ])
            ->values()
            ->all();
        $bloqueosCanonicos = $bloqueos
            ->map(fn (BloqueoAgenda $bloqueo): array => [
                'id' => (int) $bloqueo->getKey(),
                'inicio_at' => $this->fechaHoraCanonica($bloqueo->inicio_at),
                'fin_at' => $this->fechaHoraCanonica($bloqueo->fin_at),
                'anulado_at' => $bloqueo->anulado_at === null
                    ? null
                    : $this->fechaHoraCanonica($bloqueo->anulado_at),
                'motivo' => (string) $bloqueo->motivo,
            ])
            ->sortBy(fn (array $bloqueo): array => [
                $bloqueo['inicio_at'],
                $bloqueo['fin_at'],
                $bloqueo['id'],
            ])
            ->values()
            ->all();
        $candidatoCanonico = [
            'inicio_at' => $this->fechaHoraCanonica($candidato['inicio_at']),
            'fin_at' => $this->fechaHoraCanonica($candidato['fin_at']),
            'motivo' => $candidato['motivo'],
        ];

        return [
            ...$candidatoCanonico,
            'hay_turnos_afectados' => $turnosCanonicos !== [],
            'cantidad_turnos_afectados' => count($turnosCanonicos),
            'turnos_afectados' => $turnosCanonicos,
            'cantidad_bloqueos_superpuestos' => count($bloqueosCanonicos),
            'bloqueos_superpuestos' => $bloqueosCanonicos,
            'snapshot' => $this->snapshotService->generarRevisionBloqueo(
                (int) $kinesiologa->getKey(),
                $candidatoCanonico,
                $turnosCanonicos,
                $bloqueosCanonicos
            ),
        ];
    }

    private function resolverKinesiologa(User $actor): Kinesiologa
    {
        $kinesiologa = Kinesiologa::query()
            ->where('user_id', $actor->getKey())
            ->first();

        if ($kinesiologa === null) {
            throw new BloqueoAgendaNoElegibleException('El actor no posee un perfil de kinesióloga.');
        }

        return $kinesiologa;
    }

    private function fechaHora(mixed $valor, string $campo): CarbonImmutable
    {
        if (! is_string($valor) && ! $valor instanceof CarbonInterface) {
            throw new InvalidArgumentException("{$campo} debe ser una fecha y hora válida.");
        }

        try {
            return $valor instanceof CarbonInterface
                ? CarbonImmutable::instance($valor)->setTimezone(config('app.timezone'))
                : CarbonImmutable::parse($valor, config('app.timezone'));
        } catch (Throwable) {
            throw new InvalidArgumentException("{$campo} debe ser una fecha y hora válida.");
        }
    }

    private function fechaHoraCanonica(CarbonInterface $fecha): string
    {
        return CarbonImmutable::instance($fecha)
            ->setTimezone(config('app.timezone'))
            ->format('Y-m-d H:i:sP');
    }
}
