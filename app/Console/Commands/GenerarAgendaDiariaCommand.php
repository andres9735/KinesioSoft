<?php

namespace App\Console\Commands;

use App\Enums\ResultadoGeneracionAgendaEnum;
use App\Services\Agenda\GenerarAgendaDiariaService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

final class GenerarAgendaDiariaCommand extends Command
{
    protected $signature = 'agenda:generar-diaria {--fecha= : Fecha civil YYYY-MM-DD; por defecto hoy local}';

    protected $description = 'Prepara la jornada administrativa sin modificar turnos ni enviar comunicaciones.';

    public function handle(GenerarAgendaDiariaService $servicio): int
    {
        $fecha = null;
        if ($this->option('fecha') !== null) {
            try {
                $texto = (string) $this->option('fecha');
                $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $texto, config('app.timezone'));
                if ($fecha->format('Y-m-d') !== $texto) {
                    throw new \InvalidArgumentException;
                }
            } catch (Throwable) {
                $this->error('La fecha debe ser una fecha civil válida YYYY-MM-DD.');

                return self::INVALID;
            }
        }
        try {
            $resultado = $servicio->ejecutar($fecha);
        } catch (Throwable) {
            // No imprimir ni reportar excepciones con SQL, parámetros o datos sensibles.
            $this->error('No pudo completarse la generación. Consultá la bitácora de ejecuciones.');

            return self::FAILURE;
        }
        $this->info('Resultado de generación: '.$resultado->resultado->value);

        return $resultado->resultado === ResultadoGeneracionAgendaEnum::COMPLETADA ? self::SUCCESS : self::FAILURE;
    }
}
