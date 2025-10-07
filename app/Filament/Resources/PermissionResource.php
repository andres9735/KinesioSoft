<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\ChecksAdmin;
use App\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use Spatie\Permission\PermissionRegistrar;

class PermissionResource extends Resource
{
    use ChecksAdmin;

    protected static ?string $model = Permission::class;

    protected static ?string $navigationIcon  = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Usuarios';
    protected static ?string $navigationLabel = 'Permisos';
    protected static ?string $modelLabel       = 'permiso';
    protected static ?string $pluralModelLabel = 'permisos';
    protected static ?int    $navigationSort   = 3;

    /** ---------- Query base optimizada ---------- */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select(['id', 'name', 'guard_name', 'created_at'])
            ->with(['roles:id,name'])
            ->withCount('roles');
    }

    /** ---------- Form ---------- */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre del permiso')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn(Unique $rule) => $rule->where('guard_name', 'web')
                    ),

                Forms\Components\Hidden::make('guard_name')->default('web'),
            ])->columns(2),
        ]);
    }

    /** ---------- Tabla ---------- */
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

                Tables\Columns\TextColumn::make('roles_count')
                    ->counts('roles')
                    ->label('Usado por roles')
                    ->badge()
                    ->sortable(),

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
                    ->before(function (Permission $record, Tables\Actions\DeleteAction $action) {
                        // Bloquea la eliminación si el permiso está usado por algún rol
                        if ($record->roles()->exists()) {
                            $action->failure();
                            \Filament\Notifications\Notification::make()
                                ->title('No se puede eliminar: el permiso está asignado a roles.')
                                ->danger()
                                ->send();
                        }
                    })
                    ->after(fn() => app(PermissionRegistrar::class)->forgetCachedPermissions()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, $records) {
                            // Si alguno de los permisos está asignado, abortamos
                            $inUse = $records->first(fn($perm) => $perm->roles()->exists());
                            if ($inUse) {
                                $action->failure();
                                \Filament\Notifications\Notification::make()
                                    ->title('Hay permisos asignados a roles. No se pueden eliminar en bloque.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->after(fn() => app(PermissionRegistrar::class)->forgetCachedPermissions()),
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
            'index'  => PermissionResource\Pages\ListPermissions::route('/'),
            'create' => PermissionResource\Pages\CreatePermission::route('/create'),
            'edit'   => PermissionResource\Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
