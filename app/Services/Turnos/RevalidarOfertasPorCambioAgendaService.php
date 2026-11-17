<?php

namespace App\Services\Turnos;

use App\Models\Kinesiologa;
use App\Models\OfertaAdelanto;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

final class RevalidarOfertasPorCambioAgendaService
{
    public function __construct(
        private readonly ValidarDestinoAdelantoService $validador,
        private readonly RelojAplicacion $reloj,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * El cambio ya se escribió en esta transacción, bajo el mutex de kinesióloga.
     * No toma otro mutex de agenda. No toca turnos ni renueva ofertas compatibles.
     * No revive ofertas cerradas ni implementa el cierre automático por vencimiento.
     */
    public function ejecutar(Kinesiologa $profesional, User $actor): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('El cambio de agenda y la liberación requieren una misma transacción.');
        }
        $ofertas = OfertaAdelanto::query()->where('kinesiologa_id', $profesional->id)
            ->where('estado', OfertaAdelanto::ACTIVA)->whereNull('cerrada_at')
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($ofertas as $oferta) {
            if (! $oferta->reservaVigenteEn($this->reloj->ahora())) {
                continue;
            }
            $candidato = Turno::query()->lockForUpdate()->findOrFail($oferta->turno_id);
            $destino = $this->validador->validar($candidato, $profesional, $oferta->inicio_at, $oferta->fin_at, bloquear: true);
            if ($destino !== null && $candidato->inicio_at->equalTo($oferta->origen_inicio_at)
                && $candidato->fin_at->equalTo($oferta->origen_fin_at)
                && (int) $candidato->paciente_id === (int) $oferta->paciente_id
                && $destino['fin_at']->equalTo($oferta->destino_fin_at)) {
                continue;
            }
            $oferta->estado = OfertaAdelanto::CERRADA_AGENDA;
            $oferta->cerrada_at = $this->reloj->ahora();
            if (! $oferta->save()) {
                throw new RuntimeException('No se pudo cerrar la oferta invalidada por el cambio de agenda.');
            }
            $this->auditoria->registrar(
                evento: 'oferta_adelanto_cerrada_por_agenda', modulo: 'Turnos', auditable: $oferta,
                valoresAnteriores: ['estado' => OfertaAdelanto::ACTIVA],
                valoresNuevos: ['estado' => $oferta->estado], actor: $actor,
            );
        }
    }
}
