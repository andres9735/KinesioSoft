<?php

namespace App\Actions\Agenda;

use App\Exceptions\Agenda\BloqueoAgendaNoElegibleException;
use App\Models\BloqueoAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\Agenda\AgendaSnapshotService;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AnularBloqueoAgendaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService,
        private readonly AgendaSnapshotService $snapshotService,
        private readonly RelojAplicacion $reloj
    ) {}

    public function ejecutar(
        User $actor,
        BloqueoAgenda $bloqueo,
        array $data
    ): BloqueoAgenda {
        return DB::transaction(function () use ($actor, $bloqueo, $data): BloqueoAgenda {
            $kinesiologa = $this->bloquearKinesiologa($actor);
            $target = BloqueoAgenda::query()
                ->where('kinesiologa_id', $kinesiologa->getKey())
                ->whereKey($bloqueo->getKey())
                ->lockForUpdate()
                ->first();
            $ahora = $this->reloj->ahora();

            if ($target === null) {
                throw new BloqueoAgendaNoElegibleException('El bloqueo no pertenece a la kinesióloga.');
            }

            if ($target->anulado_at !== null) {
                throw new BloqueoAgendaNoElegibleException('El bloqueo ya fue anulado.');
            }

            $snapshot = $data['snapshot'] ?? null;

            if (! is_string($snapshot) || trim($snapshot) === '') {
                throw new InvalidArgumentException('El snapshot del bloqueo es obligatorio.');
            }

            $this->snapshotService->verificarBloqueo(trim($snapshot), $target);

            $motivo = $data['motivo_anulacion'] ?? null;

            if (! is_string($motivo) || trim($motivo) === '' || mb_strlen(trim($motivo)) > 500) {
                throw new InvalidArgumentException(
                    'El motivo de anulación es obligatorio y no puede superar 500 caracteres.'
                );
            }

            $antes = [
                'anulado_at' => null,
                'motivo_anulacion' => null,
            ];
            $target->anulado_at = $ahora;
            $target->motivo_anulacion = trim($motivo);
            $target->save();

            $this->auditoriaService->registrar(
                evento: 'bloqueo_agenda_anulado',
                modulo: 'Agenda',
                auditable: $target,
                valoresAnteriores: $antes,
                valoresNuevos: [
                    'anulado_at' => $target->anulado_at->format('Y-m-d H:i:sP'),
                    'motivo_anulacion' => $target->motivo_anulacion,
                ],
                actor: $actor
            );

            return $target;
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
}
