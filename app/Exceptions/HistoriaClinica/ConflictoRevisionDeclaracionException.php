<?php

namespace App\Exceptions\HistoriaClinica;

use RuntimeException;

final class ConflictoRevisionDeclaracionException extends RuntimeException
{
    public function __construct(public readonly string $motivo = 'contexto')
    {
        parent::__construct('La revisión requiere recuperar el expediente y generar una nueva vista previa.');
    }
}
