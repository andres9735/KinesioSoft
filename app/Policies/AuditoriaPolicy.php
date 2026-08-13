<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\Auditoria;
use App\Models\User;

class AuditoriaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->puedeConsultarAuditoria($user);
    }

    public function view(
        User $user,
        Auditoria $auditoria
    ): bool {
        return $this->puedeConsultarAuditoria($user);
    }

    private function puedeConsultarAuditoria(User $user): bool
    {
        return $user->hasAnyRole([
            RoleEnum::ADMINISTRADOR->value,
            RoleEnum::SUPERADMIN->value,
        ]);
    }
}
