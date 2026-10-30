<?php

namespace App\Exceptions\Turnos;

use RuntimeException;

final class ConfirmacionAsistenciaNoPermitidaException extends RuntimeException
{
    public function __construct(public readonly string $motivo)
    {
        parent::__construct(match ($motivo) {
            'inicio_no_futuro' => 'El turno ya alcanzó su horario de inicio.',
            'tipo_no_permitido' => 'El tipo de turno no permite confirmar asistencia.',
            default => 'El estado actual del turno no permite confirmar asistencia.',
        });
    }
}
