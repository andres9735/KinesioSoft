<?php

namespace App\Exceptions\Kinesiologas;

use RuntimeException;

final class NoSePuedeReactivarMatriculaConCatalogoInactivoException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'No puede reactivar la matrícula profesional mientras su jurisdicción o colegio emisor se encuentren inactivos.'
        );
    }
}
