<?php

namespace App\Listeners;

use App\Events\TurnoCancelado;
use App\Services\AsignacionAutomaticaDeTurnosService;

class GenerarOfertaAdelantoTurno
{
    public function __construct(
        protected AsignacionAutomaticaDeTurnosService $service
    ) {}

    public function handle(TurnoCancelado $event): void
    {
        // Si no fue cancelación temprana, no hay oferta automática
        if (! $event->esTemprano) {
            return;
        }

        // Delegar en el servicio de dominio
        $this->service->generarPrimeraOferta($event->turno);
    }
}
