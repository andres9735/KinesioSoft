<?php

namespace Tests\Integration\Uc06MariaDb;

use App\Actions\Turnos\CancelarTurnoAction;
use App\Enums\EstadoTurnoEnum;
use App\Exceptions\Turnos\CancelacionTurnoNoPermitidaException;
use App\Exceptions\Turnos\ConfirmacionAsistenciaNoPermitidaException;
use App\Exceptions\Turnos\ProgramacionTurnoModificadaException;
use App\Exceptions\Turnos\RecursosTurnoModificadosException;
use App\Models\Auditoria;
use App\Models\IntencionEvaluacionCupo;
use App\Services\Agenda\AgendaQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\Uc06MariaDb\Support\MariaDbUc06TestCase;

/** Fuera de la suite SQLite. Ninguna propiedad concurrente se certifica por omisión. */
class AutogestionConcurrenciaTest extends MariaDbUc06TestCase
{
    #[DataProvider('carreras')]
    public function test_two_independent_workers_serialize_real_transitions(string $primera, string $segunda, string $tipo): void
    {
        $turno = $this->fixtures->turno($this->fixtures->paciente(), $this->fixtures->profesional(), ['tipo' => $tipo]);
        $this->iniciar('uno', $primera, $turno, ['pausar' => true]);
        $this->workers->esperar('uno', 'protegido');
        $this->iniciar('dos', $segunda, $turno);
        $this->esperarBloqueo('dos', $this->sesiones['uno']);
        $this->workers->barrera->marcar('uno-continuar');
        $uno = $this->resultado('uno');
        $dos = $this->resultado('dos');
        $this->assertSame('ok', $uno['status']);
        $this->assertFalse($uno['esReplay']);
        if ($primera === 'cancelar' && $segunda === 'confirmar') {
            $this->assertSame('error', $dos['status']);
            $this->assertSame(ConfirmacionAsistenciaNoPermitidaException::class, $dos['excepcion']);
        } else {
            $this->assertSame('ok', $dos['status']);
            $this->assertSame($primera === $segunda, $dos['esReplay']);
        }
        $cancelado = in_array('cancelar', [$primera, $segunda], true);
        $this->assertSame($cancelado ? EstadoTurnoEnum::CANCELADO : EstadoTurnoEnum::CONFIRMADO, $turno->fresh()->estado);
        $this->conteos($primera === 'confirmar' ? 1 : 0, $cancelado ? 1 : 0, $cancelado ? 1 : 0);
        $ocupacion = app(AgendaQueryService::class)->turnosAfectadosPorIntervalo($turno->kinesiologa, $turno->inicio_at, $turno->fin_at)->pluck('id')->all();
        $this->assertSame($cancelado ? [] : [$turno->id], $ocupacion);
    }

    public static function carreras(): array
    {
        $casos = [];
        foreach (['PRIMERA_CONSULTA', 'SEGUIMIENTO'] as $tipo) {
            foreach ([['confirmar', 'confirmar'], ['cancelar', 'cancelar'], ['confirmar', 'cancelar'], ['cancelar', 'confirmar']] as [$uno, $dos]) {
                $casos[$tipo.' '.$uno.' → '.$dos] = [$uno, $dos, $tipo];
            }
        }

        return $casos;
    }

    #[DataProvider('recursosCompartidos')]
    public function test_different_turnos_contend_for_the_expected_shared_mutex(string $recurso): void
    {
        $paciente = $this->fixtures->paciente();
        $profesional = $this->fixtures->profesional();
        $uno = $this->fixtures->turno($paciente, $profesional);
        $dos = $this->fixtures->turno(
            $recurso === 'paciente' ? $paciente : $this->fixtures->paciente(),
            $recurso === 'kinesiologa' ? $profesional : $this->fixtures->profesional(),
        );
        $this->iniciar('uno', 'cancelar', $uno, ['pausar' => true]);
        $this->workers->esperar('uno', 'protegido');
        $this->iniciar('dos', 'cancelar', $dos);
        $this->esperarBloqueo('dos', $this->sesiones['uno']);
        $this->workers->barrera->marcar('uno-continuar');
        $this->assertSame('ok', $this->resultado('uno')['status']);
        $this->assertSame('ok', $this->resultado('dos')['status']);
        $this->conteos(0, 2, 2);
        $this->assertSame(2, IntencionEvaluacionCupo::query()->distinct()->count('auditoria_cancelacion_id'));
    }

    public static function recursosCompartidos(): array
    {
        return [['kinesiologa'], ['paciente']];
    }

