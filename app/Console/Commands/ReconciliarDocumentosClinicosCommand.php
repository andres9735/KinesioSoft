<?php

namespace App\Console\Commands;

use App\Services\Turnos\ReconciliadorDocumentosClinicosService;
use Illuminate\Console\Command;

final class ReconciliarDocumentosClinicosCommand extends Command
{
    protected $signature = 'documentos-clinicos:reconciliar-huerfanos
                            {--delete : Eliminar candidatos seguros; sin esta opción sólo simula (dry-run)}';

    protected $description = 'Reconcilia archivos clínicos huérfanos. Dry-run por defecto, sin eliminar archivos.';

    public function handle(ReconciliadorDocumentosClinicosService $servicio): int
    {
        $resultado = $servicio->reconciliar((bool) $this->option('delete'));
        $this->info($resultado->dryRun ? 'Modo: dry-run (sin eliminación)' : 'Modo: eliminación explícita');
        $this->table(['Clasificación', 'Cantidad'], collect($resultado->conteos)->map(fn ($cantidad, $nombre) => [$nombre, $cantidad])->values()->all());
        foreach ($resultado->errores as $error) {
            $this->error('Reconciliación interrumpida: '.$error);
        }

        return $resultado->exitoso() ? self::SUCCESS : self::FAILURE;
    }
}
