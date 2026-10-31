<?php

namespace Tests\Integration\Uc06MariaDb\Support;

use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Integration\MySql\Support\Barrera;

/** Cada worker recibe datos sintéticos por stdin; secretos sólo por entorno heredado. */
final class ProcesosUc06
{
    private array $procesos = [];

    public function __construct(public readonly Barrera $barrera) {}

    public function iniciar(string $nombre, string $script, array $entrada, array $entorno = [], float $timeout = 35): void
    {
        if (! preg_match('/\A[a-z]+\z/', $nombre) || isset($this->procesos[$nombre])) {
            throw new RuntimeException('Nombre de worker inválido o repetido.');
        }
        $proceso = new Process([PHP_BINARY, $script], dirname(__DIR__, 4), $entorno,
            json_encode($entrada + ['worker' => $nombre], JSON_THROW_ON_ERROR), $timeout);
        $this->procesos[$nombre] = $proceso;
        $proceso->start();
    }

    public function esperar(string $worker, string $evento, float $timeout = 12): array
    {
        return Barrera::hasta(function () use ($worker, $evento) {
            $dato = $this->barrera->leer($worker.'-'.$evento);
            if ($dato !== null) {
                return $dato;
            }
            $proceso = $this->procesos[$worker];
            $proceso->checkTimeout();
            if (! $proceso->isRunning()) {
                // Nunca volcar stdout/stderr arbitrario: podría contener un error de conexión.
                throw new RuntimeException('Worker finalizó sin publicar el evento requerido.');
            }

            return null;
        }, $timeout);
    }

    public function resultado(string $worker): array
    {
        $proceso = $this->procesos[$worker];
        $proceso->wait();
        $resultado = json_decode(trim($proceso->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        if (! in_array($proceso->getExitCode(), [0, 2], true)
            || ! is_array($resultado) || ! in_array($resultado['status'] ?? null, ['ok', 'error'], true)) {
            throw new RuntimeException('Worker fuera del protocolo estructurado.');
        }

        return $resultado;
    }

    public function activo(string $worker): bool
    {
        $this->procesos[$worker]->checkTimeout();

        return $this->procesos[$worker]->isRunning();
    }

    public function detener(): void
    {
        foreach ($this->procesos as $proceso) {
            if ($proceso->isRunning()) {
                $proceso->stop(0.2);
            }
        }
        foreach ($this->procesos as $proceso) {
            if ($proceso->isRunning()) {
                throw new RuntimeException('No se pudo detener un worker del escenario.');
            }
        }
    }
}
