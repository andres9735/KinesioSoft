<?php

namespace App\Actions\Turnos;

use App\Enums\CanalAutogestionTurnoEnum;
use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\ConfirmacionAsistenciaNoPermitidaException;
use App\Exceptions\Turnos\ProgramacionTurnoModificadaException;
use App\Exceptions\Turnos\RecursosTurnoModificadosException;
use App\Models\Turno;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\Turnos\TransaccionTurnoService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

final class ConfirmarAsistenciaTurnoAction
{
    public function __construct(
        private readonly TransaccionTurnoService $transacciones,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * El llamador debe validar identidad y canal antes de invocar la acción.
     * Para ENLACE_SEGURO debe resolver su paciente y validar alcance, vigencia e
     * integridad contra la programación presentada; este enum no autoriza enlaces.
     * $turno conserva la programación persistida sobre la que se pidió confirmar.
     */
    public function ejecutar(
        User $actor,
        Turno $turno,
        CanalAutogestionTurnoEnum $canal = CanalAutogestionTurnoEnum::MIS_TURNOS,
    ): ResultadoConfirmacionAsistenciaTurno {
        $esperada = $turno->getRawOriginal();
        foreach (['id', 'paciente_id', 'kinesiologa_id', 'tipo', 'inicio_at', 'fin_at'] as $campo) {
            if (! $turno->exists || ! isset($esperada[$campo])) {
                throw new InvalidArgumentException('Se requiere un turno persistido con su programación completa.');
            }
        }

        return $this->transacciones->ejecutar((int) $esperada['id'], function (Turno $actual, CarbonImmutable $ahora) use ($actor, $canal, $esperada): ResultadoConfirmacionAsistenciaTurno {
            // No heredar roles, perfil o propiedad cacheados en el modelo recibido.
            $identidad = $actor->exists ? User::query()->useWritePdo()->find($actor->getKey(), [
                'id', 'name', 'apellido', 'email', 'activo', 'email_verified_at',
                'dni', 'fecha_nacimiento', 'sexo', 'telefono',
            ]) : null;
            if ($identidad === null || (int) $actual->paciente->user_id !== (int) $identidad->getKey()) {
                throw new AuthorizationException;
            }
            Gate::forUser($identidad)->authorize('confirmar', $actual);
            $identidad->setRelation('paciente', $actual->paciente);
            if ($canal === CanalAutogestionTurnoEnum::MIS_TURNOS
                && (! $identidad->activo || ! $identidad->hasVerifiedEmail() || ! $identidad->tienePerfilPacienteCompleto())) {
                throw new AuthorizationException;
            }

            foreach (['paciente_id', 'kinesiologa_id'] as $campo) {
                if ((int) $actual->getAttribute($campo) !== (int) $esperada[$campo]) {
                    throw new RecursosTurnoModificadosException('Los recursos del turno cambiaron; consultá su estado actual.');
                }
            }
            foreach (['tipo', 'inicio_at', 'fin_at'] as $campo) {
                if ($actual->getRawOriginal($campo) !== $esperada[$campo]) {
                    throw new ProgramacionTurnoModificadaException('La programación del turno cambió; consultá su estado actual.');
                }
            }
            if (! in_array($actual->tipo, [TipoTurnoEnum::PRIMERA_CONSULTA, TipoTurnoEnum::SEGUIMIENTO], true)) {
                throw new ConfirmacionAsistenciaNoPermitidaException('tipo_no_permitido');
            }

            // D-11: una repetición autorizada no vuelve a exigir el plazo de una
            // transición nueva, ni escribe timestamps, auditorías u otros efectos.
            if ($actual->estado === EstadoTurnoEnum::CONFIRMADO) {
                return new ResultadoConfirmacionAsistenciaTurno($actual, true);
            }
            if ($actual->estado !== EstadoTurnoEnum::PENDIENTE) {
                throw new ConfirmacionAsistenciaNoPermitidaException('estado_no_permitido');
            }
            if (! $actual->inicio_at->greaterThan($ahora)) {
                throw new ConfirmacionAsistenciaNoPermitidaException('inicio_no_futuro');
            }

            $contexto = [
                'turno_id' => $actual->getKey(), 'paciente_id' => $actual->paciente_id,
                'kinesiologa_id' => $actual->kinesiologa_id, 'tipo' => $actual->tipo->value,
                'inicio_at' => $actual->inicio_at->toIso8601String(), 'fin_at' => $actual->fin_at->toIso8601String(),
            ];
            $antes = [...$contexto, 'estado' => $actual->estado->value];
            $actual->estado = EstadoTurnoEnum::CONFIRMADO;
            if (! $actual->save()) {
                throw new RuntimeException('No se pudo persistir la confirmación de asistencia.');
            }
            $registro = $this->auditoria->registrar(
                evento: 'turno_asistencia_confirmada', modulo: 'Turnos', auditable: $actual,
                valoresAnteriores: $antes,
                valoresNuevos: [...$contexto, 'estado' => $actual->estado->value,
                    'actor_user_id' => $identidad->getKey(), 'canal' => $canal->value,
                    'ocurrido_at' => $ahora->toIso8601String()],
                actor: $identidad,
            );
            if (! $registro->exists) {
                throw new RuntimeException('No se pudo persistir la auditoría de confirmación.');
            }

            return new ResultadoConfirmacionAsistenciaTurno($actual, false);
        });
    }
}
