<?php

namespace App\Enums;

enum EstadoTurnoEnum: string
{
    case PENDIENTE = 'PENDIENTE';
    case CONFIRMADO = 'CONFIRMADO';
    case EN_ATENCION = 'EN_ATENCION';
    case ATENDIDO = 'ATENDIDO';
    case AUSENTE = 'AUSENTE';
    case CANCELADO = 'CANCELADO';
}
