<?php

namespace App\Enums;

enum SistemaOrigenPruebaFuncionalEnum: string
{
    case INTERNO = 'INTERNO';
    case SNOMED = 'SNOMED';
    case LOINC = 'LOINC';
}
