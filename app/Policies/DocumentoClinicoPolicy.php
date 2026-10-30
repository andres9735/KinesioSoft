<?php

namespace App\Policies;

use App\Enums\TipoTurnoEnum;
use App\Models\DocumentoClinico;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DocumentoClinicoPolicy
{
    public function download(User $user, DocumentoClinico $documento): bool
    {
        if (! $user->exists || ! $documento->exists) {
            return false;
        }

        // Releer propiedad, origen y asociación persistidos: los atributos o
        // relaciones cargados en memoria no acreditan el derecho de descarga.
        if ($user->esPaciente()
            && DocumentoClinico::query()->whereKey($documento->getKey())
                ->where('origen', DocumentoClinico::ORIGEN_SOLICITUD_TURNO)
                ->whereHas('paciente', fn (Builder $paciente) => $paciente->where('user_id', $user->getKey()))
                ->whereHas('origenTurno.turno', fn (Builder $turno) => $turno
                    ->where('tipo', TipoTurnoEnum::PRIMERA_CONSULTA)
                    ->whereColumn('turnos.paciente_id', 'documentos_clinicos.paciente_id'))
                ->exists()) {
            return true;
        }

        return $user->esKinesiologo()
            && $documento->origenTurno()->whereHas('turno.kinesiologa', function (Builder $query) use ($user): void {
                $query->where('user_id', $user->getKey());
            })->exists();
    }
}
