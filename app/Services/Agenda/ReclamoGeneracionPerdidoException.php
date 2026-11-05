<?php

namespace App\Services\Agenda;

use RuntimeException;

final class ReclamoGeneracionPerdidoException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La ejecución ya no posee un reclamo vigente sobre la jornada.');
    }
}
