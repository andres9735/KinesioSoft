<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Paciente; // 👈 NUEVO
use Spatie\Permission\Models\Role;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private function isAdmin(): bool
    {
        /** @var User|null $u */
        $u = Auth::user();
        return $u?->hasRole('Administrador') ?? false;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = \App\Filament\Resources\UserResource::sanitizeProfileData($data, null);

        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->record;

        if ($user->roles()->exists()) {
            // si ya vino con roles desde el form, aseguramos perfil si corresponde
            \App\Services\PacienteService::ensureProfile($user);
            return;
        }

        try {
            if (class_exists(\Spatie\Permission\Models\Role::class)) {
                $roleModel = \Spatie\Permission\Models\Role::where('name', 'Paciente')->first();
                if ($roleModel) {
                    $user->syncRoles(['Paciente']);
                    \App\Services\PacienteService::ensureProfile($user);
                }
            }
        } catch (\Throwable $e) {
            // silencioso
        }
    }


    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
