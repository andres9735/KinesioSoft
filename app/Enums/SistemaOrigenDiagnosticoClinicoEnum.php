<?php

namespace App\Enums;

enum SistemaOrigenDiagnosticoClinicoEnum: string
{
    case CIE_10 = 'CIE-10';
    case CIF = 'CIF';
    case SNOMED = 'SNOMED';
    case INTERNO = 'INTERNO';
}
