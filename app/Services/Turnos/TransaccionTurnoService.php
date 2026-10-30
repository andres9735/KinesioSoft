<?php

namespace App\Services\Turnos;

use App\Exceptions\Turnos\RecursosTurnoModificadosException;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Services\Agenda\RelojAplicacion;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TransaccionTurnoService
{
    public function __construct(private readonly RelojAplicacion $reloj) {}

    /**
     * Sección protegida para un único turno: kinesióloga -> paciente -> turno.
     * El callback debe autorizar al actor/canal y revalidar estado, programación
     * y plazos sobre este turno releído y sus relaciones bloqueadas. No debe
     * adquirir otros mutex de agenda ni ejecutar efectos externos irreversibles.
     * No implementa transiciones, auditorías, outbox ni reintentos automáticos.
     *
     * @template T
     *
     * @param  Closure(Turno, CarbonImmutable): T  $operacion
     * @return T
     */
    public function ejecutar(int $turnoId, Closure $operacion): mixed
    {
        if ($turnoId <= 0) {
            throw new InvalidArgumentException('El identificador del turno debe ser positivo.');
        }

        // Sólo localiza mutex; esta lectura no autoriza ni acredita vigencia.
        $referencia = Turno::query()->useWritePdo()->findOrFail($turnoId, ['id', 'kinesiologa_id', 'paciente_id']);

        return DB::transaction(function () use ($turnoId, $referencia, $operacion): mixed {
            // Prefijo canónico de UC-07; nunca bloquear primero el turno.
            $kinesiologa = Kinesiologa::query()->lockForUpdate()->findOrFail($referencia->kinesiologa_id);
            $paciente = Paciente::query()->lockForUpdate()->findOrFail($referencia->paciente_id);
            $turno = Turno::query()->lockForUpdate()->findOrFail($turnoId);

            if ((int) $turno->kinesiologa_id !== (int) $kinesiologa->getKey()
                || (int) $turno->paciente_id !== (int) $paciente->getKey()) {
                // No tomar un segundo mutex fuera de orden ni operar con el anterior.
                throw new RecursosTurnoModificadosException('Los recursos del turno cambiaron; es necesario consultar su estado actual.');
            }

            $turno->setRelation('kinesiologa', $kinesiologa);
            $turno->setRelation('paciente', $paciente);

            return $operacion($turno, $this->reloj->ahora());
        }, attempts: 1);
    }
}
