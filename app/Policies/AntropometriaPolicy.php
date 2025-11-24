<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Antropometria;

class AntropometriaPolicy
{
    /**
     * Atajo: si la usuaria es Kinesiologa, permití todo para esta entidad.
     * (Mismo criterio que venís usando en otras policies)
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['Kinesiologa'])) {
            return true;
        }
        return null; // deja que evaluen los métodos de abajo
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Antropometria $model): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Antropometria $model): bool
    {
        return false;
    }

    public function delete(User $user, Antropometria $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Antropometria $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Antropometria $model): bool
    {
        return false;
    }
}
