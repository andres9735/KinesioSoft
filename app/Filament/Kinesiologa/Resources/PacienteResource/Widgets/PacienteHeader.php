<?php

namespace App\Filament\Kinesiologa\Resources\PacienteResource\Widgets;

use Filament\Widgets\Widget;

class PacienteHeader extends Widget
{
    // 👇 NO static
    protected ?string $heading = 'Gestión de Historia Clínica';

    // este sí es estático
    protected static string $view = 'filament.kinesiologa.pacientes.widgets.paciente-header';

    protected int|string|array $columnSpan = 'full';
}
