<?php

namespace App\Services\Turnos;

use App\Models\Kinesiologa;
use App\Models\User;
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

    /**
     * Snapshot exclusivo para el predicado: el caller ya bloqueó la Kinesiologa.
     * Las especialidades sólo requieren existencia de asociación, no atributos del catálogo.
     */
    public function prepararEvaluacionBloqueante(Kinesiologa $kinesiologa): void
    {
        $user = User::query()->whereKey($kinesiologa->user_id)->lockForUpdate()->first();
        $kinesiologa->setRelation('user', $user);

        if ($user === null) {
            return;
        }

        $roles = $user->roles();
        $roles->newPivotQuery()->orderBy($roles->getRelatedPivotKeyName())->lockForUpdate()->get();
        $user->setRelation('roles', $roles->orderBy($roles->getRelated()->getQualifiedKeyName())->lockForUpdate()->get());
        $kinesiologa->setRelation('matriculasProfesionales', $kinesiologa->matriculasProfesionales()
            ->orderBy('id')->lockForUpdate()->get());

        $relacion = $kinesiologa->especialidades();
        $pivotes = $relacion->newPivotQuery()->orderBy('id')->lockForUpdate()->get();
        $modelo = $relacion->getRelated();
        $referencias = $pivotes->map(fn ($fila): array => [
            $modelo->getKeyName() => $fila->{$relacion->getRelatedPivotKeyName()},
        ])->all();
        // La FK ya asegura la referencia; hidratar IDs evita leer/bloquear un catálogo que el predicado no consulta.
        $kinesiologa->setRelation('especialidades', $modelo->hydrate($referencias));
        $user->setRelation('kinesiologa', $kinesiologa);
    }
}
