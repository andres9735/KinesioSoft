<?php

namespace App\Enums;

/** Códigos controlados: nunca persistir mensajes de excepción, SQL o datos clínicos. */
enum IncidenciaGeneracionAgendaEnum: string
{
    case SELECCION_PROFESIONALES = 'SELECCION_PROFESIONALES';
    case PREPARACION_JORNADA = 'PREPARACION_JORNADA';
    case EJECUCION_INTERRUMPIDA = 'EJECUCION_INTERRUMPIDA';
}
