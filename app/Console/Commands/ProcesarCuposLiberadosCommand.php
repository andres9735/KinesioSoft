<?php

namespace App\Console\Commands;

use App\Jobs\DespacharOfertaAdelantoJob;
use App\Jobs\EnviarConfirmacionAdelantoJob;
use App\Jobs\ProcesarIntencionCupoJob;
use App\Jobs\RevisarCicloOfertaAdelantoJob;
use App\Models\DespachoOfertaAdelanto;
use App\Models\IntencionEvaluacionCupo;
use App\Models\OfertaAdelanto;
use App\Models\SalidaOfertaAdelanto;
use App\Services\Agenda\RelojAplicacion;
use Illuminate\Console\Command;
use Throwable;

final class ProcesarCuposLiberadosCommand extends Command
{
    protected $signature = 'turnos:procesar-cupos';

    protected $description = 'Encola intenciones, cierres, continuaciones y entregas pendientes sin ejecutar búsqueda ni correo en el comando.';

    public function handle(RelojAplicacion $reloj): int
    {
        try {
            IntencionEvaluacionCupo::query()->useWritePdo()->whereNull('procesado_at')->select('id')->chunkById(100, function ($intenciones): void {
                foreach ($intenciones as $intencion) {
                    ProcesarIntencionCupoJob::dispatch($intencion->id)->onConnection('database')->afterCommit();
                }
            });
            OfertaAdelanto::query()->useWritePdo()->where(function ($query): void {
                $query->where('estado', OfertaAdelanto::ACTIVA)
                    ->orWhereIn('id', SalidaOfertaAdelanto::query()->where('continuacion', 'pendiente')->select('oferta_id'));
            })
                ->select('id')->chunkById(100, function ($ofertas): void {
                    foreach ($ofertas as $oferta) {
                        RevisarCicloOfertaAdelantoJob::dispatch($oferta->id)->onConnection('database')->afterCommit();
                    }
                });
            DespachoOfertaAdelanto::query()->useWritePdo()->where(function ($query) use ($reloj): void {
                $query->where('estado', 'PENDIENTE')->orWhere(function ($reintento) use ($reloj): void {
                    $reintento->where('estado', 'REINTENTO')->where('proximo_intento_at', '<=', $reloj->ahora()->format('Y-m-d H:i:s.u'));
                });
            })->select(['id', 'intentos'])->chunkById(100, function ($despachos): void {
                foreach ($despachos as $despacho) {
                    DespacharOfertaAdelantoJob::dispatch($despacho->id, $despacho->intentos + 1)->onConnection('database')->afterCommit();
                }
            });
            // Sólo referencias confirmadas: la preparación normal es atómica con el traslado.
            // También recupera aceptaciones anteriores sin fila de despacho.
            SalidaOfertaAdelanto::query()->useWritePdo()->where('resultado', OfertaAdelanto::ACEPTADA)
                ->where(function ($query): void {
                    $query->whereDoesntHave('confirmacion')
                        ->orWhereHas('confirmacion', fn ($confirmacion) => $confirmacion->where('estado', 'PENDIENTE'));
                })->select('id')->chunkById(100, function ($aceptaciones): void {
                    foreach ($aceptaciones as $aceptacion) {
                        EnviarConfirmacionAdelantoJob::dispatch($aceptacion->id)->onConnection('database')->afterCommit();
                    }
                });
        } catch (Throwable) {
            $this->error('No se pudo completar el encolado de cupos. Las referencias durables permiten recuperarlo.');

            return self::FAILURE;
        }
        $this->info('Intenciones y entregas pendientes encoladas.');

        return self::SUCCESS;
    }
}
