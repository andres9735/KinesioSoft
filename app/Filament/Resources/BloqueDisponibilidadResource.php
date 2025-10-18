<?php

namespace App\Filament\Resources;

use App\Models\User;
use App\Models\BloqueDisponibilidad;
use Filament\Facades\Filament;
use App\Filament\Resources\BloqueDisponibilidadResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class BloqueDisponibilidadResource extends Resource
{
    protected static ?string $model = BloqueDisponibilidad::class;

    protected static ?string $navigationGroup  = 'Turnos y Consultas';
    protected static ?string $navigationIcon   = 'heroicon-o-clock';
    protected static ?string $navigationLabel  = 'Bloques de disponibilidad';
    protected static ?string $modelLabel       = 'bloque de disponibilidad';
    protected static ?string $pluralModelLabel = 'bloques de disponibilidad';
    protected static ?int    $navigationSort   = 1;

    /** =========================================================
     * Helper tipado para obtener el usuario autenticado en Filament
     * ========================================================= */
    protected static function user(): ?User
    {
        /** @var User|null $u */
        $u = Filament::auth()->user();
        return $u;
    }

    /** =========================================================
     * Visibilidad: solo roles Kinesiologa y Administrador
     * ========================================================= */
    public static function canViewAny(): bool
    {
        $u = static::user();
        return $u && $u->hasAnyRole(['Kinesiologa', 'Administrador']);
    }

    /** =========================================================
     * Verifica solapamientos entre bloques de disponibilidad
     * ========================================================= */
    protected static function hasOverlap(array $data, ?int $excludeId = null): bool
    {
        if (
            empty($data['profesional_id']) ||
            !isset($data['dia_semana']) ||
            empty($data['hora_desde']) ||
            empty($data['hora_hasta'])
        ) {
            return false;
        }

        return BloqueDisponibilidad::query()
            ->where('profesional_id', $data['profesional_id'])
            ->where('dia_semana', $data['dia_semana'])
            ->where(function ($q) use ($data) {
                $q->where('hora_desde', '<', $data['hora_hasta'])
                    ->where('hora_hasta', '>', $data['hora_desde']);
            })
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    /** =========================================================
     * Formulario
     * ========================================================= */
    public static function form(Form $form): Form
    {
        return $form->schema([
            /** PROFESIONAL */
            Forms\Components\Select::make('profesional_id')
                ->label('Profesional')
                ->relationship('profesional', 'name')
                ->searchable()
                ->visible(fn() => static::user()?->hasRole('Administrador') === true)
                ->required(fn() => static::user()?->hasRole('Administrador') === true),

            Forms\Components\Hidden::make('profesional_id')
                ->default(fn() => static::user()?->id)
                ->dehydrated(true)
                ->visible(fn() => static::user()?->hasRole('Administrador') === false),

            /** CONSULTORIO */
            Forms\Components\Select::make('consultorio_id')
                ->label('Consultorio')
                ->relationship('consultorio', 'nombre')
                ->searchable()
                ->nullable(),

            /** DÍA + HORAS + DURACIÓN + ACTIVO */
            Forms\Components\Select::make('dia_semana')
                ->label('Día de la semana')
                ->options([
                    0 => 'Domingo',
                    1 => 'Lunes',
                    2 => 'Martes',
                    3 => 'Miércoles',
                    4 => 'Jueves',
                    5 => 'Viernes',
                    6 => 'Sábado',
                ])
                ->required(),

            Forms\Components\TimePicker::make('hora_desde')
                ->label('Hora desde')
                ->seconds(false)
                ->required(),

            Forms\Components\TimePicker::make('hora_hasta')
                ->label('Hora hasta')
                ->seconds(false)
                ->required(),

            Forms\Components\TextInput::make('duracion_minutos')
                ->numeric()
                ->label('Duración (minutos)')
                ->default(45)
                ->required(),

            Forms\Components\Toggle::make('activo')
                ->label('Activo')
                ->default(true),
        ])->columns(2);
    }

    /** =========================================================
     * Crear: asignar profesional y validar solapes/rangos
     * ========================================================= */
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $u = static::user();

        if ($u?->hasRole('Kinesiologa') || empty($data['profesional_id'])) {
            $data['profesional_id'] = $u?->id;
        }

        if (empty($data['profesional_id'])) {
            throw ValidationException::withMessages([
                'profesional_id' => 'Debe seleccionar un profesional.',
            ]);
        }

        if (($data['hora_hasta'] ?? null) <= ($data['hora_desde'] ?? null)) {
            throw ValidationException::withMessages([
                'hora_hasta' => 'La hora hasta debe ser mayor que la hora desde.',
            ]);
        }

        if (static::hasOverlap($data)) {
            throw ValidationException::withMessages([
                'hora_desde' => 'El tramo se solapa con otro existente para este día y profesional.',
                'hora_hasta' => 'El tramo se solapa con otro existente para este día y profesional.',
            ]);
        }

        return $data;
    }

    /** =========================================================
     * Guardar (editar): mismo control, excluyendo registro actual
     * ========================================================= */
    public static function mutateFormDataBeforeSave(array $data): array
    {
        $u = static::user();

        if ($u?->hasRole('Kinesiologa') || empty($data['profesional_id'])) {
            $data['profesional_id'] = $u?->id;
        }

        if (empty($data['profesional_id'])) {
            throw ValidationException::withMessages([
                'profesional_id' => 'Debe seleccionar un profesional.',
            ]);
        }

        if (($data['hora_hasta'] ?? null) <= ($data['hora_desde'] ?? null)) {
            throw ValidationException::withMessages([
                'hora_hasta' => 'La hora hasta debe ser mayor que la hora desde.',
            ]);
        }

        $currentId = (int) request()->route('record');
        if (static::hasOverlap($data, $currentId)) {
            throw ValidationException::withMessages([
                'hora_desde' => 'El tramo se solapa con otro existente para este día y profesional.',
                'hora_hasta' => 'El tramo se solapa con otro existente para este día y profesional.',
            ]);
        }

        return $data;
    }

    /** =========================================================
     * Query: kinesiologa ve solo sus bloques, admin ve todos
     * ========================================================= */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $u = static::user();

        if ($u?->hasRole('Kinesiologa')) {
            $query->where('profesional_id', $u->id);
        }

        return $query;
    }

    /** =========================================================
     * Tabla
     * ========================================================= */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('profesional.name')
                    ->label('Profesional')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('consultorio.nombre')
                    ->label('Consultorio')
                    ->sortable(),

                Tables\Columns\TextColumn::make('dia_semana')
                    ->label('Día')
                    ->formatStateUsing(fn($state) => [
                        'Domingo',
                        'Lunes',
                        'Martes',
                        'Miércoles',
                        'Jueves',
                        'Viernes',
                        'Sábado',
                    ][$state] ?? $state),

                Tables\Columns\TextColumn::make('hora_desde')->label('Desde'),
                Tables\Columns\TextColumn::make('hora_hasta')->label('Hasta'),
                Tables\Columns\TextColumn::make('duracion_minutos')->label('Duración (min)'),
                Tables\Columns\IconColumn::make('activo')->boolean()->label('Activo'),
            ])
            ->defaultSort('dia_semana', 'asc')
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBloqueDisponibilidads::route('/'),
            'create' => Pages\CreateBloqueDisponibilidad::route('/crear'),
            'edit'   => Pages\EditBloqueDisponibilidad::route('/{record}/editar'),
        ];
    }
}
