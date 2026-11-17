<?php

namespace App\Jobs;

use App\Models\DespachoOfertaAdelanto;
use App\Models\OfertaAdelanto;
use App\Services\Turnos\CerrarOfertaAdelantoService;
use App\Services\Turnos\ContinuarCupoAdelantoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class RevisarCicloOfertaAdelantoJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $ofertaId) {}

    public function handle(CerrarOfertaAdelantoService $cierres, ContinuarCupoAdelantoService $continuacion): void
    {
        $despachoAnterior = DespachoOfertaAdelanto::query()->useWritePdo()->where('oferta_id', $this->ofertaId)->first();
        $motivo = $despachoAnterior !== null && in_array($despachoAnterior->estado, ['FALLIDO', 'OMITIDO'], true)
            ? OfertaAdelanto::FALLO_ENVIO : OfertaAdelanto::VENCIDA;
        $salida = $cierres->cerrar($this->ofertaId, $motivo);
        if ($salida !== null) {
            $salida = $continuacion->continuar($salida->id);
            if ($salida->oferta_siguiente_id !== null) {
                $despacho = DespachoOfertaAdelanto::query()->where('oferta_id', $salida->oferta_siguiente_id)->where('estado', 'PENDIENTE')->first();
                if ($despacho !== null) {
                    DespacharOfertaAdelantoJob::dispatch($despacho->id)->onConnection('database')->afterCommit();
                }
            }
        }
    }
}
