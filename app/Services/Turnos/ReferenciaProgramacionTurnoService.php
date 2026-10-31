<?php

namespace App\Services\Turnos;

use App\Exceptions\Turnos\ProgramacionTurnoModificadaException;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

/** Captura autenticada del detalle; no autoriza ni sustituye los locks de las Actions. */
final class ReferenciaProgramacionTurnoService
{
    public function emitir(Turno $turno, User $actor): string
    {
        return Crypt::encryptString(json_encode([
            'uso' => 'uc06-programacion-v1', 'actor' => (int) $actor->getKey(),
            'turno' => (int) $turno->getKey(), 'programacion' => $this->programacion($turno),
        ], JSON_THROW_ON_ERROR));
    }

    public function comprobar(string $referencia, Turno $turno, User $actor): void
    {
        try {
            $base64 = base64_decode($referencia, true);
            if ($base64 === false || base64_encode($base64) !== $referencia) {
                throw new DecryptException;
            }
            $captura = json_decode(Crypt::decryptString($referencia), true, 32, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw ValidationException::withMessages(['referencia_programacion' => 'La referencia del turno no es válida. Actualizá el detalle.']);
        }
        if (! is_array($captura) || ($captura['uso'] ?? null) !== 'uc06-programacion-v1'
            || ($captura['actor'] ?? null) !== (int) $actor->getKey()
            || ($captura['turno'] ?? null) !== (int) $turno->getKey()
            || ! is_array($captura['programacion'] ?? null)) {
            throw ValidationException::withMessages(['referencia_programacion' => 'La referencia no corresponde a este turno y paciente. Actualizá el detalle.']);
        }
        if ($captura['programacion'] !== $this->programacion($turno)) {
            throw new ProgramacionTurnoModificadaException;
        }
    }

    private function programacion(Turno $turno): array
    {
        return [
            'paciente_id' => (int) $turno->getRawOriginal('paciente_id'),
            'kinesiologa_id' => (int) $turno->getRawOriginal('kinesiologa_id'),
            'tipo' => $turno->getRawOriginal('tipo'),
            'inicio_at' => $turno->getRawOriginal('inicio_at'),
            'fin_at' => $turno->getRawOriginal('fin_at'),
        ];
    }
}
