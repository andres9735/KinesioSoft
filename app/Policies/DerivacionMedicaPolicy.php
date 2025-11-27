<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DerivacionMedica;

class DerivacionMedicaPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['Kinesiologa'])) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }
    public function view(User $user, DerivacionMedica $model): bool
    {
        return false;
    }
    public function create(User $user): bool
    {
        return false;
    }
    public function update(User $user, DerivacionMedica $model): bool
    {
        return false;
    }
    public function delete(User $user, DerivacionMedica $model): bool
    {
        return false;
    }
    public function deleteAny(User $user): bool
    {
        return false;
    }
}
