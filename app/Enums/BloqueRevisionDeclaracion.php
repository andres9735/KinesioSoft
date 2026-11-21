<?php

namespace App\Enums;

enum BloqueRevisionDeclaracion: string
{
    case ANTECEDENTES = 'antecedentes';
    case CIRUGIAS = 'cirugias';
    case ALERGIAS = 'alergias';
    case MEDICACION = 'medicacion';
    case OBJETIVO = 'objetivo_salud';
    case PESO = 'peso_kg';
    case ALTURA = 'altura_cm';
    case OCUPACION = 'ocupacion';
    case ACTIVIDAD = 'nivel_actividad_fisica';
    case DETALLE_ACTIVIDAD = 'detalle_actividad_fisica';
}
