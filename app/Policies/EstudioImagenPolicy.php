<?php

namespace App\Policies;

use App\Models\EstudioImagen;
use App\Models\User;

class EstudioImagenPolicy
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
        return null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }
    public function view(User $user, EstudioImagen $model): bool
    {
        return false;
    }
    public function create(User $user): bool
    {
        return false;
    }
    public function update(User $user, EstudioImagen $model): bool
    {
        return false;
    }
    public function delete(User $user, EstudioImagen $model): bool
    {
        return false;
    }
    public function deleteAny(User $user): bool
    {
        return false;
    }
}
