<?php

namespace App\Jobs;

use App\Models\OfertaAdelantoTurno;
use App\Services\AsignacionAutomaticaDeTurnosService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcesarExpiracionOfertaAdelantoTurnoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * ID de la oferta que vamos a procesar.
     */
    public int $ofertaId;

    /**
     * Crear una nueva instancia del Job.
     */
    public function __construct(int $ofertaId)
    {
        $this->ofertaId = $ofertaId;

        // Si quisieras separarlo en otra cola:
        // $this->onQueue('ofertas');
    }

    /**
     * Ejecuta el Job.
     */
    public function handle(AsignacionAutomaticaDeTurnosService $service): void
    {
        DB::transaction(function () use ($service) {
            /** @var OfertaAdelantoTurno|null $oferta */
            $oferta = OfertaAdelantoTurno::lockForUpdate()->find($this->ofertaId);

            if (! $oferta) {
                return;
            }

            // Si ya no está pendiente, alguien respondió (aceptó/rechazó).
            if ($oferta->estado !== OfertaAdelantoTurno::ESTADO_PENDIENTE) {
                return;
            }

            // Si todavía no venció, no hacemos nada.
            if (
                $oferta->fecha_limite_respuesta &&
                now()->lessThanOrEqualTo($oferta->fecha_limite_respuesta)
            ) {
                return;
            }

            // 1) Marcar como "sin_respuesta".
            $oferta->update([
                'estado'        => OfertaAdelantoTurno::ESTADO_SIN_RESPUESTA,
                'respondida_at' => now(),
            ]);

            // 2) Intentar con el siguiente candidato (si lo hubiera).
            $service->generarSiguienteOferta($oferta);
        });
    }
}
