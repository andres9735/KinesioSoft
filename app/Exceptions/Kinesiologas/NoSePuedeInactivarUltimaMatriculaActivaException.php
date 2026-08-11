<?php

namespace App\Exceptions\Kinesiologas;

use RuntimeException;

final class NoSePuedeInactivarUltimaMatriculaActivaException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'No puede inactivar su última matrícula activa.'
        );
    }
}
