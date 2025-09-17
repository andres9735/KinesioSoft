<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use App\Models\Permission;
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('guard_name')->label('Guard')->sortable()->toggleable(isToggledHiddenByDefault: true),

                // 👇 Nueva columna: ¿cuántos roles usan este permiso?
                Tables\Columns\TextColumn::make('roles_count')
                    ->counts('roles')
                    ->label('Usado por roles')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')->label('Creado')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('guard_name')
                    ->label('Guard')
                    ->options(['web' => 'web']),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn() => app(PermissionRegistrar::class)->forgetCachedPermissions()), // 👈

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->after(fn() => app(PermissionRegistrar::class)->forgetCachedPermissions()), // 👈
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(fn() => app(PermissionRegistrar::class)->forgetCachedPermissions()), // 👈
                ]),
            ])
            ->defaultSort('name');
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
