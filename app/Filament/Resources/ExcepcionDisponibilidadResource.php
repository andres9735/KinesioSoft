<?php

namespace App\Filament\Resources;

use App\Models\User;
use App\Models\ExcepcionDisponibilidad;
use Filament\Facades\Filament;
use App\Filament\Resources\ExcepcionDisponibilidadResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ExcepcionDisponibilidadResource extends Resource
{
    protected static ?string $model = ExcepcionDisponibilidad::class;

    protected static ?string $navigationGroup  = 'Turnos y Consultas';
    protected static ?string $navigationIcon   = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel  = 'Excepciones de disponibilidad';
    protected static ?string $modelLabel       = 'excepción de disponibilidad';
    protected static ?string $pluralModelLabel = 'excepciones de disponibilidad';
    protected static ?int    $navigationSort   = 2;

    /** ============================
     * Helper tipado para el usuario
     * ============================ */
    protected static function user(): ?User
    {
        /** @var User|null $u */
        $u = Filament::auth()->user();
        return $u;
    }

    public static function shouldRegisterNavigation(): bool
    {
        // No mostrar este recurso en ningún panel
        return false;
    }

    // Nadie puede listar
    public static function canViewAny(): bool
    {
        return false;
    }

    // Nadie puede ver un registro puntual
    public static function canView($record): bool
    {
        return false;
    }

    // Nadie puede crear
    public static function canCreate(): bool
    {
        return false;
    }

    // Nadie puede editar
    public static function canEdit($record): bool
    {
        return false;
    }

    // Nadie puede borrar
    public static function canDelete($record): bool
    {
        return false;
    }


    /** ============================
     * Formulario
     * ============================ */
    public static function form(Form $form): Form
    {
        return $form->schema([
            /** PROFESIONAL */
            Forms\Components\Select::make('profesional_id')
                ->label('Profesional')
                ->relationship('profesional', 'name')
                ->searchable()
                ->required(fn() => static::user()?->hasRole('Administrador') === true)
                ->visible(fn() => static::user()?->hasRole('Administrador') === true),

            Forms\Components\Hidden::make('profesional_id')
                ->default(fn() => static::user()?->id)
                ->dehydrated(true)
                ->visible(fn() => static::user()?->hasRole('Administrador') === false),

            /** FECHA + BLOQUEO/DURACIÓN */
            Forms\Components\DatePicker::make('fecha')
                ->label('Fecha')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->required(),

            Forms\Components\Toggle::make('bloqueado')
                ->label('Bloquea todo el día')
                ->default(true)
                ->inline(false),

            Forms\Components\TimePicker::make('hora_desde')
                ->label('Hora desde')
                ->seconds(false)
                ->visible(fn(Get $get) => ! $get('bloqueado')),

            Forms\Components\TimePicker::make('hora_hasta')
                ->label('Hora hasta')
                ->seconds(false)
                ->visible(fn(Get $get) => ! $get('bloqueado')),

            Forms\Components\TextInput::make('motivo')
                ->label('Motivo')
                ->maxLength(150)
                ->placeholder('Congreso, licencia, turno médico, feriado, etc.'),
        ])->columns(2);
    }

    /** ============================
     * Tabla
     * ============================ */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('profesional.name')
                    ->label('Profesional')
                    ->searchable()
                    ->sortable(),

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
            ->defaultSort('fecha', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /** ============================
     * Filtro del listado por rol
     * ============================ */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $u = static::user();

        if ($u?->hasRole('Kinesiologa')) {
            $query->where('profesional_id', $u->id);
        }

        return $query;
    }

    // ======= Helper de solapamientos =======
    /**
     * Determina si la excepción se solapa con otra existente para el mismo
     * profesional y fecha. Si $data['bloqueado']==true, cualquier registro ese día
     * es un solape. Si es parcial, choca con día completo o con otro parcial que
     * cumpla: start < end_existente && end > start_existente.
     */
    protected static function hasOverlap(array $data, ?int $excludeId = null): bool
    {
        if (empty($data['profesional_id']) || empty($data['fecha'])) {
            return false;
        }

        $base = ExcepcionDisponibilidad::query()
            ->where('profesional_id', $data['profesional_id'])
            ->whereDate('fecha', $data['fecha'])
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId));

        $bloqueado = (bool)($data['bloqueado'] ?? false);
        $desde     = $data['hora_desde'] ?? null;
        $hasta     = $data['hora_hasta'] ?? null;

        if ($bloqueado) {
            // Día completo: choca con cualquier excepción ese día.
            return $base->exists();
        }

        // Parcial: choca con un día completo o con otro parcial que se cruce
        return $base->where(function ($q) use ($desde, $hasta) {
            $q->where('bloqueado', 1)
                ->orWhere(function ($q2) use ($desde, $hasta) {
                    $q2->where('bloqueado', 0)
                        ->whereNotNull('hora_desde')
                        ->whereNotNull('hora_hasta')
                        ->where('hora_desde', '<', $hasta)
                        ->where('hora_hasta', '>', $desde);
                });
        })->exists();
    }
    // =======================================

    /**
     * Validación centralizada + autoasignación de profesional (kinesióloga).
     * - Si bloqueado = true => horas NULL
     * - Si bloqueado = false => horas requeridas y hasta > desde
     * - Evita solapes con otras excepciones del mismo día.
     */
    protected static function validateAndNormalize(array $data, ?int $excludeId = null): array
    {
        $u = static::user();
        if ($u?->hasRole('Kinesiologa')) {
            $data['profesional_id'] = $u->id;
        }

        $bloqueado = (bool)($data['bloqueado'] ?? false);
        $desde     = $data['hora_desde'] ?? null;
        $hasta     = $data['hora_hasta'] ?? null;

        if ($bloqueado) {
            $data['hora_desde'] = null;
            $data['hora_hasta'] = null;

            if (static::hasOverlap($data, $excludeId)) {
                throw ValidationException::withMessages([
                    'bloqueado' => 'Ya existe una excepción ese día para esta profesional.',
                ]);
            }

            return $data;
        }

        // Parcial: horas obligatorias y rango válido
        if (!$desde || !$hasta) {
            throw ValidationException::withMessages([
                'hora_desde' => 'Para una excepción parcial, las horas desde y hasta son obligatorias.',
            ]);
        }
        if ($hasta <= $desde) {
            throw ValidationException::withMessages([
                'hora_hasta' => 'La hora hasta debe ser mayor que la hora desde.',
            ]);
        }

        if (static::hasOverlap($data, $excludeId)) {
            throw ValidationException::withMessages([
                'hora_desde' => 'Este tramo se solapa con otra excepción de ese día.',
                'hora_hasta' => 'Este tramo se solapa con otra excepción de ese día.',
            ]);
        }

        return $data;
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        return static::validateAndNormalize($data, null);
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        $currentId = (int) request()->route('record');
        return static::validateAndNormalize($data, $currentId);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExcepcionDisponibilidads::route('/'),
            'create' => Pages\CreateExcepcionDisponibilidad::route('/crear'),
            'edit'   => Pages\EditExcepcionDisponibilidad::route('/{record}/editar'),
        ];
    }
}
