<?php

namespace App\Actions\Turnos;

use App\Models\Turno;

final readonly class ResultadoCancelacionTurno
{
    public function __construct(public Turno $turno, public bool $esReplay) {}
}
