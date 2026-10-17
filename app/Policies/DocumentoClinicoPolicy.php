<?php

namespace App\Policies;

use App\Models\DocumentoClinico;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DocumentoClinicoPolicy
{
    public function download(User $user, DocumentoClinico $documento): bool
    {
        if ($user->esPaciente()
            && $documento->paciente()->where('user_id', $user->getKey())->exists()) {
            return true;
        }

        return $user->esKinesiologo()
            && $documento->origenTurno()->whereHas('turno.kinesiologa', function (Builder $query) use ($user): void {
                $query->where('user_id', $user->getKey());
            })->exists();
    }
}
