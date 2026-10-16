<?php

namespace App\Services\Turnos\Documentos;

use Closure;
use LogicException;

final class BloqueoDocumental
{
    private bool $liberado = false;

    public function __construct(private readonly Closure $liberacion) {}

    public function liberar(): void
    {
        if ($this->liberado) {
            throw new LogicException('El bloqueo documental ya fue liberado.');
        }

        ($this->liberacion)();
        $this->liberado = true;
    }
}
