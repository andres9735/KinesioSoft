<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\TipoEquipoTerapeutico;
use App\Models\User;

class TipoEquipoTerapeuticoPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function view(User $user, TipoEquipoTerapeutico $tipoEquipoTerapeutico): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function create(User $user): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function update(User $user, TipoEquipoTerapeutico $tipoEquipoTerapeutico): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function inactivar(User $user, TipoEquipoTerapeutico $tipoEquipoTerapeutico): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function reactivar(User $user, TipoEquipoTerapeutico $tipoEquipoTerapeutico): bool
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
