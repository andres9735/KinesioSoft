<?php

namespace App\Policies;

use App\Models\Cirugia;
use App\Models\User;

class CirugiaPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // Agregá 'Administrador' si querés que también todo le sea permitido
        if ($user->hasAnyRole(['Kinesiologa'])) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Cirugia $model): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Cirugia $model): bool
    {
        return false;
    }

    public function delete(User $user, Cirugia $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
