<?php

namespace App\Exceptions\Turnos;

use RuntimeException;

final class AccesoEnlaceRechazadoException extends RuntimeException
{
    public function __construct(public readonly string $motivo = 'invalido')
    {
        parent::__construct('La autorización del enlace no permite esta solicitud.');
    }
}
