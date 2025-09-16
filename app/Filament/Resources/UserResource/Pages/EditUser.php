<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** Helper solo para que el IDE conozca el tipo y el método hasRole() */
    private function isAdmin(): bool
    {
        /** @var User|null $u */
        $u = Auth::user();
        return $u?->hasRole('Administrador') ?? false;
    }

    /**
     * Acciones de cabecera (solo borrar, con las mismas reglas del listado)
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(function (User $record) {
                    return $this->isAdmin()                   // solo admins
                        && ! $record->hasRole('Administrador') // nunca borrar admins
                        && Auth::id() !== $record->id;         // ni a sí mismo
                })
                ->requiresConfirmation(),
        ];
    }

    /**
     * Blindaje: si el admin edita su propio usuario, ignoro email/roles.
     * (La UI ya los oculta; esto es refuerzo por si intentan forzar el POST.)
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Limpieza por roles (seguridad backend)
        $data = \App\Filament\Resources\UserResource::sanitizeProfileData($data);

        // Si manejás password en el form, hashea sólo si se envía algo
        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        return $data;
    }


    /**
     * Adónde volver después de guardar
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
