<?php

namespace App\Filament\Paciente\Pages;

use App\Filament\Paciente\Pages\SolicitarTurno;
use App\Models\User;
use App\Models\Turno;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Actions;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class MisTurnos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Mis turnos';
    protected static ?string $title           = 'Mis turnos';
    protected static ?string $navigationGroup = 'Turnos';
    protected static ?string $slug            = 'mis-turnos';
    protected static ?int    $navigationSort  = 20;

    /** Vista Blade */
    protected static string $view = 'filament.paciente.pages.mis-turnos';

    /** Restricción de acceso al panel Paciente */
    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();
        return $u?->hasAnyRole(['Paciente', 'Administrador']) ?? false;
    }

    /** ------------------------- Tabla ------------------------- */
    public function table(Table $table): Table
    {
        $user = Filament::auth()->user();

        return $table
            ->query($this->getTableQuery($user?->id))

            // Agrupación por fecha (sin mostrar 00:00:00)
            ->groups([
                Group::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y'),
            ])
            ->defaultGroup('fecha')

            // Orden por defecto (fecha asc, hora asc)
            ->defaultSort('fecha', 'asc')
            ->defaultSort('hora_desde', 'asc')

            // Paginación
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)

            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('hora_desde')
                    ->label('Desde')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('hora_hasta')
                    ->label('Hasta')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('profesional.name')
                    ->label('Profesional')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('consultorio.nombre')
                    ->label('Consultorio')
                    ->toggleable(),

                // Estado con helpers del modelo
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->icon(fn(string $state) => Turno::estadoIcon($state))
                    ->color(fn(string $state) => Turno::estadoColor($state))
                    ->sortable(),
            ])

            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        Turno::ESTADO_PENDIENTE        => 'Pendiente',
                        Turno::ESTADO_CONFIRMADO       => 'Confirmado',
                        Turno::ESTADO_CANCELADO        => 'Cancelado',
                        Turno::ESTADO_CANCELADO_TARDE  => 'Cancelado (tardío)',
                    ]),

                Tables\Filters\Filter::make('rango_fecha')
                    ->label('Rango de fechas')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $desde = $data['desde'] ?? null;
                        $hasta = $data['hasta'] ?? null;

                        if ($desde && $hasta) {
                            $query->whereBetween('fecha', [$desde, $hasta]);
                        } elseif ($desde) {
                            $query->whereDate('fecha', '>=', $desde);
                        } elseif ($hasta) {
                            $query->whereDate('fecha', '<=', $hasta);
                        }

                        return $query;
                    }),
            ])

            ->actions([
                // Confirmar turno
                Tables\Actions\Action::make('confirmar')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn(Turno $record) => $this->puedeConfirmar($record))
                    ->action(function (Turno $record) {
                        Gate::authorize('update', $record);

                        if (!$this->puedeConfirmar($record)) {
                            Notification::make()
                                ->title('No se puede confirmar este turno.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $record->update(['estado' => Turno::ESTADO_CONFIRMADO]);

                        Notification::make()
                            ->title('Turno confirmado')
                            ->success()
                            ->send();
                    }),

                // Cancelar turno (decide normal vs tardía)
                Tables\Actions\Action::make('cancelar')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    // visible si NO está cancelado y el turno todavía no empezó
                    ->visible(fn(Turno $record) => $record->inicio?->isFuture() && ! $record->esCancelado() && ! $record->esCanceladoTarde())
                    ->tooltip(function (Turno $record) {
                        $lead = (int) config('turnos.cancel_min_minutes', 1440);
                        if (! $record->inicio) return null;

                        $limite = $record->inicio->copy()->subMinutes($lead)->format('d/m H:i');
                        return "Cancelación sin penalidad hasta $limite. Luego se registra como cancelación tardía.";
                    })
                    ->form([
                        Textarea::make('motivo')
                            ->label('Motivo (opcional)')
                            ->maxLength(255)
                            ->rows(3),
                    ])
                    ->action(function (Turno $record, array $data) {
                        Gate::authorize('update', $record);

                        $ahora  = now();
                        $inicio = $record->inicio;

                        if (! $inicio || $ahora->gte($inicio)) {
                            Notification::make()
                                ->title('Este turno ya no puede cancelarse.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $leadMin = (int) config('turnos.cancel_min_minutes', 1440); // 24 h por defecto
                        $diffMin = $ahora->diffInMinutes($inicio, false);

                        $nuevoEstado = $diffMin >= $leadMin
                            ? Turno::ESTADO_CANCELADO
                            : Turno::ESTADO_CANCELADO_TARDE;

                        $record->update([
                            'estado' => $nuevoEstado,
                            'motivo' => $data['motivo'] ?? null,
                        ]);

                        Notification::make()
                            ->title($nuevoEstado === Turno::ESTADO_CANCELADO ? 'Turno cancelado' : 'Turno cancelado (tardío)')
                            ->success()
                            ->send();
                    }),
            ])

            ->headerActions([
                Actions\Action::make('solicitar_turno')
                    ->label('Solicitar nuevo turno')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(SolicitarTurno::getUrl()),
            ])

            ->bulkActions([]); // sin acciones masivas para pacientes
    }

    /** Query base: sólo turnos del paciente actual */
    protected function getTableQuery(?int $pacienteId): Builder
    {
        return Turno::query()
            ->when($pacienteId, fn(Builder $q) => $q->where('paciente_id', $pacienteId));
    }

    /**
     * ✅ Confirmar: pendiente + futuro + respeta confirm_min_minutes
     */
    protected function puedeConfirmar(Turno $turno): bool
    {
        $ahora      = Carbon::now();
        $inicio     = Carbon::parse($turno->fecha->toDateString() . ' ' . $turno->getRawOriginal('hora_desde'));
        $minAntic   = (int) (config('turnos.confirm_min_minutes') ?? 0); // p.ej. 180

        if ($turno->estado !== Turno::ESTADO_PENDIENTE) {
            return false;
        }

        // minutos hasta el inicio (negativo si ya empezó)
        $diff = $ahora->diffInMinutes($inicio, false);

        return $diff >= $minAntic;
    }

    /**
     * (Opcional) Si en algún otro lugar la usás,
     * dejala simple: futuro y no cancelado (la acción ya decide normal vs tardía).
     */
    protected function puedeCancelar(Turno $turno): bool
    {
        $ahora  = Carbon::now();
        $inicio = $turno->inicio;

        if ($turno->esCancelado() || $turno->esCanceladoTarde()) {
            return false;
        }

        return $inicio?->greaterThan($ahora) ?? false;
    }
}
