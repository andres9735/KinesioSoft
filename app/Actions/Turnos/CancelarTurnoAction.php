<?php

namespace App\Actions\Turnos;

use App\Enums\CanalAutogestionTurnoEnum;
use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\CancelacionTurnoNoPermitidaException;
use App\Exceptions\Turnos\ProgramacionTurnoModificadaException;
use App\Exceptions\Turnos\RecursosTurnoModificadosException;
use App\Models\Turno;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\Turnos\RegistrarIntencionEvaluacionCupoService;
use App\Services\Turnos\TransaccionTurnoService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

final class CancelarTurnoAction
{
    public function __construct(
        private readonly TransaccionTurnoService $transacciones,
        private readonly AuditoriaService $auditoria,
        private readonly RegistrarIntencionEvaluacionCupoService $intenciones,
    ) {}

    /**
     * El llamador valida identidad y canal y conserva en $turno la programación
     * persistida sobre la que se pidió cancelar. ENLACE_SEGURO exige validación
     * previa de integridad, alcance y vigencia; el enum no autoriza un enlace.
     */
    public function ejecutar(
        User $actor,
        Turno $turno,
        ?string $motivo = null,
        CanalAutogestionTurnoEnum $canal = CanalAutogestionTurnoEnum::MIS_TURNOS,
    ): ResultadoCancelacionTurno {
        $esperada = $turno->getRawOriginal();
        foreach (['id', 'paciente_id', 'kinesiologa_id', 'tipo', 'inicio_at', 'fin_at'] as $campo) {
            if (! $turno->exists || ! isset($esperada[$campo])) {
                throw new InvalidArgumentException('Se requiere un turno persistido con su programación completa.');
            }
        }

        return $this->transacciones->ejecutar((int) $esperada['id'], function (Turno $actual, CarbonImmutable $ahora) use ($actor, $canal, $motivo, $esperada): ResultadoCancelacionTurno {
            $identidad = $actor->exists ? User::query()->useWritePdo()->find($actor->getKey(), [
                'id', 'name', 'apellido', 'email', 'activo', 'email_verified_at',
                'dni', 'fecha_nacimiento', 'sexo', 'telefono',
            ]) : null;
            if ($identidad === null || (int) $actual->paciente->user_id !== (int) $identidad->getKey()) {
                throw new AuthorizationException;
            }
            Gate::forUser($identidad)->authorize('cancelar', $actual);
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
                throw new CancelacionTurnoNoPermitidaException('tipo_no_permitido');
            }

            // D-11: informar lo ya consolidado, sin volver a exigir el plazo ni
            // reparar o duplicar auditorías/intenciones de otro hecho histórico.
            if ($actual->estado === EstadoTurnoEnum::CANCELADO) {
                return new ResultadoCancelacionTurno($actual, true);
            }
            if (! in_array($actual->estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)) {
                throw new CancelacionTurnoNoPermitidaException('estado_no_permitido');
            }
            // Doce horas corridas sobre instantes, preservando microsegundos.
            if ($actual->inicio_at->utc()->lessThan($ahora->utc()->addHours(12))) {
                throw new CancelacionTurnoNoPermitidaException('fuera_de_plazo');
            }

            $contexto = [
                'turno_id' => $actual->getKey(), 'paciente_id' => $actual->paciente_id,
                'kinesiologa_id' => $actual->kinesiologa_id, 'tipo' => $actual->tipo->value,
                'inicio_at' => $actual->inicio_at->toIso8601String(), 'fin_at' => $actual->fin_at->toIso8601String(),
            ];
            $antes = [...$contexto, 'estado' => $actual->estado->value];
            $motivoNormalizado = trim($motivo ?? '');
            $despues = [...$contexto, 'estado' => EstadoTurnoEnum::CANCELADO->value,
                'actor_user_id' => $identidad->getKey(), 'canal' => $canal->value,
                'ocurrido_at' => $ahora->toIso8601String()];
            if ($motivoNormalizado !== '') {
                $despues['motivo_cancelacion'] = $motivoNormalizado;
            }
            $actual->estado = EstadoTurnoEnum::CANCELADO;
            if (! $actual->save()) {
                throw new RuntimeException('No se pudo persistir la cancelación del turno.');
            }
            $registro = $this->auditoria->registrar(
                evento: 'turno_cancelado', modulo: 'Turnos', auditable: $actual,
                valoresAnteriores: $antes, valoresNuevos: $despues, actor: $identidad,
            );
            if (! $registro->exists) {
                throw new RuntimeException('No se pudo persistir la auditoría de cancelación.');
            }
            $this->intenciones->registrar($registro);

            return new ResultadoCancelacionTurno($actual, false);
        });
    }
}
