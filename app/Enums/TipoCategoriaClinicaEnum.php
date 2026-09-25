<?php

namespace App\Enums;

enum TipoCategoriaClinicaEnum: string
{
    case ANATOMICA = 'ANATOMICA';
    case FUNCIONAL = 'FUNCIONAL';
    case TERAPEUTICA = 'TERAPEUTICA';
    case EQUIPAMIENTO = 'EQUIPAMIENTO';
}