    #[DataProvider('cambiosDuranteEspera')]
    public function test_revalidates_committed_changes_after_an_observed_lock_wait(string $cambio, string $excepcion): void
    {
        $paciente = $this->fixtures->paciente();
        $turno = $this->fixtures->turno($paciente, $this->fixtures->profesional());
        $nuevoProfesional = $this->fixtures->profesional();
        $nuevoPaciente = $this->fixtures->paciente();
        DB::beginTransaction();
        try {
            DB::table('kinesiologas')->where('id', $turno->kinesiologa_id)->lockForUpdate()->first();
            $this->iniciar('espera', 'cancelar', $turno);
            $this->esperarBloqueo('espera', $this->sesion);
            match ($cambio) {
                'inicio_at' => DB::table('turnos')->where('id', $turno->id)->update(['inicio_at' => $turno->inicio_at->addHour()]),
                'fin_at' => DB::table('turnos')->where('id', $turno->id)->update(['fin_at' => $turno->fin_at->addHour()]),
                'tipo' => DB::table('turnos')->where('id', $turno->id)->update(['tipo' => 'SEGUIMIENTO']),
                'kinesiologa_id' => DB::table('turnos')->where('id', $turno->id)->update(['kinesiologa_id' => $nuevoProfesional->id]),
                'paciente_id' => DB::table('turnos')->where('id', $turno->id)->update(['paciente_id' => $nuevoPaciente->id]),
                'estado' => DB::table('turnos')->where('id', $turno->id)->update(['estado' => 'EN_ATENCION']),
                'activo' => DB::table('users')->where('id', $paciente->user_id)->update(['activo' => false]),
                'rol' => DB::table('model_has_roles')->where('model_id', $paciente->user_id)->where('model_type', $paciente->user->getMorphClass())->delete(),
            };
            DB::commit();
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        $antes = $turno->fresh()->getAttributes();
        $resultado = $this->resultado('espera');
        $this->assertSame('error', $resultado['status']);
        $this->assertSame($excepcion, $resultado['excepcion']);
        $this->assertSame($antes, $turno->fresh()->getAttributes());
        $this->conteos(0, 0, 0);
    }

    public static function cambiosDuranteEspera(): array
    {
        return [
            ['inicio_at', ProgramacionTurnoModificadaException::class], ['fin_at', ProgramacionTurnoModificadaException::class],
            ['tipo', ProgramacionTurnoModificadaException::class], ['kinesiologa_id', RecursosTurnoModificadosException::class],
            ['paciente_id', RecursosTurnoModificadosException::class], ['estado', CancelacionTurnoNoPermitidaException::class],
            ['activo', AuthorizationException::class], ['rol', AuthorizationException::class],
        ];
    }

    #[DataProvider('operaciones')]
    public function test_clock_is_evaluated_after_real_wait_and_replays_remain_authorized(string $operacion): void
    {
        $turno = $this->fixtures->turno($this->fixtures->paciente(), $this->fixtures->profesional());
        $limite = $operacion === 'cancelar' ? $turno->inicio_at->subHours(12) : $turno->inicio_at;
        DB::beginTransaction();
        try {
            DB::table('kinesiologas')->where('id', $turno->kinesiologa_id)->lockForUpdate()->first();
            $this->iniciar('espera', $operacion, $turno, ['ahora' => $limite->subSecond()->toIso8601String(), 'ahora_despues_lock' => $limite->addSecond()->toIso8601String()]);
            $this->esperarBloqueo('espera', $this->sesion);
        } finally {
            DB::rollBack();
        }
        $rechazo = $this->resultado('espera');
        $this->assertSame('error', $rechazo['status']);
        $this->assertSame($operacion === 'cancelar' ? CancelacionTurnoNoPermitidaException::class : ConfirmacionAsistenciaNoPermitidaException::class, $rechazo['excepcion']);
        $this->conteos(0, 0, 0);
        $this->iniciar('valido', $operacion, $turno);
        $this->assertFalse($this->resultado('valido')['esReplay']);
        $this->iniciar('replay', $operacion, $turno, ['ahora' => $turno->inicio_at->addDay()->toIso8601String()]);
        $this->assertTrue($this->resultado('replay')['esReplay']);
        $this->conteos($operacion === 'confirmar' ? 1 : 0, $operacion === 'cancelar' ? 1 : 0, $operacion === 'cancelar' ? 1 : 0);
    }

    public static function operaciones(): array
    {
        return [['confirmar'], ['cancelar']];
    }

    public function test_same_audit_intention_is_unique_under_real_concurrent_registration(): void
    {
        $turno = $this->fixtures->turno($this->fixtures->paciente(), $this->fixtures->profesional());
        app(CancelarTurnoAction::class)->ejecutar($turno->paciente->user, $turno);
        $auditoria = Auditoria::sole();
        // Sólo fixture: conservar cancelación/auditoría y competir por su intención aún ausente.
        DB::table('intenciones_evaluacion_cupo')->where('auditoria_cancelacion_id', $auditoria->id)->delete();
        $this->iniciar('uno', 'intencion', $turno, ['auditoria' => $auditoria->id, 'pausar' => true]);
        $this->workers->esperar('uno', 'protegido');
        $this->iniciar('dos', 'intencion', $turno, ['auditoria' => $auditoria->id]);
        $this->esperarBloqueo('dos', $this->sesiones['uno']);
        $this->workers->barrera->marcar('uno-continuar');
        $uno = $this->resultado('uno', false);
        $dos = $this->resultado('dos', false);
        $this->assertSame('ok', $uno['status']);
        $this->assertSame('ok', $dos['status'], 'No basta evitar duplicados: ambos registros deben resolver la misma intención.');
        $this->assertSame($uno['intencion_id'], $dos['intencion_id']);
        $this->conteos(0, 1, 1);
    }

    #[DataProvider('operaciones')]
    public function test_real_timeout_has_no_partial_writes_and_explicit_retry_is_safe(string $operacion): void
    {
        $turno = $this->fixtures->turno($this->fixtures->paciente(), $this->fixtures->profesional());
        DB::beginTransaction();
        try {
            DB::table('kinesiologas')->where('id', $turno->kinesiologa_id)->lockForUpdate()->first();
            $this->iniciar('timeout', $operacion, $turno, ['lock_timeout' => 3]);
            $this->esperarBloqueo('timeout', $this->sesion);
            $resultado = $this->resultado('timeout', false);
            $this->assertSame('error', $resultado['status']);
            $this->assertContains(1205, $resultado['codigos_sql']);
        } finally {
            DB::rollBack();
        }
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $turno->fresh()->estado);
        $this->conteos(0, 0, 0);
        $this->iniciar('retry', $operacion, $turno);
        $this->assertSame('ok', $this->resultado('retry')['status']);
        $this->conteos($operacion === 'confirmar' ? 1 : 0, $operacion === 'cancelar' ? 1 : 0, $operacion === 'cancelar' ? 1 : 0);
    }

