<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\TecnicaTerapeutica;
use App\Models\User;

class TecnicaTerapeuticaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function view(User $user, TecnicaTerapeutica $tecnicaTerapeutica): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function create(User $user): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function update(User $user, TecnicaTerapeutica $tecnicaTerapeutica): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function inactivar(User $user, TecnicaTerapeutica $tecnicaTerapeutica): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function reactivar(User $user, TecnicaTerapeutica $tecnicaTerapeutica): bool
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
