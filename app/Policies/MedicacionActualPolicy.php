<?php

namespace App\Policies;

use App\Models\MedicacionActual;
use App\Models\User;

class MedicacionActualPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // mismo criterio que tus otras policies
        if ($user->hasAnyRole(['Kinesiologa'])) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }
    public function view(User $user, MedicacionActual $model): bool
    {
        return false;
    }
    public function create(User $user): bool
    {
        return false;
    }
    public function update(User $user, MedicacionActual $model): bool
    {
        return false;
    }
    public function delete(User $user, MedicacionActual $model): bool
    {
        return false;
    }
    public function deleteAny(User $user): bool
    {
        return false;
    }
}
