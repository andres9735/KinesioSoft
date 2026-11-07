<?php

namespace App\Jobs;

use App\Services\Turnos\DespacharRecordatorioTurnoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** La cola sólo contiene referencias. Nunca un Mailable, modelo, token o URL. */
final class DespacharRecordatorioTurnoJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $recordatorioId, public readonly string $identidadIntento, public readonly int $numeroIntento = 1)
    {
        if ($recordatorioId < 1 || ! Str::isUuid($identidadIntento) || $numeroIntento < 1 || $numeroIntento > 4) {
            throw new InvalidArgumentException('El trabajo requiere referencias técnicas válidas.');
        }
    }

    public function handle(DespacharRecordatorioTurnoService $servicio): void
    {
        // Los payloads de 6.34 no incluían la posición: siempre eran el intento inicial.
        $servicio->despachar($this->recordatorioId, $this->identidadIntento, $this->numeroIntento ?? 1);
    }
}
