<?php

namespace App\Enums;

enum RoleEnum: string
{
    case SUPERADMIN = 'superadmin';
    case ADMINISTRADOR = 'administrador';
    case KINESIOLOGO = 'kinesiologo';
    case PACIENTE = 'paciente';
}
