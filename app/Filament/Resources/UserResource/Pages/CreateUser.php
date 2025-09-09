<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\User; // 👈 tipamos el modelo

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** Helper para el IDE y para reutilizar */
    private function isAdmin(): bool
    {
        /** @var User|null $u */
        $u = Auth::user();
        return $u?->hasRole('Administrador') ?? false;
    }

    /**
     * Blindaje: si alguien no-admin forzara el POST, ignoro el campo roles.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! $this->isAdmin()) {
            unset($data['roles']);
        }

        return $data;
    }

    /**
     * Si no se enviaron roles, intento setear "Paciente" como default (si existe).
     */
    protected function afterCreate(): void
    {
        $user = $this->record;

        // Si ya vinieron roles por el form, no tocamos nada.
        if ($user->roles()->exists()) {
            return;
        }

        try {
            if (class_exists(\Spatie\Permission\Models\Role::class)) {
                $roleModel = \Spatie\Permission\Models\Role::where('name', 'Paciente')->first();
                if ($roleModel) {
                    $user->syncRoles(['Paciente']);
                }
            }
        } catch (\Throwable $e) {
            // Silencioso: no interrumpimos el flujo si falta el rol
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
