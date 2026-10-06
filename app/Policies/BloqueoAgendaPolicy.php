<?php

namespace App\Policies;

use App\Models\BloqueoAgenda;
use App\Models\User;

class BloqueoAgendaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->esKinesiologa($user);
    }

    public function view(User $user, BloqueoAgenda $bloqueoAgenda): bool
    {
        return $this->esPropietaria($user, $bloqueoAgenda);
    }

    public function create(User $user): bool
    {
        return $this->esKinesiologa($user);
    }

    public function update(User $user, BloqueoAgenda $bloqueoAgenda): bool
    {
        return false;
    }

    public function anular(User $user, BloqueoAgenda $bloqueoAgenda): bool
    {
        return $this->esPropietaria($user, $bloqueoAgenda)
            && $bloqueoAgenda->anulado_at === null;
    }

    public function delete(User $user, BloqueoAgenda $bloqueoAgenda): bool
    {
        return false;
    }

    private function esKinesiologa(User $user): bool
    {
        return $user->esKinesiologo();
    }

    private function esPropietaria(User $user, BloqueoAgenda $bloqueoAgenda): bool
    {
        if (! $this->esKinesiologa($user)) {
            return false;
        }

        return $bloqueoAgenda->kinesiologa_id === $user->kinesiologa?->id;
    }
}
