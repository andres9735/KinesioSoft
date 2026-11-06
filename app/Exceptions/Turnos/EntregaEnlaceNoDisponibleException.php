<?php

namespace App\Exceptions\Turnos;

use RuntimeException;

final class EntregaEnlaceNoDisponibleException extends RuntimeException
{
    public function __construct(public readonly string $motivo)
    {
        parent::__construct('El material de entrega no está disponible para esta operación.');
    }
}
