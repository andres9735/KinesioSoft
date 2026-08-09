<?php

namespace App\Exceptions\Usuarios;

use RuntimeException;

final class NoSePuedeDesactivarUnicoSuperadminException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'No se puede desactivar al único superadmin activo.'
        );
    }
}
