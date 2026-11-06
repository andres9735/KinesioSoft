<?php

namespace App\Enums;

enum ResultadoIntentoRecordatorioEnum: string
{
    case INCIERTO = 'INCIERTO';
    case ACEPTADO = 'ACEPTADO';
    case ENTREGA_VERIFICADA = 'ENTREGA_VERIFICADA';
    case FALLO_RECUPERABLE = 'FALLO_RECUPERABLE';
    case FALLO_DEFINITIVO = 'FALLO_DEFINITIVO';
}
