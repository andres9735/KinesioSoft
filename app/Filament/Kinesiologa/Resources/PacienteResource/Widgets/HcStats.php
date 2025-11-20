<?php

namespace App\Filament\Kinesiologa\Resources\PacienteResource\Widgets;

use App\Models\EntradaHc;
use App\Models\AntecedentePersonal;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HcStats extends BaseWidget
{
    // 👇 NO static
    protected ?string $heading = 'Resumen HC';
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make('Entradas de HC', (string) EntradaHc::count()),
            Stat::make('Antecedentes personales', (string) AntecedentePersonal::count()),
        ];
    }
}
