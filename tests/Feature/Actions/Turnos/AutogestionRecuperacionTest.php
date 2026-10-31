<?php

namespace Tests\Feature\Actions\Turnos;

use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarAsistenciaTurnoAction;
use App\Enums\EstadoTurnoEnum;
use App\Models\Auditoria;
use App\Models\IntencionEvaluacionCupo;
use App\Services\Agenda\AgendaQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\FixturesAutogestionTurnos;
use Tests\TestCase;

class AutogestionRecuperacionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach ([Auditoria::class, IntencionEvaluacionCupo::class] as $modelo) {
            Event::forget('eloquent.created: '.$modelo);
        }
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('fallosPosterioresAConfirmacion')]
    public function test_failed_cancellation_preserves_prior_committed_confirmation_and_can_be_explicitly_retried(string $tipo, string $modelo): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-30 10:00:00', config('app.timezone')));
        $fixtures = new FixturesAutogestionTurnos;
        $turno = $fixtures->turno($fixtures->paciente(), $fixtures->profesional(), ['tipo' => $tipo]);
        $actor = $turno->paciente->user;
        app(ConfirmarAsistenciaTurnoAction::class)->ejecutar($actor, $turno);
        $confirmacion = Auditoria::sole()->getAttributes();
        $antes = $turno->fresh()->getAttributes();
        $fallo = new RuntimeException('Fallo posterior a una confirmación ya consolidada.');
        Event::listen('eloquent.created: '.$modelo, fn () => throw $fallo);
        try {
            app(CancelarTurnoAction::class)->ejecutar($actor, $turno);
            $this->fail('Debe revertir exclusivamente la nueva cancelación.');
        } catch (RuntimeException $error) {
            $this->assertSame($fallo, $error);
        } finally {
            Event::forget('eloquent.created: '.$modelo);
        }
        $this->assertSame($antes, $turno->fresh()->getAttributes());
        $this->assertSame($confirmacion, Auditoria::sole()->getAttributes());
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        $this->assertSame([$turno->id], app(AgendaQueryService::class)->turnosAfectadosPorIntervalo($turno->kinesiologa, $turno->inicio_at, $turno->fin_at)->pluck('id')->all());
        $this->assertTrue(app(ConfirmarAsistenciaTurnoAction::class)->ejecutar($actor, $turno)->esReplay);
        $this->assertFalse(app(CancelarTurnoAction::class)->ejecutar($actor, $turno)->esReplay);
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $turno->fresh()->estado);
        $this->assertSame(['turno_asistencia_confirmada', 'turno_cancelado'], Auditoria::query()->orderBy('id')->pluck('evento')->all());
        $this->assertSame('CONFIRMADO', Auditoria::query()->where('evento', 'turno_cancelado')->sole()->valores_anteriores['estado']);
        $this->assertSame(Auditoria::query()->where('evento', 'turno_cancelado')->sole()->id, IntencionEvaluacionCupo::sole()->auditoria_cancelacion_id);
        CarbonImmutable::setTestNow($turno->inicio_at->addDay());
        $this->assertTrue(app(CancelarTurnoAction::class)->ejecutar($actor, $turno)->esReplay);
        $this->assertDatabaseCount('auditorias', 2);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }

    public static function fallosPosterioresAConfirmacion(): array
    {
        return [
            ['PRIMERA_CONSULTA', Auditoria::class], ['PRIMERA_CONSULTA', IntencionEvaluacionCupo::class],
            ['SEGUIMIENTO', Auditoria::class], ['SEGUIMIENTO', IntencionEvaluacionCupo::class],
        ];
    }

    public function test_fixture_cleanup_respects_foreign_keys_without_schema_reset(): void
    {
        $fixtures = new FixturesAutogestionTurnos;
        $turno = $fixtures->turno($fixtures->paciente(), $fixtures->profesional());
        app(CancelarTurnoAction::class)->ejecutar($turno->paciente->user, $turno);
        $fixtures->limpiar();
        foreach (['turnos', 'auditorias', 'intenciones_evaluacion_cupo', 'users', 'pacientes', 'kinesiologas', 'roles', 'model_has_roles', 'declaraciones_salud_paciente', 'titulos_profesionales', 'localidades', 'provincias'] as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), $tabla);
        }
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('intenciones_evaluacion_cupo'));
    }
}
