<?php

namespace App\Exceptions\Turnos;

use RuntimeException;
use Throwable;

final class AlmacenamientoDocumentalException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $violacionesPolitica
     * @param  array<string, Throwable>  $erroresEliminacion
     * @param  array<string, Throwable>  $erroresLiberacion
     */
    public function __construct(
        string $mensaje,
        ?Throwable $previous = null,
        public readonly array $violacionesPolitica = [],
        public readonly array $erroresEliminacion = [],
        public readonly array $erroresLiberacion = [],
    ) {
        parent::__construct($mensaje, 0, $previous);
    }

    /** @return list<string> */
    public function rutasNoEliminadas(): array
    {
        return array_keys($this->erroresEliminacion);
    }
}
