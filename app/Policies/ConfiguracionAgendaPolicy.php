<?php

namespace App\Policies;

use App\Models\ConfiguracionAgenda;
use App\Models\User;
use App\Services\Agenda\RelojAplicacion;

class ConfiguracionAgendaPolicy
{
    public function __construct(
        private readonly RelojAplicacion $reloj
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->esKinesiologa($user);
    }

    public function view(User $user, ConfiguracionAgenda $configuracionAgenda): bool
    {
        return $this->esPropietaria($user, $configuracionAgenda);
    }

    public function create(User $user): bool
    {
        return $this->esKinesiologa($user);
    }

    public function update(User $user, ConfiguracionAgenda $configuracionAgenda): bool
    {
        if (! $this->esPropietaria($user, $configuracionAgenda)) {
            return false;
        }

        $hoy = $this->reloj->hoy();

        return $configuracionAgenda->puedeEditarseEn($hoy);
    }

    public function cambiarDuracion(User $user): bool
    {
        return $this->esKinesiologa($user);
    }

    public function anular(User $user, ConfiguracionAgenda $configuracionAgenda): bool
    {
        if (! $this->esPropietaria($user, $configuracionAgenda)) {
            return false;
        }

        $hoy = $this->reloj->hoy();

        return $configuracionAgenda->puedeEditarseEn($hoy);
    }

    public function delete(User $user, ConfiguracionAgenda $configuracionAgenda): bool
    {
        return false;
    }

    private function esKinesiologa(User $user): bool
    {
        return $user->esKinesiologo();
    }

    private function esPropietaria(User $user, ConfiguracionAgenda $configuracionAgenda): bool
    {
        if (! $this->esKinesiologa($user)) {
            return false;
        }

        return $configuracionAgenda->kinesiologa_id === $user->kinesiologa?->id;
    }
}
