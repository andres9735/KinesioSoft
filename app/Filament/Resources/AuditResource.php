<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Models\Audit;

class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static ?string $navigationIcon   = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup  = 'Usuarios'; // si usás menú automático
    protected static ?string $navigationLabel  = 'Auditoría';
    protected static ?string $modelLabel       = 'auditoría';
    protected static ?string $pluralModelLabel = 'auditorías';
    protected static ?int    $navigationSort   = 2;

    /** Solo Administrador */
    protected static function isAdmin(): bool
    {
        /** @var User|null $u */
        $u = Auth::user();

        return $u instanceof User && $u->hasRole('Administrador');
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Si el menú lo construís manualmente en AdminPanelProvider, este return no afecta.
        return self::isAdmin();
    }

    public static function canViewAny(): bool
    {
        return self::isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Cuándo ocurrió
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),

                // Quién lo hizo
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                // Evento (sin deprecations)
                Tables\Columns\TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->formatStateUsing(fn($state) => [
                        'created' => 'Creación',
                        'updated' => 'Actualización',
                        'deleted' => 'Eliminación',
                    ][$state] ?? $state)
                    ->color(fn($state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default   => 'gray',
                    })
                    ->sortable(),

                // Modelo afectado
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Modelo')
                    ->formatStateUsing(fn($state) => class_basename($state))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('auditable_id')
                    ->label('ID')
                    ->sortable(),

                // Resumen de cambios (cantidad de atributos tocados)
                Tables\Columns\TextColumn::make('new_values')
                    ->label('Cambios')
                    ->formatStateUsing(function ($state, Audit $record) {
                        $old  = (array) $record->old_values;
                        $new  = (array) $record->new_values;
                        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));

                        return count($keys) ? count($keys) . ' campo(s)' : '—';
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user_agent')
                    ->label('Agente')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label('Evento')
                    ->options([
                        'created' => 'Creación',
                        'updated' => 'Actualización',
                        'deleted' => 'Eliminación',
                    ]),

                Tables\Filters\SelectFilter::make('modelo')
                    ->label('Modelo')
                    ->options(
                        fn() => Audit::query()
                            ->select('auditable_type')
                            ->distinct()
                            ->pluck('auditable_type')
                            ->mapWithKeys(fn($t) => [$t => class_basename($t)])
                            ->toArray()
                    )
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->where('auditable_type', $data['value']);
                        }
                    }),

                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('desde')->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['desde'])) {
                            $query->whereDate('created_at', '>=', $data['desde']);
                        }
                        if (!empty($data['hasta'])) {
                            $query->whereDate('created_at', '<=', $data['hasta']);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Detalle de auditoría')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->form(function (Audit $record) {
                        return [
                            Forms\Components\Fieldset::make('Objeto')
                                ->schema([
                                    Forms\Components\TextInput::make('modelo')
                                        ->label('Modelo')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->afterStateHydrated(function ($component) use ($record) {
                                            $component->state(class_basename($record->auditable_type));
                                        }),

                                    Forms\Components\TextInput::make('id')
                                        ->label('ID')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->afterStateHydrated(function ($component) use ($record) {
                                            $component->state($record->auditable_id);
                                        }),

                                    Forms\Components\TextInput::make('evento')
                                        ->label('Evento')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->afterStateHydrated(function ($component) use ($record) {
                                            $map = [
                                                'created' => 'Creación',
                                                'updated' => 'Actualización',
                                                'deleted' => 'Eliminación',
                                            ];
                                            $component->state($map[$record->event] ?? $record->event);
                                        }),

                                    Forms\Components\TextInput::make('usuario')
                                        ->label('Usuario')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->afterStateHydrated(function ($component) use ($record) {
                                            $component->state($record->user?->name ?? '—');
                                        }),

                                    Forms\Components\TextInput::make('fecha')
                                        ->label('Fecha')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->afterStateHydrated(function ($component) use ($record) {
                                            $component->state(optional($record->created_at)->format('d/m/Y H:i'));
                                        }),
                                ])->columns(5),

                            Forms\Components\Textarea::make('old')
                                ->label('Valores anteriores')
                                ->rows(10)
                                ->disabled()
                                ->dehydrated(false)
                                ->afterStateHydrated(function ($component) use ($record) {
                                    $component->state(json_encode($record->old_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                }),

                            Forms\Components\Textarea::make('new')
                                ->label('Valores nuevos')
                                ->rows(10)
                                ->disabled()
                                ->dehydrated(false)
                                ->afterStateHydrated(function ($component) use ($record) {
                                    $component->state(json_encode($record->new_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                }),
                        ];
                    }),
            ])

            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAudits::route('/'),
        ];
    }
}
