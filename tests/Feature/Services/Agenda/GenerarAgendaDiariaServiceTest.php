<?php

namespace Tests\Feature\Services\Agenda;

use App\Enums\EstadoTurnoEnum;
use App\Enums\IncidenciaGeneracionAgendaEnum;
use App\Enums\ResultadoGeneracionAgendaEnum;
use App\Models\EjecucionAgendaDiaria;
use App\Models\JornadaGeneracionAgenda;
use App\Models\PreparacionAgendaDiaria;
use App\Models\Turno;
use App\Services\Agenda\AgendaAtencionQueryService;
use App\Services\Agenda\GenerarAgendaDiariaService;
use App\Services\Agenda\ReclamoGeneracionPerdidoException;
use App\Services\Agenda\RegistroGeneracionAgendaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class GenerarAgendaDiariaServiceTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-05 07:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_repeticion_y_segunda_instancia_reutilizan_la_misma_ejecucion_sin_escrituras(): void
    {
        $profesional = $this->profesionalUc07();
        $primera = app(GenerarAgendaDiariaService::class)->ejecutar();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $segunda = app(GenerarAgendaDiariaService::class)->ejecutar();
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertTrue($primera->is($segunda));
        $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, $segunda->resultado);
        $this->assertSame([$profesional->id], $segunda->preparaciones->pluck('kinesiologa_id')->all());
        foreach ($queries as $q) {
            $this->assertMatchesRegularExpression('/^select\b/i', $q['query']);
        }
        $this->assertDatabaseCount('ejecuciones_agenda_diaria', 1);
        $this->assertDatabaseCount('preparaciones_agenda_diaria', 1);
        $this->assertDatabaseCount('jornadas_generacion_agenda', 1);
    }

    public function test_jornada_sin_profesionales_tambien_es_idempotente(): void
    {
        $uno = app(GenerarAgendaDiariaService::class)->ejecutar();
        $dos = app(GenerarAgendaDiariaService::class)->ejecutar();
        $this->assertTrue($uno->is($dos));
        $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, $dos->resultado);
        $this->assertDatabaseCount('ejecuciones_agenda_diaria', 1);
        $this->assertDatabaseCount('preparaciones_agenda_diaria', 0);
    }

    public function test_fecha_y_zona_delimitan_unidades_logicas_independientes(): void
    {
        app(GenerarAgendaDiariaService::class)->ejecutar(CarbonImmutable::parse('2026-11-04'));
        app(GenerarAgendaDiariaService::class)->ejecutar();
        config(['app.timezone' => 'Pacific/Auckland']);
        app(GenerarAgendaDiariaService::class)->ejecutar(CarbonImmutable::parse('2026-11-05'));
        $this->assertDatabaseCount('jornadas_generacion_agenda', 3);
        $this->assertDatabaseCount('ejecuciones_agenda_diaria', 3);
    }

    public function test_fallo_parcial_reintenta_solo_fallidas_y_referencia_exitos_sin_volver_a_consultar_turnos(): void
    {
        $bien = $this->profesionalUc07();
        $mal = $this->profesionalUc07();
        $fallar = true;
        $lecturas = [];
        DB::connection()->beforeExecuting(function (string $sql, array $bindings) use ($mal, &$fallar, &$lecturas): void {
            if (str_starts_with($sql, 'select') && str_contains($sql, 'from "turnos"')) {
                $lecturas[] = $bindings[0];
                if ($fallar && (int) $bindings[0] === $mal->id) {
                    throw new RuntimeException('SQL y contenido clínico no deben registrarse');
                }
            }
        });
        $servicio = app(GenerarAgendaDiariaService::class);
        $primera = $servicio->ejecutar();
        $this->assertSame(ResultadoGeneracionAgendaEnum::FALLIDA, $primera->resultado);
        $original = $primera->preparaciones()->where('kinesiologa_id', $bien->id)->sole();
        $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, $original->resultado);
        $fallar = false;
        $lecturas = [];
        $segunda = $servicio->ejecutar();
        $this->assertSame([$mal->id], $lecturas);
        $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, $segunda->resultado);
        $this->assertNotSame($primera->id, $segunda->id);
        $reutilizada = $segunda->preparaciones()->where('kinesiologa_id', $bien->id)->sole();
        $this->assertSame($original->id, $reutilizada->preparacion_origen_id);
        $this->assertTrue($reutilizada->origen->is($original));
        $this->assertSame(0, $reutilizada->total_turnos);
        $this->assertSame(ResultadoGeneracionAgendaEnum::FALLIDA, $primera->fresh()->resultado);
        $this->assertSame('PREPARACION_JORNADA', $primera->preparaciones()->where('kinesiologa_id', $mal->id)->sole()->codigo_incidencia->value);
        $this->assertTrue($segunda->is($servicio->ejecutar()));
        $this->assertDatabaseCount('ejecuciones_agenda_diaria', 2);
        $this->assertDatabaseCount('preparaciones_agenda_diaria', 4);
    }

    public function test_fallo_de_seleccion_se_registra_y_puede_recuperarse_sin_fingir_vacio(): void
    {
        $this->profesionalUc07();
        $fallar = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$fallar): void {
            if ($fallar && str_starts_with($sql, 'select * from "kinesiologas"')) {
                throw new RuntimeException('Fallo de selección simulado');
            }
        });
        $servicio = app(GenerarAgendaDiariaService::class);
        $fallida = $servicio->ejecutar();
        $this->assertSame(ResultadoGeneracionAgendaEnum::FALLIDA, $fallida->resultado);
        $this->assertSame(IncidenciaGeneracionAgendaEnum::SELECCION_PROFESIONALES, $fallida->codigo_incidencia);
        $this->assertCount(0, $fallida->preparaciones);
        $fallar = false;
        $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, $servicio->ejecutar()->resultado);
        $this->assertDatabaseCount('preparaciones_agenda_diaria', 1);
    }

    public function test_segunda_invocacion_durante_una_lectura_no_reclama_la_jornada_activa(): void
    {
        $this->profesionalUc07();
        $entrar = true;
        $segunda = null;
        DB::connection()->beforeExecuting(function (string $sql) use (&$entrar, &$segunda): void {
            if ($entrar && str_starts_with($sql, 'select') && str_contains($sql, 'from "turnos"')) {
                $entrar = false;
                $segunda = app(GenerarAgendaDiariaService::class)->ejecutar();
            }
        });
        $primera = app(GenerarAgendaDiariaService::class)->ejecutar();
        $this->assertSame(ResultadoGeneracionAgendaEnum::EN_CURSO, $segunda->resultado);
        $this->assertTrue($primera->is($segunda));
        $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, $primera->resultado);
        $this->assertDatabaseCount('ejecuciones_agenda_diaria', 1);
        $this->assertDatabaseCount('preparaciones_agenda_diaria', 1);
    }

    public function test_reclamo_vencido_recupera_interrupcion_y_rechaza_escritura_del_trabajador_anterior(): void
    {
        $this->profesionalUc07();
        $entrar = true;
        $recuperada = null;
        DB::connection()->beforeExecuting(function (string $sql) use (&$entrar, &$recuperada): void {
            if ($entrar && str_starts_with($sql, 'select') && str_contains($sql, 'from "turnos"')) {
                $entrar = false;
                CarbonImmutable::setTestNow(CarbonImmutable::now(config('app.timezone'))->addSeconds(GenerarAgendaDiariaService::RECLAMO_SEGUNDOS));
                $recuperada = app(GenerarAgendaDiariaService::class)->ejecutar();
            }
        });
        try {
            app(GenerarAgendaDiariaService::class)->ejecutar();
            $this->fail('El trabajador viejo no debe registrar resultados.');
        } catch (ReclamoGeneracionPerdidoException) {
            $anterior = EjecucionAgendaDiaria::orderBy('id')->firstOrFail();
            $this->assertSame(ResultadoGeneracionAgendaEnum::FALLIDA, $anterior->resultado);
            $this->assertSame(IncidenciaGeneracionAgendaEnum::EJECUCION_INTERRUMPIDA, $anterior->codigo_incidencia);
            $this->assertSame(IncidenciaGeneracionAgendaEnum::EJECUCION_INTERRUMPIDA, $anterior->preparaciones->sole()->codigo_incidencia);
            $this->assertNull($anterior->preparaciones->sole()->total_turnos);
            $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, $recuperada->resultado);
            $this->assertSame($recuperada->id, JornadaGeneracionAgenda::sole()->ejecucion_actual_id);
        }
    }

    public function test_excepcion_de_persistencia_deja_inicio_durable_recuperable_despues_del_vencimiento(): void
    {
        $this->profesionalUc07();
        $fallar = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$fallar): void {
            if ($fallar && str_starts_with($sql, 'update "preparaciones_agenda_diaria"')) {
                throw new RuntimeException('Fallo de escritura simulado');
            }
        });
        try {
            app(GenerarAgendaDiariaService::class)->ejecutar();
            $this->fail('El fallo de persistencia debe propagarse.');
        } catch (RuntimeException) {
            $fallar = false;
            $this->assertSame(ResultadoGeneracionAgendaEnum::EN_CURSO, EjecucionAgendaDiaria::sole()->resultado);
            $this->assertNull(PreparacionAgendaDiaria::sole()->finalizado_at);
            $this->assertNull(PreparacionAgendaDiaria::sole()->total_turnos);
        }
        CarbonImmutable::setTestNow(CarbonImmutable::now(config('app.timezone'))->addSeconds(301));
        $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, app(GenerarAgendaDiariaService::class)->ejecutar()->resultado);
        $this->assertDatabaseCount('ejecuciones_agenda_diaria', 2);
    }

    public function test_generacion_no_modifica_otras_tablas_y_consulta_vigente_ignora_su_resultado(): void
    {
        $profesional = $this->profesionalUc07();
        $paciente = $this->pacienteUc07();
        $turno = Turno::factory()->for($profesional)->for($paciente)->create([
            'inicio_at' => '2026-11-05 06:00:00', 'fin_at' => '2026-11-05 06:30:00',
        ]);
        $antes = [];
        foreach (Schema::getTableListing() as $tabla) {
            if (! in_array(Str::afterLast($tabla, '.'), ['ejecuciones_agenda_diaria', 'preparaciones_agenda_diaria', 'jornadas_generacion_agenda'], true)) {
                $antes[$tabla] = DB::table($tabla)->get()->toJson();
            }
        }
        app(GenerarAgendaDiariaService::class)->ejecutar();
        foreach ($antes as $tabla => $contenido) {
            $this->assertSame($contenido, DB::table($tabla)->get()->toJson(), $tabla);
        }
        $turno->forceFill(['estado' => EstadoTurnoEnum::CANCELADO])->save();
        $nuevo = Turno::factory()->for($profesional)->for($paciente)->create([
            'inicio_at' => '2026-11-05 08:00:00', 'fin_at' => '2026-11-05 08:30:00',
        ]);
        app(GenerarAgendaDiariaService::class)->ejecutar();
        $fecha = CarbonImmutable::parse('2026-11-05');
        $vigentes = app(AgendaAtencionQueryService::class)->informacionDePeriodo($profesional, $fecha, $fecha);
        $this->assertSame([$turno->id, $nuevo->id], array_column($vigentes, 'id'));
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $vigentes[0]['estado']);
        $this->assertSame(1, PreparacionAgendaDiaria::sole()->total_turnos);
    }

    public function test_restricciones_logicas_y_fk_se_aplican_en_sqlite(): void
    {
        app(GenerarAgendaDiariaService::class)->ejecutar();
        $control = JornadaGeneracionAgenda::sole();
        foreach ([
            fn () => DB::table('jornadas_generacion_agenda')->insert(['fecha_local' => $control->fecha_local, 'zona_horaria' => $control->zona_horaria]),
            fn () => DB::table('jornadas_generacion_agenda')->where('id', $control->id)->update(['ejecucion_actual_id' => 999999]),
            fn () => DB::table('ejecuciones_agenda_diaria')->where('id', $control->ejecucion_actual_id)->delete(),
        ] as $operacion) {
            try {
                $operacion();
                $this->fail('Debe preservarse la integridad del control.');
            } catch (QueryException) {
                $this->assertDatabaseCount('jornadas_generacion_agenda', 1);
            }
        }
    }

    public function test_uc05_http_sigue_disponible_tras_fallo_y_muestra_cambios_posteriores(): void
    {
        $this->withoutVite();
        $profesional = $this->profesionalUc07();
        $paciente = $this->pacienteUc07();
        $turno = Turno::factory()->for($profesional)->for($paciente)->create([
            'inicio_at' => '2026-11-05 06:00:00', 'fin_at' => '2026-11-05 06:30:00',
        ]);
        $fallar = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$fallar): void {
            if ($fallar && str_starts_with($sql, 'select') && str_contains($sql, 'from "turnos"')) {
                throw new RuntimeException('No revelar datos internos');
            }
        });
        $this->assertSame(ResultadoGeneracionAgendaEnum::FALLIDA, app(GenerarAgendaDiariaService::class)->ejecutar()->resultado);
        $fallar = false;
        $this->actingAs($profesional->user);
        $url = route('turnos.agenda-atencion.index', ['fecha' => '2026-11-05']);
        $this->get($url)->assertOk()->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === [$turno->id]);
        foreach ([EstadoTurnoEnum::CONFIRMADO, EstadoTurnoEnum::EN_ATENCION, EstadoTurnoEnum::ATENDIDO, EstadoTurnoEnum::AUSENTE, EstadoTurnoEnum::CANCELADO] as $estado) {
            // Simular resultados ya consolidados por sus UC responsables, sin implementarlos.
            $turno->forceFill(['estado' => $estado])->save();
            $this->get($url)->assertOk()->assertViewHas('turnos', fn ($turnos) => $turnos[0]['estado'] === $estado);
        }
    }

    public function test_reutilizacion_conserva_fk_de_origen_y_no_admite_resultados_de_otra_jornada(): void
    {
        $profesional = $this->profesionalUc07();
        $ejecucion = app(GenerarAgendaDiariaService::class)->ejecutar();
        $origen = $ejecucion->preparaciones->sole();
        $registro = app(RegistroGeneracionAgendaService::class);
        $otra = $registro->iniciar((string) Str::uuid(), CarbonImmutable::parse('2026-11-04'));
        try {
            $registro->reutilizarAgenda($otra, $origen);
            $this->fail('No se puede reutilizar el resultado de otra fecha.');
        } catch (\InvalidArgumentException) {
            $this->assertCount(0, $otra->fresh()->preparaciones);
        }
        $misma = $registro->iniciar((string) Str::uuid());
        $reutilizada = $registro->reutilizarAgenda($misma, $origen);
        try {
            DB::table('preparaciones_agenda_diaria')->where('id', $origen->id)->delete();
            $this->fail('El resultado referenciado debe conservarse.');
        } catch (QueryException) {
            $this->assertTrue($reutilizada->fresh()->origen->is($origen));
            $this->assertSame($profesional->id, $reutilizada->kinesiologa_id);
        }
    }

    public function test_migracion_de_control_compila_offline_para_mariadb(): void
    {
        $this->assertFalse(extension_loaded('pdo_mysql'));
        $conexion = new class(fn () => throw new RuntimeException('Prohibida conexión real'), 'offline', '', ['driver' => 'mariadb', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']) extends MariaDbConnection
        {
            public function getServerVersion(): string
            {
                return '10.4.32';
            }
        };
        $original = Schema::getFacadeRoot();
        Schema::swap($conexion->getSchemaBuilder());
        try {
            $migracion = require database_path('migrations/2026_11_05_000002_add_control_jornada_generacion_agenda.php');
            $sql = implode("\n", array_column($conexion->pretend(fn () => $migracion->up()), 'query'));
        } finally {
            Schema::swap($original);
        }
        $this->assertStringContainsString('jornada_agenda_fecha_zona_unique', $sql);
        $this->assertStringContainsString('preparacion_origen_id', $sql);
        $this->assertStringContainsString('on delete restrict on update restrict', $sql);
        $this->assertStringNotContainsString('cascade', strtolower($sql));
        $this->assertStringNotContainsString('enum(', strtolower($sql));
    }
}
