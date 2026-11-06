<?php

namespace App\Console\Commands;

use App\Services\Turnos\OrquestarRecordatoriosTurnoService;
use Illuminate\Console\Command;
use Throwable;

final class PrepararRecordatoriosTurnoCommand extends Command
{
    protected $signature = 'turnos:preparar-recordatorios';

    protected $description = 'Selecciona recordatorios durables sin enviar correos ni modificar turnos.';

    public function handle(OrquestarRecordatoriosTurnoService $servicio): int
    {
        try {
            $cantidad = $servicio->ejecutar();
        } catch (Throwable) {
            $this->error('No pudo completarse la preparación de recordatorios. Revisá las incidencias técnicas.');

            return self::FAILURE;
        }
        $this->info($cantidad === null ? 'Ciclo omitido por horario o ejecución en curso.' : 'Recordatorios seleccionados: '.$cantidad);

        return self::SUCCESS;
    }
}
