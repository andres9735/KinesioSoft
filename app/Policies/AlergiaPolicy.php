<?php

namespace App\Policies;

use App\Models\Alergia;
use App\Models\User;

class AlergiaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Kinesiologa') || $user->hasRole('Administrador');
    }

    public function view(User $user, Alergia $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Kinesiologa');
    }

    public function update(User $user, Alergia $model): bool
    {
        return $user->hasRole('Kinesiologa');
    }

    public function delete(User $user, Alergia $model): bool
    {
        return $user->hasRole('Kinesiologa');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Kinesiologa');
    }
}
