<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\ChecksAdmin;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role as SpatieRole;

class UserResource extends Resource
{
    use ChecksAdmin;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon   = 'heroicon-o-user';
    protected static ?string $navigationGroup  = 'Usuarios';
    protected static ?string $navigationLabel  = 'Usuarios';
    protected static ?string $modelLabel       = 'usuario';
    protected static ?string $pluralModelLabel = 'usuarios';
    protected static ?int    $navigationSort   = 1;

    /** ---------- PERF: query base del resource ---------- */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'dni',
                'address',
                'specialty',
                'is_active',
                'last_login_at',
                'created_at',
                'updated_at',
            ])
            ->with(['roles:id,name'])
            ->withCount('roles');
    }

    /** ---------- Reglas especiales ---------- */
    public static function canDelete($record): bool
    {
        if ($record instanceof User && $record->hasRole('Administrador')) {
            return false;
        }
        // usa la verificación centralizada del trait
        return static::isAdmin();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    /** ---------- Form ---------- */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos de acceso')->schema([
                Forms\Components\TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')
                    ->label('Contraseña')->password()->revealable()
                    ->dehydrated(fn($state) => filled($state))
                    ->required(fn(string $operation) => $operation === 'create'),
            ])->columns(3),

            Forms\Components\Select::make('roles')
                ->label('Roles')
                ->relationship('roles', 'name')
                ->multiple()
                ->preload()
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    $names = UserResource::resolveRoleNamesFromState($state);
                    if (! in_array('Paciente', $names, true)) {
                        $set('phone', null);
                        $set('dni', null);
                        $set('address', null);
                    }
                    if (! in_array('Kinesiologa', $names, true)) {
                        $set('specialty', null);
                    }
                }),

            Forms\Components\Section::make('Perfil')->schema([
                Forms\Components\TextInput::make('phone')
                    ->label('Teléfono')->tel()->maxLength(30)
                    ->visible(fn(Get $get, ?User $record) =>
                    self::hasAnyRoleSelected($get, $record, ['Paciente']) || Auth::user()?->hasRole('Administrador'))
                    ->disabled(fn(Get $get, ?User $record) =>
                    ! self::hasAnyRoleSelected($get, $record, ['Paciente'])
                        && ! Auth::user()?->hasRole('Administrador'))
                    ->dehydrated(fn(Get $get, ?User $record) =>
                    self::hasAnyRoleSelected($get, $record, ['Paciente']) || Auth::user()?->hasRole('Administrador'))
                    ->helperText(self::helperIfMissing(['Paciente'], 'Disponible cuando el rol “Paciente” está seleccionado.'))
                    ->hintIcon('heroicon-m-information-circle'),

                Forms\Components\TextInput::make('dni')
                    ->label('DNI / Identificador')->maxLength(30)->unique(ignoreRecord: true)
                    ->visible(fn(Get $get, ?User $record) =>
                    self::hasAnyRoleSelected($get, $record, ['Paciente']) || Auth::user()?->hasRole('Administrador'))
                    ->disabled(fn(Get $get, ?User $record) =>
                    ! self::hasAnyRoleSelected($get, $record, ['Paciente'])
                        && ! Auth::user()?->hasRole('Administrador'))
                    ->dehydrated(fn(Get $get, ?User $record) =>
                    self::hasAnyRoleSelected($get, $record, ['Paciente']) || Auth::user()?->hasRole('Administrador'))
                    ->helperText(self::helperIfMissing(['Paciente'], 'Disponible cuando el rol “Paciente” está seleccionado.'))
                    ->hintIcon('heroicon-m-information-circle'),

                Forms\Components\TextInput::make('address')
                    ->label('Dirección')->maxLength(255)
                    ->visible(fn(Get $get, ?User $record) =>
                    self::hasAnyRoleSelected($get, $record, ['Paciente']) || Auth::user()?->hasRole('Administrador'))
                    ->disabled(fn(Get $get, ?User $record) =>
                    ! self::hasAnyRoleSelected($get, $record, ['Paciente'])
                        && ! Auth::user()?->hasRole('Administrador'))
                    ->dehydrated(fn(Get $get, ?User $record) =>
                    self::hasAnyRoleSelected($get, $record, ['Paciente']) || Auth::user()?->hasRole('Administrador'))
                    ->helperText(self::helperIfMissing(['Paciente'], 'Disponible cuando el rol “Paciente” está seleccionado.'))
                    ->hintIcon('heroicon-m-information-circle'),

                Forms\Components\Select::make('specialty')
                    ->label('Especialidad (Kinesióloga)')
                    ->native(false)->searchable()->preload()
                    ->options([
                        'Kinesiología Deportiva'  => 'Kinesiología Deportiva',
                        'Neurorehabilitación'     => 'Neurorehabilitación',
                        'Traumatológica'          => 'Traumatológica',
                        'Respiratoria'            => 'Respiratoria',
                        'Pediátrica'              => 'Pediátrica',
                        'Geriátrica'              => 'Geriátrica',
                        'Suelo pélvico'           => 'Suelo pélvico',
                        'Cardiorrespiratoria'     => 'Cardiorrespiratoria',
                    ])
                    ->placeholder('Selecciona una especialidad')
                    ->visible(fn(Get $get, ?User $record) =>
                    self::hasAnyRoleSelected($get, $record, ['Kinesiologa']) || Auth::user()?->hasRole('Administrador'))
                    ->disabled(fn(Get $get, ?User $record) =>
                    ! self::hasAnyRoleSelected($get, $record, ['Kinesiologa'])
                        && ! Auth::user()?->hasRole('Administrador'))
                    ->dehydrated(fn(Get $get, ?User $record) =>
                    self::hasAnyRoleSelected($get, $record, ['Kinesiologa']) || Auth::user()?->hasRole('Administrador'))
                    ->required(fn(Get $get, ?User $record) =>
                    self::hasAnyRoleSelected($get, $record, ['Kinesiologa']))
                    ->validationMessages([
                        'required' => 'La especialidad es obligatoria cuando el rol “Kinesiologa” está seleccionado.',
                    ])
                    ->helperText(self::helperIfMissing(['Kinesiologa'], 'Disponible cuando el rol “Kinesiologa” está seleccionado.'))
                    ->hintIcon('heroicon-m-information-circle'),

                Forms\Components\Toggle::make('is_active')->label('Activo')->inline(false)->default(true),

                Forms\Components\Placeholder::make('last_login_at')
                    ->label('Último login')
                    ->content(fn(?User $record) => $record?->last_login_at?->format('d/m/Y H:i') ?? '—'),
            ])->columns(3),
        ]);
    }

    /** ---------- Table ---------- */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('roles_list')
                    ->label('Roles')
                    ->state(fn(User $record) => $record->roles->pluck('name')->join(', '))
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('specialty')
                    ->label('Especialidad')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Último login')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos')
                    ->queries(
                        true: fn($q) => $q->where('is_active', true),
                        false: fn($q) => $q->where('is_active', false),
                        blank: fn($q) => $q,
                    ),

                Tables\Filters\SelectFilter::make('role')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /** ---------- Helpers internos ---------- */

    protected static function hasAnyRoleSelected(Get $get, ?User $record, array $roles): bool
    {
        $selected = $get('roles') ?? [];
        $selectedNames = self::resolveRoleNamesFromState($selected);
        if ($selectedNames === [] && $record) {
            $selectedNames = $record->roles->pluck('name')->all();
        }
        return count(array_intersect($roles, $selectedNames)) > 0;
    }

    public static function sanitizeProfileData(array $data, ?User $record = null): array
    {
        // Si el que edita es Administrador, no forzamos nulls ni recortes.
        if (Auth::user()?->hasRole('Administrador')) {
            return $data;
        }

        // Determinar roles “efectivos”: los que vienen del form o, si no llegaron,
        // los que ya tiene el registro (útil en edición).
        $roleIdsOrNames = $data['roles'] ?? [];
        $names = self::resolveRoleNamesFromState($roleIdsOrNames);
        if ($names === [] && $record) {
            $names = $record->roles->pluck('name')->all();
        }

        // Si NO es Paciente, NO sobreescribas phone/dni/address -> simplemente no los toques.
        if (! in_array('Paciente', $names, true)) {
            unset($data['phone'], $data['dni'], $data['address']);
        }

        // Si NO es Kinesiologa, NO sobreescribas specialty -> no lo toques.
        if (! in_array('Kinesiologa', $names, true)) {
            unset($data['specialty']);
        }

        return $data;
    }

    protected static function resolveRoleNamesFromState(null|array $state): array
    {
        $state = $state ?? [];
        if ($state === []) return [];
        if (! is_numeric($state[0] ?? null)) {
            return array_values(array_filter(array_map('strval', $state)));
        }
        return SpatieRole::query()->whereIn('id', $state)->pluck('name')->all();
    }

    protected static function helperIfMissing(array $roles, string $text): \Closure
    {
        return function (Get $get, ?User $record) use ($roles, $text) {
            return self::hasAnyRoleSelected($get, $record, $roles) ? null : $text;
        };
    }

    protected static function helperIfMissingExceptAdmin(array $roles, string $text): \Closure
    {
        return function (Get $get, ?User $record) use ($roles, $text) {
            if (Auth::user()?->hasRole('Administrador')) return null;
            return self::hasAnyRoleSelected($get, $record, $roles) ? null : $text;
        };
    }
}
