<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole([
            RoleEnum::ADMINISTRADOR->value,
            RoleEnum::SUPERADMIN->value,
        ]);
    }

    public function create(User $actor): bool
    {
        return $actor->hasAnyRole([
            RoleEnum::ADMINISTRADOR->value,
            RoleEnum::SUPERADMIN->value,
        ]);
    }

    public function update(User $actor, User $target): bool
    {
        if ($actor->esSuperadmin()) {
            return true;
        }

        if (! $actor->esAdministrador()) {
            return false;
        }

        return ! $target->esAdministrador()
            && ! $target->esSuperadmin();
    }

    public function changeRole(
        User $actor,
        User $target,
        string $newRole
    ): bool {
        if (! $this->update($actor, $target)) {
            return false;
        }

        if ($target->esSuperadmin()) {
            return $newRole === RoleEnum::SUPERADMIN->value;
        }

        return true;
    }

    public function toggleActivo(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }
}