    #[DataProvider('operaciones')]
    public function test_physical_deadlock_is_observed_and_never_hidden_by_automatic_retry(string $operacion): void
    {
        $turno = $this->fixtures->turno($this->fixtures->paciente(), $this->fixtures->profesional());
        DB::beginTransaction();
        $codigoPadre = null;
        try {
            // Inversión deliberada SÓLO del actor de prueba para forzar un ciclo físico.
            DB::table('pacientes')->where('id', $turno->paciente_id)->lockForUpdate()->first();
            $this->iniciar('deadlock', $operacion, $turno);
            $this->esperarBloqueo('deadlock', $this->sesion);
            try {
                DB::table('kinesiologas')->where('id', $turno->kinesiologa_id)->lockForUpdate()->first();
            } catch (QueryException $error) {
                $codigoPadre = (int) ($error->errorInfo[1] ?? 0);
                $this->assertSame(1213, $codigoPadre);
            }
        } finally {
            DB::rollBack();
        }
        $resultado = $this->resultado('deadlock', false);
        $this->assertTrue($codigoPadre === 1213 || in_array(1213, $resultado['codigos_sql'] ?? [], true), 'Debe observarse el error físico 1213.');
        $exito = $resultado['status'] === 'ok';
        if (! $exito) {
            $this->assertContains(1213, $resultado['codigos_sql']);
        }
        $this->conteos($exito && $operacion === 'confirmar' ? 1 : 0, $exito && $operacion === 'cancelar' ? 1 : 0, $exito && $operacion === 'cancelar' ? 1 : 0);
        $this->iniciar('retry', $operacion, $turno);
        $retry = $this->resultado('retry');
        $this->assertSame('ok', $retry['status']);
        $this->assertSame($exito, $retry['esReplay']);
        $this->conteos($operacion === 'confirmar' ? 1 : 0, $operacion === 'cancelar' ? 1 : 0, $operacion === 'cancelar' ? 1 : 0);
    }

    #[DataProvider('fallos')]
    public function test_persistence_failure_rolls_back_state_audit_intention_and_occupation(string $fallo): void
    {
        $turno = $this->fixtures->turno($this->fixtures->paciente(), $this->fixtures->profesional());
        $antes = $turno->getAttributes();
        $this->iniciar('falla', 'cancelar', $turno, ['fallar' => $fallo]);
        $fallido = $this->resultado('falla');
        $this->assertSame('error', $fallido['status']);
        $this->assertSame(\RuntimeException::class, $fallido['excepcion']);
        $this->assertSame($antes, $turno->fresh()->getAttributes());
        $this->conteos(0, 0, 0);
        $this->assertSame([$turno->id], app(AgendaQueryService::class)->turnosAfectadosPorIntervalo($turno->kinesiologa, $turno->inicio_at, $turno->fin_at)->pluck('id')->all());
        $this->iniciar('retry', 'cancelar', $turno);
        $this->assertFalse($this->resultado('retry')['esReplay']);
        $this->conteos(0, 1, 1);
    }

    public static function fallos(): array
    {
        return [['auditoria'], ['intencion']];
    }
}
