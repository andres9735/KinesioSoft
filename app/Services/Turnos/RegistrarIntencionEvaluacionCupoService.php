<?php

namespace App\Services\Turnos;

use App\Enums\CanalAutogestionTurnoEnum;
use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Auditoria;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Turno;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

final class RegistrarIntencionEvaluacionCupoService
{
    /**
     * Consumidor previsto: 6.12, dentro del callback de TransaccionTurnoService,
     * después de autorizar, cancelar y auditar, todo en la misma conexión/transacción.
     * No cancela, no toma otros mutex ni publica trabajos o efectos externos.
     * La auditoría identifica el hecho, nunca el profesional/horario ni la UUID UC-07.
     * Usa los snapshots administrativos de AuditoriaService, como la Action de 6.10.
     */
    public function registrar(Auditoria $cancelacion): IntencionEvaluacionCupo
    {
        $conexion = DB::connection();
        if ($cancelacion->getConnectionName() !== null && $cancelacion->getConnectionName() !== $conexion->getName()) {
            throw new InvalidArgumentException('La auditoría debe pertenecer a la misma conexión transaccional.');
        }
        if ($conexion->transactionLevel() === 0) {
            throw new LogicException('La intención requiere la transacción de la cancelación.');
        }
        if (! $cancelacion->exists || (int) $cancelacion->getKey() <= 0) {
            throw new InvalidArgumentException('Se requiere una auditoría de cancelación persistida.');
        }

        // Releer el evento inmutable; no confiar en atributos alterados en memoria.
        $hecho = Auditoria::query()->useWritePdo()->findOrFail($cancelacion->getKey());
        $antes = $hecho->valores_anteriores ?? [];
        $despues = $hecho->valores_nuevos ?? [];
        if ($hecho->evento !== 'turno_cancelado' || $hecho->modulo !== 'Turnos'
            || $hecho->auditable_type !== (new Turno)->getMorphClass()
            || ! in_array($antes['estado'] ?? null, ['PENDIENTE', 'CONFIRMADO'], true)
            || ($despues['estado'] ?? null) !== 'CANCELADO'
            || ! in_array($despues['canal'] ?? null, array_column(CanalAutogestionTurnoEnum::cases(), 'value'), true)
            || ! is_int($despues['actor_user_id'] ?? null)
            || $despues['actor_user_id'] !== $hecho->actor_user_id) {
            throw new InvalidArgumentException('La auditoría no acredita una cancelación de autogestión.');
        }
        foreach (['turno_id', 'paciente_id', 'kinesiologa_id', 'tipo', 'inicio_at', 'fin_at'] as $campo) {
            if (! isset($antes[$campo], $despues[$campo]) || $antes[$campo] !== $despues[$campo]) {
                throw new InvalidArgumentException('La cancelación requiere programación completa y sin alteraciones.');
            }
        }
        foreach (['turno_id', 'paciente_id', 'kinesiologa_id'] as $campo) {
            if (! is_int($despues[$campo]) || $despues[$campo] <= 0) {
                throw new InvalidArgumentException('La cancelación requiere identificadores válidos.');
            }
        }
        if ($despues['turno_id'] !== (int) $hecho->auditable_id
            || ! in_array($despues['tipo'], array_column(TipoTurnoEnum::cases(), 'value'), true)) {
            throw new InvalidArgumentException('La cancelación no identifica coherentemente el turno.');
        }
        $inicio = $this->instante($despues['inicio_at']);
        $fin = $this->instante($despues['fin_at']);
        $liberado = $this->instante($despues['ocurrido_at'] ?? null);
        if (! $fin->greaterThan($inicio) || $inicio->lessThan($liberado->addHours(12))) {
            throw new InvalidArgumentException('El intervalo o la anticipación no habilitan esta intención de autogestión.');
        }
        $datos = [
            'turno_id' => $despues['turno_id'], 'kinesiologa_id' => $despues['kinesiologa_id'],
            'inicio_at' => $inicio->format('Y-m-d H:i:s'), 'fin_at' => $fin->format('Y-m-d H:i:s'),
            'liberado_at' => $liberado->format('Y-m-d H:i:s'),
        ];
        $existente = IntencionEvaluacionCupo::query()->useWritePdo()->where('auditoria_cancelacion_id', $hecho->id)->first();
        if ($existente !== null) {
            return $this->comprobarContexto($existente, $datos);
        }

        $turno = Turno::query()->useWritePdo()->findOrFail($despues['turno_id']);
        if ($turno->estado !== EstadoTurnoEnum::CANCELADO
            || (int) $turno->paciente_id !== $despues['paciente_id']
            || (int) $turno->kinesiologa_id !== $despues['kinesiologa_id']
            || $turno->tipo->value !== $despues['tipo']
            || ! $turno->inicio_at->equalTo($inicio) || ! $turno->fin_at->equalTo($fin)) {
            throw new InvalidArgumentException('La cancelación no coincide con el turno persistido.');
        }

        // UNIQUE identifica una cancelación. createOrFirst usa savepoint al
        // resolver colisiones; no silenciar otros fallos mediante insertOrIgnore.
        try {
            $intencion = IntencionEvaluacionCupo::query()->firstOrCreate(['auditoria_cancelacion_id' => $hecho->id], $datos);
        } catch (UniqueConstraintViolationException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) !== 1062
                || ! preg_match("/['`](?:intenciones_evaluacion_cupo\\.)?int_eval_cupo_cancelacion_unique['`]/", (string) ($error->errorInfo[2] ?? ''))
                || $error->getConnectionName() !== $conexion->getNameWithReadWriteType()
                || ! str_starts_with($error->getSql(), 'insert into '.$conexion->getQueryGrammar()->wrapTable((new IntencionEvaluacionCupo)->getTable()).' ')
                || $conexion->transactionLevel() === 0) {
                throw $error;
            }

            // El savepoint conserva el snapshot REPEATABLE-READ: releer la fila
            // ganadora con bloqueo en la misma conexión y transacción vigente.
            $intencion = IntencionEvaluacionCupo::on($conexion->getNameWithReadWriteType())
                ->where('auditoria_cancelacion_id', $hecho->id)
                ->lockForUpdate()
                ->first();
            if ($intencion === null) {
                throw $error;
            }
        }
        if (! $intencion->exists) {
            throw new RuntimeException('No se pudo persistir la intención de evaluación.');
        }

        return $this->comprobarContexto($intencion, $datos);
    }

    private function instante(mixed $valor): CarbonImmutable
    {
        if (! is_string($valor)) {
            throw new InvalidArgumentException('La auditoría requiere instantes ISO 8601 completos.');
        }
        try {
            $instante = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:sP', $valor);
            if ($instante === null || $instante->format('Y-m-d\TH:i:sP') !== $valor) {
                throw new InvalidArgumentException;
            }
        } catch (InvalidArgumentException $error) {
            throw new InvalidArgumentException('La auditoría contiene un instante inválido.', previous: $error);
        }

        return $instante->setTimezone(config('app.timezone'));
    }

    private function comprobarContexto(IntencionEvaluacionCupo $intencion, array $datos): IntencionEvaluacionCupo
    {
        foreach ($datos as $campo => $valor) {
            if ((string) $intencion->getRawOriginal($campo) !== (string) $valor) {
                throw new LogicException('La intención existente contradice la cancelación que la identifica.');
            }
        }

        return $intencion;
    }
}
