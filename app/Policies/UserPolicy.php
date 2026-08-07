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
}
