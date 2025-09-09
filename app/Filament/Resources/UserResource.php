<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon   = 'heroicon-o-user';
    protected static ?string $navigationGroup  = 'Usuarios y Acceso';
    protected static ?string $navigationLabel  = 'Usuarios';
    protected static ?string $modelLabel       = 'usuario';
    protected static ?string $pluralModelLabel = 'usuarios';
    protected static ?int    $navigationSort   = 1;

    /** @return bool */
    protected static function isAdmin(): bool
    {
        /** @var \App\Models\User|null $u */
        $u = Auth::user();
        return $u?->hasRole('Administrador') ?? false;
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
        return self::isAdmin();
    }

    public static function canEdit($record): bool
    {
        return self::isAdmin();
    }

    public static function canDelete($record): bool
    {
        if ($record instanceof User && $record->hasRole('Administrador')) {
            return false;
        }

        return self::isAdmin();
    }

    public static function canDeleteAny(): bool
    {
        return self::isAdmin();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->hidden(function (?User $record): bool {
                        // Si un admin edita su propio usuario, ocultamos email
                        return $record?->id === Auth::id() && self::isAdmin();
                    }),

                Forms\Components\TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                    ->dehydrated(fn($state) => filled($state))
                    ->required(fn(string $operation) => $operation === 'create'),
            ])->columns(2),

            Forms\Components\Section::make('Roles')->schema([
                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Roles')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->hidden(function (?User $record): bool {
                        // Si un admin edita su propio usuario, ocultamos roles para evitar autolimitarse
                        return $record?->id === Auth::id() && self::isAdmin();
                    }),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(', ')
                    ->limitList(3),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Editar usuario')
                    ->modalWidth('lg')
                    ->visible(fn() => self::isAdmin())
                    ->mutateFormDataUsing(function (array $data, User $record): array {
                        if (Auth::id() === $record->id && ! self::isAdmin()) {
                            unset($data['roles'], $data['email']);
                        }
                        return $data;
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn(User $record) => self::isAdmin() && ! $record->hasRole('Administrador'))
                    ->hidden(fn(User $record) => Auth::id() === $record->id)
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => self::isAdmin())
                        ->before(function (Tables\Actions\DeleteBulkAction $action, $records) {
                            $admins = $records->filter->hasRole('Administrador');

                            if ($admins->isNotEmpty()) {
                                $action->failure();

                                Notification::make()
                                    ->title('No se pueden eliminar usuarios con rol Administrador.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ]);
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
}
