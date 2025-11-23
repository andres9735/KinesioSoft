<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Models\Paciente; // 👈 NUEVO
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = \App\Filament\Resources\UserResource::sanitizeProfileData($data, $this->record);

        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        return $data;
    }

    protected function handleRecordUpdate($record, array $data): User
    {
        $data = \App\Filament\Resources\UserResource::sanitizeProfileData($data);

        $record->fill([
            'phone'     => $data['phone']     ?? $record->phone,
            'dni'       => $data['dni']       ?? $record->dni,
            'address'   => $data['address']   ?? $record->address,
            'specialty' => $data['specialty'] ?? $record->specialty,
            'is_active' => $data['is_active'] ?? $record->is_active,
        ]);

        if (!empty($data['password'])) {
            $record->password = bcrypt($data['password']);
        }

        $record->save();

        if (isset($data['roles'])) {
            $record->syncRoles($data['roles']);
            \App\Services\PacienteService::ensureProfile($record);
        }


        // 👇 Si ahora tiene rol Paciente, asegurar perfil clínico
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
