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
    protected static ?string $navigationGroup  = 'Usuarios y Acceso';
    protected static ?string $navigationLabel  = 'Auditoría';
    protected static ?string $modelLabel       = 'auditoría';
    protected static ?string $pluralModelLabel = 'auditorías';
    protected static ?int    $navigationSort   = 4;

    /** ---------- ACCESO (solo Admin) ---------- */
    protected static function isAdmin(): bool
    {
        /** @var User|null $u */
        $u = Auth::user();
        return $u instanceof User && $u->hasRole('Administrador');
    }

    public static function shouldRegisterNavigation(): bool
    {
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

    /** ---------- PERF: query base ---------- */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select([
                'id',
                'event',
                'auditable_type',
                'auditable_id',
                'user_type',
                'user_id',
                'ip_address',
                'url',
                'user_agent',
                'old_values',
                'new_values',
                'created_at',
            ])
            ->with(['user:id,name'])
            ->latest('created_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Cuándo ocurrió (indexado)
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                // Quién lo hizo (no searchable/sortable para evitar join)
                Tables\Columns\TextColumn::make('user_name')
                    ->label('Usuario')
                    ->state(fn(Audit $r) => $r->user?->name ?? '—')
                    ->placeholder('—'),

                // Evento
                Tables\Columns\TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->formatStateUsing(fn(?string $state): string => [
                        'created' => 'Creación',
                        'updated' => 'Actualización',
                        'deleted' => 'Eliminación',
                    ][$state] ?? ($state ?? '—'))
                    ->color(fn(?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default   => 'gray',
                    })
                    ->sortable(),

                // Modelo afectado
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Modelo')
                    ->formatStateUsing(fn(?string $state): string => $state ? class_basename($state) : '—')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('auditable_id')
                    ->label('ID')
                    ->sortable(),

                // Resumen de cambios (cuenta de claves)
                Tables\Columns\TextColumn::make('changes_count')
                    ->label('Cambios')
                    ->state(function (Audit $r) {
                        $old  = (array) $r->old_values;
                        $new  = (array) $r->new_values;
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

                // Filtro por modelo (usa índice auditable_type)
                Tables\Filters\SelectFilter::make('auditable_type')
                    ->label('Modelo')
                    ->options(
                        fn() => Audit::query()
                            ->select('auditable_type')
                            ->distinct()
                            ->orderBy('auditable_type')
                            ->pluck('auditable_type', 'auditable_type')
                            ->mapWithKeys(fn($t) => [$t => class_basename($t)])
                            ->toArray()
                    ),

                // Rango de fechas (usa índice created_at)
                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('desde')->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $q, array $data) {
                        if (!empty($data['desde'])) {
                            $q->whereDate('created_at', '>=', $data['desde']);
                        }
                        if (!empty($data['hasta'])) {
                            $q->whereDate('created_at', '<=', $data['hasta']);
                        }
                    }),

                // Filtro por usuario (sin join: user_id + user_type)
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->options(
                        fn() => User::query()
                            ->orderBy('name')
                            ->limit(200)
                            ->pluck('name', 'id')
                            ->toArray()
                    )
                    ->query(
                        fn(Builder $q, array $data) =>
                        !empty($data['value'])
                            ? $q->where('user_id', $data['value'])->where('user_type', User::class)
                            : $q
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Detalle de auditoría')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->form(function (Audit $record) {
                        return [
                            Forms\Components\Fieldset::make('Objeto')->schema([
                                Forms\Components\TextInput::make('modelo')
                                    ->label('Modelo')->disabled()->dehydrated(false)
                                    ->afterStateHydrated(fn($c) => $c->state(class_basename($record->auditable_type))),
                                Forms\Components\TextInput::make('id')
                                    ->label('ID')->disabled()->dehydrated(false)
                                    ->afterStateHydrated(fn($c) => $c->state($record->auditable_id)),
                                Forms\Components\TextInput::make('evento')
                                    ->label('Evento')->disabled()->dehydrated(false)
                                    ->afterStateHydrated(function ($c) use ($record) {
                                        $map = ['created' => 'Creación', 'updated' => 'Actualización', 'deleted' => 'Eliminación'];
                                        $c->state($map[$record->event] ?? $record->event);
                                    }),
                                Forms\Components\TextInput::make('usuario')
                                    ->label('Usuario')->disabled()->dehydrated(false)
                                    ->afterStateHydrated(fn($c) => $c->state($record->user?->name ?? '—')),
                                Forms\Components\TextInput::make('fecha')
                                    ->label('Fecha')->disabled()->dehydrated(false)
                                    ->afterStateHydrated(fn($c) => $c->state(optional($record->created_at)->format('d/m/Y H:i'))),
                            ])->columns(5),

                            Forms\Components\Textarea::make('old')
                                ->label('Valores anteriores')->rows(10)->disabled()->dehydrated(false)
                                ->afterStateHydrated(
                                    fn($c) => $c->state(json_encode($record->old_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                ),

                            Forms\Components\Textarea::make('new')
                                ->label('Valores nuevos')->rows(10)->disabled()->dehydrated(false)
                                ->afterStateHydrated(
                                    fn($c) => $c->state(json_encode($record->new_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                ),
                        ];
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAudits::route('/'),
        ];
    }
}
