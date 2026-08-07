<?php

namespace App\Services;

use App\Models\Paciente;
use LogicException;

final class GeneradorNumeroHistoriaClinica
{
    private const PREFIJO = 'HC-';

    private const LONGITUD_NUMERO = 6;

    public function generar(Paciente $paciente): string
    {
        $id = $paciente->getKey();

        if (! $paciente->exists || $id === null) {
            throw new LogicException(
                'El paciente debe estar persistido antes de generar su número de historia clínica.'
            );
        }

        return self::PREFIJO . str_pad(
            (string) $id,
            self::LONGITUD_NUMERO,
            '0',
            STR_PAD_LEFT
        );
    }
}
