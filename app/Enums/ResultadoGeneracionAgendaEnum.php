<?php

namespace App\Enums;

/** Estado técnico de procesamiento; no es un estado de Turno. */
enum ResultadoGeneracionAgendaEnum: string
{
    case EN_CURSO = 'EN_CURSO';
    case COMPLETADA = 'COMPLETADA';
    case FALLIDA = 'FALLIDA';
}
