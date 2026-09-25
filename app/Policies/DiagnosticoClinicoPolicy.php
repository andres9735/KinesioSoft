<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\DiagnosticoClinico;
use App\Models\User;

class DiagnosticoClinicoPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function view(User $user, DiagnosticoClinico $diagnosticoClinico): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function create(User $user): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function update(User $user, DiagnosticoClinico $diagnosticoClinico): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function inactivar(User $user, DiagnosticoClinico $diagnosticoClinico): bool
    {
        return $this->puedeAdministrarCatalogos($user);
    }

    public function reactivar(User $user, DiagnosticoClinico $diagnosticoClinico): bool
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
