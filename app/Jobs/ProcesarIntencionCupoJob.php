<?php

namespace App\Jobs;

use App\Models\DespachoOfertaAdelanto;
use App\Services\Turnos\ProcesarIntencionCupoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class ProcesarIntencionCupoJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $intencionId) {}

    public function handle(ProcesarIntencionCupoService $servicio): void
    {
        $evaluacion = $servicio->procesar($this->intencionId);
        $despacho = DespachoOfertaAdelanto::query()->useWritePdo()->where('oferta_id', $evaluacion->oferta_id)
            ->where('estado', 'PENDIENTE')->first();
        if ($despacho !== null) {
            DespacharOfertaAdelantoJob::dispatch($despacho->id)->onConnection('database')->afterCommit();
        }
    }
}
