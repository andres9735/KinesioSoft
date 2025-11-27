<?php

namespace App\Enums;

enum EstadoDerivacion: string
{
    case Emitida = 'emitida';
    case Vigente = 'vigente';
    case Vencida = 'vencida';
    case Anulada = 'anulada';
}
