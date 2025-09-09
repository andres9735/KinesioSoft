<?php

namespace App\Filament\Paciente\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;   // 👈 para evitar el warning de intelephense
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UpcomingAppointments extends Widget
{
    protected static string $view = 'filament.paciente.widgets.upcoming-appointments';
    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $user = Auth::user(); // 👈 en vez de auth()->user() (mismo efecto, menos warning del IDE)

        // Mock de turnos (cámbialo por tu query real cuando tengas el modelo)
        $appointments = collect([
            [
                'fecha'        => Carbon::now()->addDays(1)->format('d/m/Y'),
                'hora'         => '10:30',
                'profesional'  => 'Lic. María Gómez',
                'lugar'        => 'Consultorio Central',
                'estado'       => 'Confirmado',
            ],
            [
                'fecha'        => Carbon::now()->addDays(3)->format('d/m/Y'),
                'hora'         => '14:00',
                'profesional'  => 'Lic. Juan López',
                'lugar'        => 'Sucursal Norte',
                'estado'       => 'Pendiente',
            ],
            [
                'fecha'        => Carbon::now()->addDays(7)->format('d/m/Y'),
                'hora'         => '09:15',
                'profesional'  => 'Lic. Ana Ruiz',
                'lugar'        => 'Consultorio Central',
                'estado'       => 'Confirmado',
            ],
        ]);

        return [
            'patientName'  => $user?->name ?? 'Paciente',
            'appointments' => $appointments,
        ];
    }
}
