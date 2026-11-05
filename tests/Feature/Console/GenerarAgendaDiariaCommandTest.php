<?php

namespace Tests\Feature\Console;

use App\Enums\ResultadoGeneracionAgendaEnum;
use App\Models\EjecucionAgendaDiaria;
use App\Models\Turno;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class GenerarAgendaDiariaCommandTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 01:00:00 UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_scheduler_registra_una_sola_tarea_a_las_siete_locales_sin_disparar_eventos(): void
    {
        $eventos = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains($e->command ?? '', 'agenda:generar-diaria'));
        $this->assertCount(1, $eventos);
        $evento = $eventos->first();
        $this->assertSame('0 7 * * *', $evento->expression);
        $this->assertSame(config('app.timezone'), $evento->timezone);
        $this->assertTrue($evento->withoutOverlapping);
        $this->assertSame(5, $evento->expiresAt);
        foreach (['2026-11-05 09:59:00 UTC' => false, '2026-11-05 10:00:00 UTC' => true, '2026-11-05 10:01:00 UTC' => false] as $instante => $debido) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse($instante));
            $this->assertSame($debido, $evento->isDue(app()));
        }
        $this->assertDatabaseCount('ejecuciones_agenda_diaria', 0);
    }

    public function test_comando_usa_hoy_local_sin_sesion_y_registra_ausencia_de_profesionales(): void
    {
        $this->assertGuest();
        $this->artisan('agenda:generar-diaria')->expectsOutput('Resultado de generación: COMPLETADA')->assertExitCode(0);
        $resultado = EjecucionAgendaDiaria::sole();
        $this->assertSame('2026-11-05', $resultado->fecha_local->toDateString());
        $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, $resultado->resultado);
        $this->assertDatabaseCount('preparaciones_agenda_diaria', 0);
    }

    public function test_comando_prepara_toda_la_fecha_explicita_incluso_antes_de_las_siete_y_agendas_vacias(): void
    {
        $profesional = $this->profesionalUc07();
        $vacia = $this->profesionalUc07();
        $paciente = $this->pacienteUc07();
        Turno::factory()->for($profesional)->for($paciente)->create([
            'inicio_at' => '2026-11-04 06:00:00', 'fin_at' => '2026-11-04 06:30:00',
        ]);
        $antes = DB::table('turnos')->get()->toJson();
        $this->artisan('agenda:generar-diaria', ['--fecha' => '2026-11-04'])->assertExitCode(0);
        $ejecucion = EjecucionAgendaDiaria::sole();
        $this->assertSame('2026-11-04', $ejecucion->fecha_local->toDateString());
        $this->assertSame([1, 0], $ejecucion->preparaciones()->orderBy('kinesiologa_id')->pluck('total_turnos')->all());
        $this->assertSame([$profesional->id, $vacia->id], $ejecucion->preparaciones()->orderBy('kinesiologa_id')->pluck('kinesiologa_id')->all());
        $this->assertSame($antes, DB::table('turnos')->get()->toJson());
    }

    #[DataProvider('fechasInvalidas')]
    public function test_fecha_invalida_no_inicia_procesamiento(string $fecha): void
    {
        $this->artisan('agenda:generar-diaria', ['--fecha' => $fecha])->assertExitCode(2);
        $this->assertDatabaseCount('ejecuciones_agenda_diaria', 0);
    }

    public static function fechasInvalidas(): array
    {
        return [['2026-02-30'], ['2026-1-1'], ['no-fecha']];
    }

    public function test_error_de_lectura_registrado_y_salida_sin_datos_sensibles(): void
    {
        $this->profesionalUc07();
        DB::connection()->beforeExecuting(function (string $sql): void {
            if (str_starts_with($sql, 'select') && str_contains($sql, '"turnos"')) {
                throw new \RuntimeException('SQL privado token clínico');
            }
        });
        $this->artisan('agenda:generar-diaria')->expectsOutput('Resultado de generación: FALLIDA')->assertExitCode(1);
        $ejecucion = EjecucionAgendaDiaria::sole();
        $this->assertSame(ResultadoGeneracionAgendaEnum::FALLIDA, $ejecucion->resultado);
        $this->assertSame('PREPARACION_JORNADA', $ejecucion->preparaciones->sole()->codigo_incidencia->value);
        $this->assertNull($ejecucion->preparaciones->sole()->total_turnos);
    }
}
