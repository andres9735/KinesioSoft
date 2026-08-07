<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\Jurisdiccion;
use App\Models\User;

class JurisdiccionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function create(User $user): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function update(User $user, Jurisdiccion $jurisdiccion): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function toggleActivo(User $user, Jurisdiccion $jurisdiccion): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    private function puedeAdministrarCatalogos(User $user): bool
    {
        return $user->hasAnyRole([
            RoleEnum::ADMINISTRADOR->value,
            RoleEnum::SUPERADMIN->value,
        ]);
    }
}
