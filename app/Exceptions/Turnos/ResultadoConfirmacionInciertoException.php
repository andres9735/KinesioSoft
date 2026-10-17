<?php

namespace App\Exceptions\Turnos;

use RuntimeException;
use Throwable;

final class ResultadoConfirmacionInciertoException extends RuntimeException
{
    public function __construct(
        Throwable $previous,
        public readonly ?Throwable $errorVerificacion = null,
        public readonly ?Throwable $errorFinalizacion = null,
    ) {
        parent::__construct('No se pudo determinar la consolidación de la confirmación.', 0, $previous);
    }
}
