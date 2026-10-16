<?php

namespace App\Services\Turnos;

use App\Models\Kinesiologa;
use Illuminate\Database\Eloquent\Collection;

final class ElegibilidadKinesiologaService
{
    /**
     * @return Collection<int, Kinesiologa>
     */
    public function listarElegibles(): Collection
    {
        return Kinesiologa::query()
            ->with([
                'user.roles',
                'user.kinesiologa.matriculasProfesionales',
                'user.kinesiologa.especialidades',
            ])
            ->get()
            ->filter(fn (Kinesiologa $kinesiologa): bool => $this->esElegible($kinesiologa))
            ->values();
    }

    public function esElegible(Kinesiologa $kinesiologa): bool
    {
        return $kinesiologa->user?->puedeAtenderComoKinesiologo() ?? false;
    }
}
