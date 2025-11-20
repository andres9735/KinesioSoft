<?php

namespace App\Policies;

use App\Models\AntecedentePersonal;
use App\Models\User;

class AntecedentePersonalPolicy
{
    // (Opcional) Admin todo acceso
    public function before(User $user): ?bool
    {
        return $user->hasRole('Administrador') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Kinesiologa');
    }

    public function view(User $user, AntecedentePersonal $model): bool
    {
        return $user->hasRole('Kinesiologa');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Kinesiologa');
    }

    public function update(User $user, AntecedentePersonal $model): bool
    {
        return $user->hasRole('Kinesiologa');
    }

    public function delete(User $user, AntecedentePersonal $model): bool
    {
        return $user->hasRole('Kinesiologa');
    }

    // ✅ para acciones masivas (bulk)
    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Kinesiologa');
    }

    public function restore(User $user, AntecedentePersonal $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, AntecedentePersonal $model): bool
    {
        return false;
    }
}
