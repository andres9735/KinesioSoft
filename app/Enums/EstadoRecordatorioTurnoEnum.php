<?php

namespace App\Enums;

/** Estado técnico de comunicación; nunca un estado de asistencia. */
enum EstadoRecordatorioTurnoEnum: string
{
    case PENDIENTE = 'PENDIENTE';
    case PROCESADO = 'PROCESADO';
    case FALLIDO = 'FALLIDO';
    case INCIERTO = 'INCIERTO';
}
