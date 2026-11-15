<?php

namespace App\Services\Turnos;

use App\Models\Auditoria;
use App\Models\RecordatorioTurno;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Canal interno CA-27-26: proyección de incidencias durables, sin duplicar su persistencia. */
final class ConsultarAlertasRecordatorioService
{
    public const MENSAJES = [
        'TRANSPORTE_DEFINITIVO' => 'El servicio de correo rechazó definitivamente el recordatorio.',
        'REINTENTOS_AGOTADOS' => 'El recordatorio no pudo enviarse después de los tres reintentos permitidos.',
        'VENTANA_REINTENTOS_AGOTADA' => 'Se agotó el plazo de una hora para reintentar el envío del recordatorio.',
    ];

    public function contar(User $usuario): int
    {
        $this->autorizar($usuario);

        return $this->incidencias()->distinct()->count('auditable_id');
    }

    public function listar(User $usuario): LengthAwarePaginator
    {
        $this->autorizar($usuario);
        // Primera incidencia terminal por identidad: replay y revisiones posteriores no duplican el aviso.
        $primeras = $this->incidencias()->selectRaw('MIN(id)')->groupBy('auditable_id');

        return Auditoria::query()->select(['id', 'auditable_id', 'valores_nuevos', 'created_at'])
            ->selectSub(RecordatorioTurno::query()->select('turno_id')
                ->whereColumn('recordatorios_turno.id', 'auditorias.auditable_id'), 'turno_id')
            ->whereIn('id', $primeras)->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
    }

    private function autorizar(User $usuario): void
    {
        abort_unless($usuario->activo && $usuario->esAdministrador(), 403);
    }

    private function incidencias(): Builder
    {
        return Auditoria::query()->where('auditable_type', (new RecordatorioTurno)->getMorphClass())
            ->whereIn('evento', ['recordatorio_resultado_registrado', 'recordatorio_recuperacion_evaluada'])
            ->whereIn('valores_nuevos->incidencia', array_keys(self::MENSAJES));
    }
}
