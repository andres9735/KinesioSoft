<?php

namespace App\Exceptions\Turnos;

use RuntimeException;

final class CancelacionTurnoNoPermitidaException extends RuntimeException
{
    public function __construct(public readonly string $motivo)
    {
        parent::__construct(match ($motivo) {
            'fuera_de_plazo' => 'La cancelación online requiere al menos 12 horas de anticipación. Comunicate con el consultorio.',
            'tipo_no_permitido' => 'El tipo de turno no permite esta cancelación.',
            default => 'El estado actual del turno no permite esta cancelación.',
        });
    }
}
