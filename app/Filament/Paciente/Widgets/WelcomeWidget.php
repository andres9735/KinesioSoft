<?php

namespace App\Filament\Paciente\Widgets;

use Filament\Widgets\Widget;

class WelcomeWidget extends Widget
{
    // Vista Blade que va a renderizar
    protected static string $view = 'filament.paciente.widgets.welcome-widget';

    // Que ocupe todo el ancho del dashboard
    protected int|string|array $columnSpan = 'full';
}
