<?php

namespace App\Filament\Kinesiologa\Widgets;

use App\Models\BloqueDisponibilidad;
use App\Models\ExcepcionDisponibilidad;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class BloquesActivosHoy extends BaseWidget
{
    protected ?string $heading = 'Resumen de hoy';

    protected function getStats(): array
    {
        $u   = Auth::user();
        $dow = Carbon::now()->dayOfWeek; // 0=Domingo .. 6=Sábado
        $hoy = Carbon::today();

        // Bloques activos hoy
        $bloquesHoy = BloqueDisponibilidad::query()
            ->where('profesional_id', $u->id)
            ->where('dia_semana', $dow)
            ->where('activo', true)
            ->get();

        // Total de minutos disponibles hoy
        $minutos = $bloquesHoy->sum(function ($b) {
            $desde = Carbon::createFromFormat('H:i:s', $b->hora_desde);
            $hasta = Carbon::createFromFormat('H:i:s', $b->hora_hasta);
            return $hasta->diffInMinutes($desde);
        });

        // Excepciones de hoy y próximas
        $exHoy = ExcepcionDisponibilidad::query()
            ->where('profesional_id', $u->id)
            ->whereDate('fecha', $hoy)
            ->count();

        $exProx = ExcepcionDisponibilidad::query()
            ->where('profesional_id', $u->id)
            ->whereDate('fecha', '>=', $hoy->copy()->addDay())
            ->whereDate('fecha', '<=', $hoy->copy()->addDays(7))
            ->count();

        return [
            Stat::make('Bloques activos hoy', (string) $bloquesHoy->count())
                ->description('Tramos configurados para el día actual')
                ->icon('heroicon-o-clock'),

            Stat::make('Horas disponibles hoy', number_format($minutos / 60, 1) . ' h')
                ->description('Suma total de tramos de hoy')
                ->icon('heroicon-o-calendar'),

            Stat::make('Excepciones', "{$exHoy} hoy · {$exProx} próximos 7 días")
                ->description('Licencias, feriados o bloqueos próximos')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    public static function canView(): bool
    {
        $u = Auth::user();
        return $u && $u->hasAnyRole(['Kinesiologa', 'Administrador']);
    }
}
