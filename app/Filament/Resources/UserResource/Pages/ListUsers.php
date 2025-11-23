<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Models\User;
use App\Models\Paciente; // 👈 NUEVO
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getTableQuery(): Builder
    {
        return static::getResource()::getEloquentQuery()->with('roles');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createUser')
                ->label('Crear usuario')
                ->icon('heroicon-m-user-plus')
                ->modalHeading('Crear usuario')
                ->modalSubmitActionLabel('Guardar')
                ->form([
                    Forms\Components\Section::make()->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique('users', 'email'),

                        Forms\Components\TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8),
                    ])->columns(2),

                    Forms\Components\Section::make('Roles')->schema([
                        Forms\Components\Select::make('roles')
                            ->label('Roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ])->visible(function () {
                        /** @var \App\Models\User|null $u */
                        $u = Auth::user();
                        return $u?->hasRole('Administrador') ?? false;
                    }),
                ])
                ->action(function (array $data) {
                    /** @var \App\Models\User|null $authUser */
                    $authUser = Auth::user();

                    $user = User::create([
                        'name'     => $data['name'],
                        'email'    => $data['email'],
                        'password' => Hash::make($data['password']),
                    ]);

                    // Solo admins asignan roles al crear
                    if (($authUser?->hasRole('Administrador') ?? false) && ! empty($data['roles'])) {
                        $user->syncRoles($data['roles']);
                        \App\Services\PacienteService::ensureProfile($user);
                    }

                    // 👇 Si tiene rol Paciente, asegurar perfil clínico
                    if ($user->hasRole('Paciente')) {
                        Paciente::firstOrCreate(
                            ['user_id' => $user->id],
                            ['nombre'  => $user->name]
                        );
                    }

                    Notification::make()
                        ->title('Usuario creado correctamente')
                        ->success()
                        ->send();

                    $this->refreshTable();
                })
                ->visible(function () {
                    /** @var \App\Models\User|null $u */
                    $u = Auth::user();
                    return $u?->hasRole('Administrador') ?? false;
                }),
        ];
    }
}
