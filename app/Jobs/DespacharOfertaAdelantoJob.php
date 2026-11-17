<?php

namespace App\Jobs;

use App\Services\Turnos\DespacharOfertaAdelantoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class DespacharOfertaAdelantoJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    // Cada entrega de cola identifica un intento; RF12.5 se planifica en la persistencia.
    public int $tries = 1;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $despachoId, public readonly int $numero = 1) {}

    public function handle(DespacharOfertaAdelantoService $servicio): void
    {
        $servicio->despachar($this->despachoId, $this->numero ?? 1);
    }
}
