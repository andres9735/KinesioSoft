<?php

namespace Tests\Feature\Console;

use App\Jobs\DespacharRecordatorioTurnoJob;
use App\Models\Auditoria;
use App\Models\Turno;
use App\Services\Turnos\OrquestarRecordatoriosTurnoService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class PrepararRecordatoriosTurnoCommandTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || extension_loaded('pdo_mysql')) {
            throw new LogicException('Estas pruebas requieren SQLite en memoria sin PDO MySQL.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test',
            'recordatorios.horario_inicio' => null, 'recordatorios.horario_fin' => null]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 10:00:00', config('app.timezone')));
        Mail::fake();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_scheduler_cada_cinco_minutos_local_sin_alterar_uc29(): void
    {
        $eventos = collect(app(Schedule::class)->events());
        $evento = $eventos->first(fn ($e) => str_contains($e->command ?? '', 'turnos:preparar-recordatorios'));
        $this->assertSame('*/5 * * * *', $evento->expression);
        $this->assertSame(config('app.timezone'), $evento->timezone);
        $this->assertTrue($evento->withoutOverlapping);
        $uc29 = $eventos->first(fn ($e) => str_contains($e->command ?? '', 'agenda:generar-diaria'));
        $this->assertSame('0 7 * * *', $uc29->expression);
        $this->assertNotSame($evento->mutexName(), $uc29->mutexName());
        foreach (['13:00:00' => true, '13:01:00' => false, '13:05:00' => true] as $hora => $debido) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 '.$hora, 'UTC'));
            $this->assertSame($debido, $evento->isDue(app()));
        }
        $this->assertDatabaseCount('recordatorios_turno', 0);
    }

    public function test_sin_candidatos_finaliza_normal_sin_registros_ficticios(): void
    {
        $this->assertGuest();
        $this->artisan('turnos:preparar-recordatorios')->expectsOutput('Recordatorios seleccionados: 0')->assertExitCode(0);
        $this->assertDatabaseCount('recordatorios_turno', 0);
        $this->assertDatabaseCount('auditorias', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_ciclos_repetidos_y_turnos_creados_entre_ciclos(): void
    {
        $profesional = $this->profesionalUc07();
        $paciente = $this->pacienteUc07();
        $turno = Turno::factory()->for($profesional)->for($paciente)->create([
            'inicio_at' => '2026-11-06 18:00:00', 'fin_at' => '2026-11-06 18:30:00',
        ]);
        $antes = $turno->fresh()->getAttributes();
        $this->artisan('turnos:preparar-recordatorios')->expectsOutput('Recordatorios seleccionados: 1')->assertExitCode(0);
        $auditorias = Auditoria::count();
        $this->artisan('turnos:preparar-recordatorios')->assertExitCode(0);
        $this->assertSame($auditorias, Auditoria::count());
        Turno::factory()->for($profesional)->for($paciente)->create([
            'inicio_at' => '2026-11-06 19:00:00', 'fin_at' => '2026-11-06 19:30:00',
        ]);
        $this->artisan('turnos:preparar-recordatorios')->expectsOutput('Recordatorios seleccionados: 2')->assertExitCode(0);
        $this->assertDatabaseCount('recordatorios_turno', 2);
        $this->assertDatabaseCount('sobres_entrega_enlace', 2);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 2);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 0);
        $this->assertSame($antes, $turno->fresh()->getAttributes());
        Mail::assertNothingSent();
        Queue::assertPushed(DespacharRecordatorioTurnoJob::class, 4);
        Queue::assertPushed(DespacharRecordatorioTurnoJob::class, function ($job): bool {
            $payload = serialize($job);
            $this->assertStringNotContainsString('?token=', $payload);
            $this->assertStringNotContainsString('material_cifrado', $payload);

            return $job->recordatorioId > 0 && $job->tries === 1 && $job->afterCommit === true;
        });
    }

    public function test_ejecucion_con_mutex_ocupado_no_interfiere_ni_libera_lock_ajeno(): void
    {
        $lock = Cache::lock(OrquestarRecordatoriosTurnoService::MUTEX, 300);
        $this->assertTrue($lock->get());
        $this->artisan('turnos:preparar-recordatorios')->expectsOutput('Ciclo omitido por horario o ejecución en curso.')->assertExitCode(0);
        $this->assertFalse(Cache::lock(OrquestarRecordatoriosTurnoService::MUTEX, 300)->get());
        $lock->release();
        $this->artisan('turnos:preparar-recordatorios')->expectsOutput('Recordatorios seleccionados: 0')->assertExitCode(0);
    }

    #[DataProvider('horarios')]
    public function test_horario_operativo_configurable_local(string $inicio, string $fin, string $ahora, bool $ejecuta): void
    {
        config(['recordatorios.horario_inicio' => $inicio, 'recordatorios.horario_fin' => $fin]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 '.$ahora, config('app.timezone')));
        $this->artisan('turnos:preparar-recordatorios')
            ->expectsOutput($ejecuta ? 'Recordatorios seleccionados: 0' : 'Ciclo omitido por horario o ejecución en curso.')
            ->assertExitCode(0);
    }

    public static function horarios(): array
    {
        return [['08:00', '20:00', '08:00:00', true], ['08:00', '20:00', '20:00:00', true],
            ['08:00', '20:00', '07:59:00', false], ['22:00', '06:00', '23:00:00', true],
            ['22:00', '06:00', '05:00:00', true], ['22:00', '06:00', '12:00:00', false]];
    }

    public function test_error_registra_incidencia_saneada_y_libera_mutex(): void
    {
        Log::spy();
        DB::connection()->beforeExecuting(function (string $sql): void {
            if (str_contains($sql, 'select "turnos"."id"')) {
                throw new RuntimeException('SQL token=NO-EXPONER CLINICO');
            }
        });
        $this->artisan('turnos:preparar-recordatorios')
            ->expectsOutput('No pudo completarse la preparación de recordatorios. Revisá las incidencias técnicas.')
            ->assertExitCode(1);
        $this->assertDatabaseCount('auditorias', 0);
        Log::shouldHaveReceived('error')->once()->with('No pudo completarse la selección automática de recordatorios.');
        $lock = Cache::lock(OrquestarRecordatoriosTurnoService::MUTEX, 300);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_configuracion_horaria_invalida_no_inicia_seleccion(): void
    {
        config(['recordatorios.horario_inicio' => '25:99']);
        $this->artisan('turnos:preparar-recordatorios')->assertExitCode(1);
        $this->assertDatabaseCount('recordatorios_turno', 0);
    }
}
