<?php

namespace App\Services\Turnos;

use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\ClaveIdempotenciaIncompatibleException;
use App\Models\Paciente;
use App\Models\Turno;
use Illuminate\Database\Eloquent\Builder;

final class ReplayPrimeraConsultaService
{
    public function resolver(Paciente $paciente, string $uuid): ?Turno
    {
        $turno = $this->porClave($uuid)->first();
        if ($turno !== null) {
            $this->validarContexto($turno, $paciente);
        }

        return $turno;
    }

    /** Builder compartido con las lecturas bloqueantes y la verificación sin reconexión de la Action. */
    public function porClave(string $uuid): Builder
    {
        return Turno::query()->useWritePdo()->where('clave_idempotencia', strtolower($uuid));
    }

    public function validarContexto(Turno $turno, Paciente $paciente): void
    {
        if ((int) $turno->paciente_id !== (int) $paciente->getKey()
            || $turno->getRawOriginal('tipo') !== TipoTurnoEnum::PRIMERA_CONSULTA->value) {
            throw new ClaveIdempotenciaIncompatibleException('La clave de intención no puede utilizarse.');
        }
    }
}
