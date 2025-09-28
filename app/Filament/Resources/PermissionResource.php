<?php

namespace App\Filament\Resources;

use App\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationIcon  = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Usuarios y Acceso';
    protected static ?string $navigationLabel = 'Permisos';
    protected static ?string $modelLabel       = 'permiso';
    protected static ?string $pluralModelLabel = 'permisos';
    protected static ?int    $navigationSort   = 3;

    /** ---------- PERF: query base del resource ---------- */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // ✅ Solo columnas necesarias
            ->select(['id', 'name', 'guard_name', 'created_at'])
            // ✅ Pre-carga para listar roles sin N+1
            ->with(['roles:id,name'])
            // ✅ Para badge/ordenar por cantidad
            ->withCount('roles');
    }

    /** ---------- Seguridad: solo Admin ---------- */
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
        return self::userIsAdmin();
    }
    public static function canDeleteAny(): bool
    {
        return self::userIsAdmin();
    }

    /** ---------- Form ---------- */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre del permiso')
                    ->required()
                    ->unique(ignoreRecord: true),

                Forms\Components\Hidden::make('guard_name')->default('web'),
            ])->columns(2),
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

                Tables\Columns\TextColumn::make('guard_name')
                    ->label('Guard')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ Usa withCount (rápido) y permite ordenar por cantidad
                Tables\Columns\TextColumn::make('roles_count')
                    ->counts('roles')
                    ->label('Usado por roles')
                    ->badge()
                    ->sortable(),

                // ✅ Lista de roles (precargados) – sin sortable para evitar joins caros
                Tables\Columns\TextColumn::make('roles_list')
                    ->label('Lista de roles')
                    ->state(fn(Permission $record) => $record->roles->pluck('name')->join(', '))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('guard_name')
                    ->label('Guard')
                    ->options(['web' => 'web']),

                Tables\Filters\TernaryFilter::make('has_roles')
                    ->label('¿Asignado a algún rol?')
                    ->queries(
                        true: fn($q) => $q->has('roles'),
                        false: fn($q) => $q->doesntHave('roles'),
                        blank: fn($q) => $q,
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn() => app(PermissionRegistrar::class)->forgetCachedPermissions()),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->after(fn() => app(PermissionRegistrar::class)->forgetCachedPermissions()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(fn() => app(PermissionRegistrar::class)->forgetCachedPermissions()),
                ]),
            ])
            // ✅ Índice + paginación razonable
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
            'index'  => PermissionResource\Pages\ListPermissions::route('/'),
            'create' => PermissionResource\Pages\CreatePermission::route('/create'),
            'edit'   => PermissionResource\Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
