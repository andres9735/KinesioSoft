<?php

namespace App\Jobs;

use App\Services\Turnos\DespacharConfirmacionAdelantoService;
use App\Services\Turnos\PrepararConfirmacionAdelantoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class EnviarConfirmacionAdelantoJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $aceptacionId) {}

    public function handle(PrepararConfirmacionAdelantoService $preparador, DespacharConfirmacionAdelantoService $despachador): void
    {
        $despacho = $preparador->preparar($this->aceptacionId);
        $despachador->despachar($despacho->id);
    }
}
