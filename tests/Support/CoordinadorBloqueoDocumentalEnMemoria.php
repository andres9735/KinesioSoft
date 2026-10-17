<?php

namespace Tests\Support;

use App\Services\Turnos\Documentos\BloqueoDocumental;
use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumental;
use RuntimeException;

final class CoordinadorBloqueoDocumentalEnMemoria implements CoordinadorBloqueoDocumental
{
    /** @var array<string, bool> */
    public array $activos = [];

    /** @var list<string> */
    public array $adquisiciones = [];

    /** @var list<string> */
    public array $liberaciones = [];

    public ?int $fallarAdquisicion = null;

    public ?string $fallarLiberacion = null;

    public function adquirir(string $ruta): BloqueoDocumental
    {
        return $this->intentarAdquirir($ruta)
            ?? throw new RuntimeException('Bloqueo de test no adquirido.');
    }

    public function intentarAdquirir(string $ruta): ?BloqueoDocumental
    {
        $this->adquisiciones[] = $ruta;

        if (count($this->adquisiciones) === $this->fallarAdquisicion) {
            throw new RuntimeException('Bloqueo de test no adquirido.');
        }

        if (isset($this->activos[$ruta])) {
            return null;
        }

        $this->activos[$ruta] = true;

        return new BloqueoDocumental(function () use ($ruta): void {
            unset($this->activos[$ruta]);
            $this->liberaciones[] = $ruta;

            if ($ruta === $this->fallarLiberacion) {
                throw new RuntimeException('Error representativo al confirmar la liberación.');
            }
        });
    }
}
