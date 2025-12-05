<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Models\Paciente;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private function isAdmin(): bool
    {
        /** @var User|null $u */
        $u = Auth::user();
        return $u?->hasRole('Administrador') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(function (User $record) {
                    return $this->isAdmin()
                        && ! $record->hasRole('Administrador')
                        && Auth::id() !== $record->id;
                })
                ->requiresConfirmation(),
        ];
    }

    /**
     * Antes de guardar:
     *  - saneamos los campos de perfil según los roles
     *  - hasheamos password si se envió, o lo quitamos si viene vacío
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Limpia phone/dni/address/specialty según roles,
        // pero NO toca name ni email.
        $data = UserResource::sanitizeProfileData($data, $this->record);

        if (! empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        return $data;
    }

    /**
     * Acá dejamos que se actualicen TODOS los campos del form
     * (name, email, perfil, etc.) y luego aplicamos lógica extra
     * de roles y perfil Paciente.
     */
    protected function handleRecordUpdate($record, array $data): User
    {
        // $data ya viene mutado desde mutateFormDataBeforeSave
        $record->fill($data);
        $record->save();

        if (isset($data['roles'])) {
            $record->syncRoles($data['roles']);
            \App\Services\PacienteService::ensureProfile($record);
        }

        // Si ahora tiene rol Paciente, asegurar perfil clínico
        if ($record->hasRole('Paciente')) {
            Paciente::firstOrCreate(
                ['user_id' => $record->id],
                ['nombre'  => $record->name]
            );
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
