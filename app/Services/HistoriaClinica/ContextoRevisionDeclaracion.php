<?php

namespace App\Services\HistoriaClinica;

use App\Data\HistoriaClinica\FuenteDeclaracionParaRevision;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

final class ContextoRevisionDeclaracion
{
    public function emitir(User $actor, array $datos): string
    {
        return Crypt::encryptString(json_encode($this->identidad($actor, $datos), JSON_THROW_ON_ERROR));
    }

    public function verificar(string $contexto, User $actor, array $datos): void
    {
        try {
            $recibido = json_decode(Crypt::decryptString($contexto), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $recibido = null;
        }
        if ($recibido !== $this->identidad($actor, $datos)) {
            throw ValidationException::withMessages(['contexto' => 'Cambió el contexto de revisión. Volvé al expediente y abrí un nuevo formulario.']);
        }
    }

    private function identidad(User $actor, array $datos): array
    {
        $base = $datos['versionBase'];

        return ['proposito' => 'uc14-c-vista-previa', 'actor' => (int) $actor->id,
            'turno' => (int) $datos['turno']->id, 'profesional' => (int) $datos['turno']->kinesiologa_id,
            'paciente' => (int) $datos['paciente']->id, 'declaracion' => $datos['fuente']->declaracionId,
            'original' => $datos['fuente']->huella, 'historia' => $datos['historia']?->id,
            'base' => $base ? FuenteDeclaracionParaRevision::huellaDe([$base->getAttributes(),
                $base->items->map->getAttributes()->all(), $base->integrantes->map->getAttributes()->all()]) : null,
            'contacto' => hash('sha256', $datos['paciente']->contacto_emergencia_nombre ?? '')];
    }
}
