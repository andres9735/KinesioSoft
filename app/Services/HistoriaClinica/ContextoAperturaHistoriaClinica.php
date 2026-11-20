<?php

namespace App\Services\HistoriaClinica;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

final class ContextoAperturaHistoriaClinica
{
    public function emitir(User $actor, Turno $turno): string
    {
        return Crypt::encryptString(json_encode([
            'proposito' => 'uc14-apertura', 'actor' => (int) $actor->id, 'turno' => (int) $turno->id,
            'paciente' => (int) $turno->paciente_id, 'clave' => (string) Str::uuid(),
            'contacto' => hash('sha256', $turno->paciente->contacto_emergencia_nombre ?? ''),
        ], JSON_THROW_ON_ERROR));
    }

    public function verificar(string $contexto, User $actor, Turno $turno, bool $confirmaContacto = false): string
    {
        try {
            $datos = json_decode(Crypt::decryptString($contexto), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException $e) {
            $datos = [];
        }
        if (! is_array($datos) || ($datos['proposito'] ?? null) !== 'uc14-apertura'
            || ($datos['actor'] ?? null) !== (int) $actor->id || ($datos['turno'] ?? null) !== (int) $turno->id
            || ($datos['paciente'] ?? null) !== (int) $turno->paciente_id || ! Str::isUuid($datos['clave'] ?? '')) {
            throw ValidationException::withMessages(['contexto' => 'El contexto cambió o no es válido. Volvé al turno y abrí un nuevo formulario.']);
        }
        if ($confirmaContacto && ($datos['contacto'] ?? null) !== hash('sha256', $turno->paciente->contacto_emergencia_nombre ?? '')) {
            throw ValidationException::withMessages(['contexto' => 'Cambió el contacto de emergencia. Abrí un nuevo formulario y confirmá sus datos antes de incorporarlo.']);
        }

        return $datos['clave'];
    }
}
