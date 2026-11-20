<?php

namespace App\Mail;

use Carbon\CarbonImmutable;

/** Formatos de presentación; no modifica la programación ni sus plazos. */
final class PresentacionTurno
{
    public static function horario(string $intervalo, ?string $zona = null): array
    {
        $inicio = explode('–', $intervalo, 2)[0];
        $formato = strlen($inicio) === 16 ? '!d/m/Y H:i' : '!d/m/Y H:i:s';
        $fecha = CarbonImmutable::createFromFormat($formato, $inicio, $zona ?? config('app.timezone'));

        return [
            'fecha' => ucfirst($fecha->locale('es')->translatedFormat('l d/m/Y')),
            'hora' => $fecha->format('H:i'),
        ];
    }

    public static function vencimiento(string $valor): string
    {
        return CarbonImmutable::createFromFormat('d/m/Y H:i:s.u P', $valor)
            ->setTimezone('America/Argentina/Buenos_Aires')->format('d/m/Y \\a \\l\\a\\s H:i');
    }
}
