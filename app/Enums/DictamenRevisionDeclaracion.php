<?php

namespace App\Enums;

enum DictamenRevisionDeclaracion: string
{
    case VALIDAR = 'VALIDAR';
    case CORREGIR = 'CORREGIR';
    case OBSERVAR = 'OBSERVAR';
    case DESCARTAR = 'DESCARTAR';

    public function requiereJustificacion(): bool
    {
        return $this === self::OBSERVAR || $this === self::DESCARTAR;
    }
}
