<?php

namespace App\Actions\Agenda;

use App\Exceptions\Agenda\BloqueoAgendaNoElegibleException;
use App\Models\BloqueoAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\AgendaSnapshotService;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class RegistrarBloqueoAgendaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService,
        private readonly AgendaQueryService $queryService,
        private readonly AgendaSnapshotService $snapshotService,
        private readonly RevisarBloqueoAgendaAction $revisarAction,
        private readonly RelojAplicacion $reloj
    ) {}

    public function ejecutar(User $actor, array $data): BloqueoAgenda
    {
        $inicioPreliminar = $this->fechaHoraPreliminar($data['inicio_at'] ?? null, 'inicio_at');
        $finPreliminar = $this->fechaHoraPreliminar($data['fin_at'] ?? null, 'fin_at');

        return DB::transaction(function () use (
            $actor,
            $data,
            $inicioPreliminar,
            $finPreliminar
        ): BloqueoAgenda {
            $kinesiologa = $this->bloquearKinesiologa($actor);
            $bloqueos = $this->queryService->bloqueosSuperpuestosCon(
                $kinesiologa,
                $inicioPreliminar,
                $finPreliminar
            )->reorder()->orderBy('id')->lockForUpdate()->get();
            $turnos = $this->queryService->turnosAfectadosPorIntervalo(
                $kinesiologa,
                $inicioPreliminar,
                $finPreliminar
            )->reorder()->orderBy('id')->lockForUpdate()->get();
            $ahora = $this->reloj->ahora();
            $candidato = $this->revisarAction->normalizarCandidato($data, $ahora);
            $revision = $this->revisarAction->construirResultado(
                $kinesiologa,
                $candidato,
                $turnos,
                $bloqueos
            );
            $snapshot = $data['snapshot'] ?? null;

            if (! is_string($snapshot) || trim($snapshot) === '') {
                throw new InvalidArgumentException('El snapshot de revisión es obligatorio.');
            }

            $this->snapshotService->verificarRevisionBloqueo(
                trim($snapshot),
                (int) $kinesiologa->getKey(),
                [
                    'inicio_at' => $revision['inicio_at'],
                    'fin_at' => $revision['fin_at'],
                    'motivo' => $revision['motivo'],
                ],
                $revision['turnos_afectados'],
                $revision['bloqueos_superpuestos']
            );

            $bloqueo = $kinesiologa->bloqueosAgenda()->create([
                'inicio_at' => $candidato['inicio_at'],
                'fin_at' => $candidato['fin_at'],
                'motivo' => $candidato['motivo'],
            ]);

            $this->auditoriaService->registrar(
                evento: 'bloqueo_agenda_registrado',
                modulo: 'Agenda',
                auditable: $bloqueo,
                valoresNuevos: [
                    'kinesiologa_id' => (int) $kinesiologa->getKey(),
                    'inicio_at' => $bloqueo->inicio_at->format('Y-m-d H:i:sP'),
                    'fin_at' => $bloqueo->fin_at->format('Y-m-d H:i:sP'),
                    'motivo' => $bloqueo->motivo,
                    'cantidad_turnos_afectados' => $revision['cantidad_turnos_afectados'],
                    'cantidad_bloqueos_superpuestos' => $revision['cantidad_bloqueos_superpuestos'],
                ],
                actor: $actor
            );

            return $bloqueo;
        });
    }

    private function bloquearKinesiologa(User $actor): Kinesiologa
    {
        $kinesiologa = Kinesiologa::query()
            ->where('user_id', $actor->getKey())
            ->lockForUpdate()
            ->first();

        if ($kinesiologa === null) {
            throw new BloqueoAgendaNoElegibleException('El actor no posee un perfil de kinesióloga.');
        }

        return $kinesiologa;
    }

    private function fechaHoraPreliminar(mixed $valor, string $campo): CarbonImmutable
    {
        if (! is_string($valor)) {
            throw new InvalidArgumentException("{$campo} debe ser una fecha y hora válida.");
        }

        try {
            return CarbonImmutable::parse($valor, config('app.timezone'));
        } catch (Throwable) {
            throw new InvalidArgumentException("{$campo} debe ser una fecha y hora válida.");
        }
    }
}
