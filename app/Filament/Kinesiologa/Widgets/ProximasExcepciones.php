<?php

namespace App\Filament\Kinesiologa\Widgets;

use App\Models\ExcepcionDisponibilidad;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class ProximasExcepciones extends BaseWidget
{
    protected static ?string $heading = 'Próximas excepciones de disponibilidad';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $u   = Auth::user();
        $hoy = Carbon::today();

        return $table
            ->query(
                ExcepcionDisponibilidad::query()
                    ->where('profesional_id', $u->id)
                    ->whereDate('fecha', '>=', $hoy)
                    ->orderBy('fecha')
                    ->limit(10)
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\IconColumn::make('bloqueado')
                    ->label('Día completo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('hora_desde')
                    ->label('Desde')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('hora_hasta')
                    ->label('Hasta')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('motivo')
                    ->label('Motivo')
                    ->limit(40),
            ])
            ->emptyStateHeading('Sin excepciones próximas')
            ->emptyStateDescription('No hay bloqueos programados a futuro.');
    }

    public static function canView(): bool
    {
        $u = Auth::user();
        return $u && $u->hasAnyRole(['Kinesiologa', 'Administrador']);
    }
}
