<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\User;

class MatriculaProfesionalKinesiologiaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->esKinesiologaConPerfil($user);
    }

    public function create(User $user): bool
    {
        return $this->esKinesiologaConPerfil($user);
    }

    public function update(
        User $user,
        MatriculaProfesionalKinesiologia $matricula
    ): bool {
        return $this->esPropietaria($user, $matricula);
    }

    public function toggleActiva(
        User $user,
        MatriculaProfesionalKinesiologia $matricula
    ): bool {
        return $this->esPropietaria($user, $matricula);
    }

    private function esKinesiologaConPerfil(User $user): bool
    {
        return $user->hasRole(RoleEnum::KINESIOLOGO->value)
            && $user->kinesiologa !== null;
    }

    private function esPropietaria(
        User $user,
        MatriculaProfesionalKinesiologia $matricula
    ): bool {
        if (! $this->esKinesiologaConPerfil($user)) {
            return false;
        }

        return $matricula->kinesiologa_id === $user->kinesiologa?->id;
    }
}
