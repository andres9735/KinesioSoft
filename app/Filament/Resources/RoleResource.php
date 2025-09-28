<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Models\Role;
use App\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon  = 'heroicon-o-lock-closed';
    protected static ?string $navigationGroup = 'Usuarios y Acceso';
    protected static ?string $navigationLabel = 'Roles';
    protected static ?string $modelLabel       = 'rol';
    protected static ?string $pluralModelLabel = 'roles';
    protected static ?int    $navigationSort   = 2;

    /** ---------- PERF: query base del resource ---------- */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // ✅ Solo columnas necesarias
            ->select(['id', 'name', 'guard_name', 'created_at'])
            // ✅ Pre-carga para evitar N+1 y habilitar lista sin consultas extra
            ->with(['permissions:id,name'])
            // ✅ Para badge y ordenar por cantidad sin subconsultas por fila
            ->withCount('permissions');
    }

    protected static function userIsAdmin(): bool
    {
        /** @var \App\Models\User|null $u */
        $u = Auth::user();
        return $u && method_exists($u, 'hasRole') ? $u->hasRole('Administrador') : false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::userIsAdmin();
    }
    public static function canViewAny(): bool
    {
        return self::userIsAdmin();
    }
    public static function canCreate(): bool
    {
        return self::userIsAdmin();
    }
    public static function canEdit($record): bool
    {
        return self::userIsAdmin();
    }

    public static function canDelete($record): bool
    {
        if ($record instanceof Role && $record->name === 'Administrador') {
            return false;
        }
        return self::userIsAdmin();
    }
    public static function canDeleteAny(): bool
    {
        return self::userIsAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Forms\Components\Hidden::make('guard_name')->default('web'),
            ])->columns(2),

            Forms\Components\Section::make('Permisos')->schema([
                Forms\Components\Select::make('permissions')
                    ->label('Permisos')
                    ->relationship('permissions', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->visible(fn(): bool => self::userIsAdmin())
                    // Crear permisos al vuelo
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del permiso')
                            ->required()
                            ->unique(Permission::class, 'name'),
                        Forms\Components\Hidden::make('guard_name')->default('web'),
                    ])
                    ->createOptionUsing(function (array $data) {
                        $permission = Permission::create([
                            'name'       => $data['name'],
                            'guard_name' => 'web',
                        ]);

                        // Flush cache de spatie
                        app(PermissionRegistrar::class)->forgetCachedPermissions();

                        return $permission->getKey();
                    })
                    ->createOptionAction(function (Action $action) {
                        $action->modalHeading('Nuevo permiso')
                            ->modalSubmitActionLabel('Crear')
                            ->modalWidth('sm');
                    }),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('guard_name')
                    ->label('Guard')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ Usa withCount (rápido) y permite ordenar por cantidad
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permisos')
                    ->counts('permissions')
                    ->badge()
                    ->sortable(),

                // ✅ Lista legible de permisos precargados (sin sortable)
                Tables\Columns\TextColumn::make('permissions_list')
                    ->label('Lista de permisos')
                    ->state(fn(Role $record) => $record->permissions->pluck('name')->join(', '))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('has_permissions')
                    ->label('¿Con permisos?')
                    ->queries(
                        true: fn($q) => $q->has('permissions'),
                        false: fn($q) => $q->doesntHave('permissions'),
                        blank: fn($q) => $q
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn() => app(PermissionRegistrar::class)->forgetCachedPermissions()),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn(Role $record) => $record->name !== 'Administrador')
                    ->requiresConfirmation()
                    ->after(function () {
                        app(PermissionRegistrar::class)->forgetCachedPermissions();
                        Notification::make()->title('Rol eliminado')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, $records) {
                            $protected = $records->firstWhere('name', 'Administrador');
                            if ($protected) {
                                $action->failure();
                                Notification::make()
                                    ->title('No se puede eliminar el rol “Administrador”.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->after(fn() => app(PermissionRegistrar::class)->forgetCachedPermissions()),
                ]),
            ])
            // ✅ Orden por columna indexada y paginación razonable
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
            'index'  => RoleResource\Pages\ListRoles::route('/'),
            'create' => RoleResource\Pages\CreateRole::route('/create'),
            'edit'   => RoleResource\Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
