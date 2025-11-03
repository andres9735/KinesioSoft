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
        // Pasamos el record para que la función use sus roles actuales si los del form no vienen
        $data = \App\Filament\Resources\UserResource::sanitizeProfileData($data, $this->record);

        // Si manejás password en el form, hashea sólo si se envía algo
        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        return $data;
    }

    protected function handleRecordUpdate($record, array $data): User
    {
        // Limpieza de datos (seguridad)
        $data = \App\Filament\Resources\UserResource::sanitizeProfileData($data);

        // Forzar actualización de campos del perfil si vienen del form
        $record->fill([
            'phone'     => $data['phone']     ?? $record->phone,
            'dni'       => $data['dni']       ?? $record->dni,
            'address'   => $data['address']   ?? $record->address,
            'specialty' => $data['specialty'] ?? $record->specialty,
            'is_active' => $data['is_active'] ?? $record->is_active,
        ]);

        // Manejo de contraseña (solo si se envía)
        if (!empty($data['password'])) {
            $record->password = bcrypt($data['password']);
        }

        // Guardar cambios
        $record->save();

        // Actualizar roles (manteniendo la sincronización estándar de Filament)
        if (isset($data['roles'])) {
            $record->syncRoles($data['roles']);
        }

        return $record;
    }


    /**
     * Adónde volver después de guardar
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
