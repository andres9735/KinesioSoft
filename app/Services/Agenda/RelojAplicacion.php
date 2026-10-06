<?php

namespace App\Services\Agenda;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class RelojAplicacion
{
    public function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now($this->zonaHoraria());
    }

    public function hoy(?CarbonInterface $ahora = null): CarbonImmutable
    {
        $instante = $ahora === null
            ? $this->ahora()
            : CarbonImmutable::instance($ahora)->setTimezone($this->zonaHoraria());

        return $instante->startOfDay();
    }

    private function zonaHoraria(): string
    {
        return (string) config('app.timezone');
    }
}
