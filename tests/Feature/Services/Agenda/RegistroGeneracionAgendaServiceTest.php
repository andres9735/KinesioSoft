<?php

namespace Tests\Feature\Services\Agenda;

use App\Enums\EstadoTurnoEnum;
use App\Enums\IncidenciaGeneracionAgendaEnum;
use App\Enums\ResultadoGeneracionAgendaEnum;
use App\Models\Turno;
use App\Services\Agenda\AgendaAtencionQueryService;
use App\Services\Agenda\JornadaAgendaPreparada;
use App\Services\Agenda\PrepararJornadaAgendaService;
use App\Services\Agenda\RegistroGeneracionAgendaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class RegistroGeneracionAgendaServiceTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private RegistroGeneracionAgendaService $registro;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 01:30:00 UTC'));
        $this->registro = app(RegistroGeneracionAgendaService::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_identidad_temporal_local_y_relaciones_sin_resultados_ficticios(): void
    {
        $clave = (string) Str::uuid();
        $ejecucion = $this->registro->iniciar(strtoupper($clave));
        $this->assertSame($clave, $ejecucion->clave_ejecucion);
        $this->assertSame('2026-11-05', $ejecucion->fecha_local->toDateString());
        $this->assertSame('2026-11-05', $ejecucion->getRawOriginal('fecha_local'));
        $this->assertSame(config('app.timezone'), $ejecucion->zona_horaria);
        $this->assertSame('2026-11-05 22:30:00', $ejecucion->iniciado_at->toDateTimeString());
        $this->assertSame(ResultadoGeneracionAgendaEnum::EN_CURSO, $ejecucion->resultado);
        $this->assertNull($ejecucion->finalizado_at);
        $this->assertNull($ejecucion->codigo_incidencia);
        $this->assertCount(0, $ejecucion->preparaciones);

        $profesional = $this->profesionalUc07();
        $agenda = $this->registro->iniciarAgenda($ejecucion, $profesional);
        $this->assertTrue($agenda->ejecucion->is($ejecucion));
        $this->assertTrue($agenda->kinesiologa->is($profesional));
        $this->assertCount(1, $ejecucion->fresh()->preparaciones);
        foreach (['total_turnos', 'turnos_operativos', 'turnos_en_atencion', 'otros_turnos', 'codigo_incidencia', 'finalizado_at'] as $campo) {
            $this->assertNull($agenda->$campo);
        }
    }

    public function test_sin_profesionales_finaliza_sin_agendas_ficticias_y_con_resultado(): void
    {
        $this->assertTrue(app(PrepararJornadaAgendaService::class)->profesionalesHabilitadas()->isEmpty());
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $final = $this->registro->finalizar($ejecucion);
        $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, $final->resultado);
        $this->assertNotNull($final->finalizado_at);
        $this->assertDatabaseCount('preparaciones_agenda_diaria', 0);
    }

    public function test_fecha_civil_objetivo_es_independiente_del_instante_de_procesamiento(): void
    {
        $ejecucion = $this->registro->iniciar((string) Str::uuid(), CarbonImmutable::parse('2026-11-04 23:00:00', 'Pacific/Auckland'));
        $this->assertSame('2026-11-04', $ejecucion->fecha_local->toDateString());
        $this->assertSame('2026-11-05', $ejecucion->iniciado_at->toDateString());
    }

    public function test_unicidad_de_ejecucion_en_base_de_datos(): void
    {
        $clave = (string) Str::uuid();
        $this->registro->iniciar($clave);
        try {
            $this->registro->iniciar($clave);
            $this->fail('La identidad de ejecución debe ser única.');
        } catch (QueryException) {
            $this->assertDatabaseCount('ejecuciones_agenda_diaria', 1);
        }
    }

    public function test_unicidad_por_ejecucion_y_profesional_sin_confundir_ejecuciones_distintas(): void
    {
        $profesional = $this->profesionalUc07();
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $this->registro->iniciarAgenda($ejecucion, $profesional);
        try {
            $this->registro->iniciarAgenda($ejecucion, $profesional);
            $this->fail('Una profesional no puede duplicarse en la ejecución.');
        } catch (QueryException) {
            $this->assertDatabaseCount('preparaciones_agenda_diaria', 1);
        }
        $otraEjecucion = $this->registro->iniciar((string) Str::uuid());
        $otra = $this->registro->iniciarAgenda($otraEjecucion, $profesional);
        $this->assertSame($otraEjecucion->id, $otra->ejecucion_id);
        $this->assertDatabaseCount('ejecuciones_agenda_diaria', 2);
        $this->assertDatabaseCount('preparaciones_agenda_diaria', 2);
    }

    public function test_jornada_vacia_tiene_ceros_solo_despues_de_prepararla(): void
    {
        $profesional = $this->profesionalUc07();
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $agenda = $this->registro->iniciarAgenda($ejecucion, $profesional);
        $this->assertNull($agenda->total_turnos);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 01:30:03 UTC'));
        $final = $this->registro->completarAgenda($agenda, app(PrepararJornadaAgendaService::class)->preparar($profesional));
        $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, $final->resultado);
        foreach (['total_turnos', 'turnos_operativos', 'turnos_en_atencion', 'otros_turnos'] as $campo) {
            $this->assertSame(0, $final->$campo);
        }
        $this->assertSame(3.0, (float) $final->iniciado_at->diffInSeconds($final->finalizado_at));
        $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, $this->registro->finalizar($ejecucion)->resultado);
    }

    public function test_fallo_parcial_conserva_exito_individual_y_no_persiste_excepciones(): void
    {
        $profesional = $this->profesionalUc07();
        $otra = $this->profesionalUc07();
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $exito = $this->registro->iniciarAgenda($ejecucion, $profesional);
        $fallo = $this->registro->iniciarAgenda($ejecucion, $otra);
        $this->registro->completarAgenda($exito, app(PrepararJornadaAgendaService::class)->preparar($profesional));
        $error = $this->registro->fallarAgenda($fallo);
        $final = $this->registro->finalizar($ejecucion);
        $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, $exito->fresh()->resultado);
        $this->assertSame(ResultadoGeneracionAgendaEnum::FALLIDA, $error->resultado);
        $this->assertNull($error->total_turnos);
        $this->assertNotNull($error->finalizado_at);
        $this->assertSame(IncidenciaGeneracionAgendaEnum::PREPARACION_JORNADA, $error->codigo_incidencia);
        $this->assertSame(ResultadoGeneracionAgendaEnum::FALLIDA, $final->resultado);
        $this->assertSame(IncidenciaGeneracionAgendaEnum::PREPARACION_JORNADA, $final->codigo_incidencia);
        $this->assertFalse(Schema::hasColumn('preparaciones_agenda_diaria', 'mensaje_error'));
        $this->assertFalse(Schema::hasColumn('preparaciones_agenda_diaria', 'turnos'));
        $this->assertFalse(Schema::hasColumn('preparaciones_agenda_diaria', 'paciente_id'));
    }

    public function test_fallo_de_seleccion_es_distinto_de_cero_profesionales(): void
    {
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $fallo = $this->registro->fallarSeleccion($ejecucion);
        $this->assertSame(ResultadoGeneracionAgendaEnum::FALLIDA, $fallo->resultado);
        $this->assertSame(IncidenciaGeneracionAgendaEnum::SELECCION_PROFESIONALES, $fallo->codigo_incidencia);
        $this->assertNotNull($fallo->finalizado_at);
        $this->assertDatabaseCount('preparaciones_agenda_diaria', 0);
    }

    public function test_persiste_conteos_operativos_en_curso_e_historicos_sin_copiar_los_turnos(): void
    {
        $profesional = $this->profesionalUc07();
        $paciente = $this->pacienteUc07();
        foreach (EstadoTurnoEnum::cases() as $estado) {
            Turno::factory()->for($profesional)->for($paciente)->create([
                'estado' => $estado, 'inicio_at' => '2026-11-05 06:00:00', 'fin_at' => '2026-11-05 06:30:00',
            ]);
        }
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $agenda = $this->registro->iniciarAgenda($ejecucion, $profesional);
        $final = $this->registro->completarAgenda($agenda, app(PrepararJornadaAgendaService::class)->preparar($profesional));
        $this->assertSame(6, $final->total_turnos);
        $this->assertSame(2, $final->turnos_operativos);
        $this->assertSame(1, $final->turnos_en_atencion);
        $this->assertSame(3, $final->otros_turnos);
        $this->assertSame(6, Turno::count());
    }

    public function test_no_finaliza_antes_del_inicio_si_el_reloj_retrocede(): void
    {
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 01:29:59 UTC'));
        try {
            $this->registro->finalizar($ejecucion);
            $this->fail('No corresponde registrar un intervalo temporal negativo.');
        } catch (LogicException) {
            $this->assertSame(ResultadoGeneracionAgendaEnum::EN_CURSO, $ejecucion->fresh()->resultado);
            $this->assertNull($ejecucion->fresh()->finalizado_at);
        }
    }

    public function test_cierre_interpreta_momentos_en_la_zona_registrada_aunque_el_proceso_cambie_de_zona(): void
    {
        $profesional = $this->profesionalUc07();
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $agenda = $this->registro->iniciarAgenda($ejecucion, $profesional);
        $datos = app(PrepararJornadaAgendaService::class)->preparar($profesional);
        config(['app.timezone' => 'UTC']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 01:30:05 UTC'));
        $final = $this->registro->completarAgenda($agenda, $datos);
        $this->registro->finalizar($ejecucion);
        $this->assertSame('2026-11-05 22:30:05.000000', $final->getRawOriginal('finalizado_at'));
        $this->assertSame('2026-11-05', $ejecucion->fresh()->fecha_local->toDateString());
        $this->assertSame('America/Argentina/Buenos_Aires', $ejecucion->fresh()->zona_horaria);
    }

    public function test_fallo_de_seleccion_no_puede_ocultar_preparaciones_ya_iniciadas(): void
    {
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $this->registro->iniciarAgenda($ejecucion, $this->profesionalUc07());
        try {
            $this->registro->fallarSeleccion($ejecucion);
            $this->fail('El fallo debe registrarse sobre la preparación correspondiente.');
        } catch (LogicException) {
            $this->assertSame(ResultadoGeneracionAgendaEnum::EN_CURSO, $ejecucion->fresh()->resultado);
            $this->assertDatabaseCount('preparaciones_agenda_diaria', 1);
        }
    }

    public function test_interrupcion_conserva_en_curso_y_no_permite_finalizar_como_vacio(): void
    {
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $agenda = $this->registro->iniciarAgenda($ejecucion, $this->profesionalUc07());
        try {
            $this->registro->finalizar($ejecucion);
            $this->fail('Falta resultado profesional.');
        } catch (LogicException) {
            $this->assertSame(ResultadoGeneracionAgendaEnum::EN_CURSO, $ejecucion->fresh()->resultado);
            $this->assertSame(ResultadoGeneracionAgendaEnum::EN_CURSO, $agenda->fresh()->resultado);
            $this->assertNull($agenda->fresh()->total_turnos);
        }
    }

    #[DataProvider('ambitosIncompatibles')]
    public function test_rechaza_resultado_de_otra_profesional_fecha_o_zona_sin_cierre_parcial(string $campo): void
    {
        $profesional = $this->profesionalUc07();
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $agenda = $this->registro->iniciarAgenda($ejecucion, $profesional);
        $jornada = new JornadaAgendaPreparada(
            $campo === 'profesional' ? $profesional->id + 1 : $profesional->id,
            CarbonImmutable::parse($campo === 'fecha' ? '2026-11-06' : '2026-11-05'),
            $campo === 'zona' ? 'UTC' : config('app.timezone'), [],
        );
        try {
            $this->registro->completarAgenda($agenda, $jornada);
            $this->fail('Ámbito incorrecto.');
        } catch (InvalidArgumentException) {
            $this->assertSame(ResultadoGeneracionAgendaEnum::EN_CURSO, $agenda->fresh()->resultado);
            $this->assertNull($agenda->fresh()->total_turnos);
            $this->assertNull($agenda->fresh()->finalizado_at);
        }
    }

    public static function ambitosIncompatibles(): array
    {
        return [['profesional'], ['fecha'], ['zona']];
    }

    public function test_estado_terminal_no_se_reabre_ni_sobrescribe_con_objeto_obsoleto(): void
    {
        $profesional = $this->profesionalUc07();
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $agenda = $this->registro->iniciarAgenda($ejecucion, $profesional);
        $obsoleta = clone $agenda;
        $this->registro->fallarAgenda($agenda);
        try {
            $this->registro->completarAgenda($obsoleta, app(PrepararJornadaAgendaService::class)->preparar($profesional));
            $this->fail('No se puede sobrescribir el resultado previo.');
        } catch (LogicException) {
            $this->assertSame(ResultadoGeneracionAgendaEnum::FALLIDA, $agenda->fresh()->resultado);
        }
        $this->registro->finalizar($ejecucion);
        $this->expectException(LogicException::class);
        $this->registro->iniciarAgenda($ejecucion, $this->profesionalUc07());
    }

    #[DataProvider('identidadesInmutables')]
    public function test_modelos_preservan_identidad_y_trazabilidad(string $operacion): void
    {
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $agenda = $this->registro->iniciarAgenda($ejecucion, $this->profesionalUc07());
        $this->expectException(LogicException::class);
        match ($operacion) {
            'fecha' => $ejecucion->forceFill(['fecha_local' => '2026-11-06'])->save(),
            'profesional' => $agenda->forceFill(['kinesiologa_id' => 999])->save(),
            'eliminar ejecucion' => $ejecucion->delete(),
            'eliminar preparacion' => $agenda->delete(),
        };
    }

    public static function identidadesInmutables(): array
    {
        return [['fecha'], ['profesional'], ['eliminar ejecucion'], ['eliminar preparacion']];
    }

    public function test_fk_impiden_huerfanos_y_borrados_destructivos_incluso_por_sql(): void
    {
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $profesional = $this->profesionalUc07();
        $agenda = $this->registro->iniciarAgenda($ejecucion, $profesional);
        foreach ([
            fn () => DB::table('preparaciones_agenda_diaria')->where('id', $agenda->id)->update(['ejecucion_id' => 999999]),
            fn () => DB::table('preparaciones_agenda_diaria')->where('id', $agenda->id)->update(['kinesiologa_id' => 999999]),
            fn () => DB::table('ejecuciones_agenda_diaria')->where('id', $ejecucion->id)->delete(),
            fn () => DB::table('kinesiologas')->where('id', $profesional->id)->delete(),
        ] as $operacion) {
            try {
                $operacion();
                $this->fail('La FK debe impedir esta operación.');
            } catch (QueryException) {
                $this->assertTrue($agenda->fresh()->exists);
            }
        }
    }

    public function test_uuid_invalida_se_rechaza_sin_escribir(): void
    {
        try {
            $this->registro->iniciar('texto no permitido');
            $this->fail('Se requiere una UUID.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('ejecuciones_agenda_diaria', 0);
        }
    }

    public function test_fallo_de_persistencia_no_pierde_el_inicio_ni_finge_finalizacion(): void
    {
        $ejecucion = $this->registro->iniciar((string) Str::uuid());
        $agenda = $this->registro->iniciarAgenda($ejecucion, $this->profesionalUc07());
        $fallar = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$fallar): void {
            if ($fallar && str_starts_with($sql, 'update "preparaciones_agenda_diaria"')) {
                throw new RuntimeException('No persistir este SQL, token o dato clínico simulado');
            }
        });
        try {
            $this->registro->fallarAgenda($agenda);
            $this->fail('Debía propagarse el fallo de persistencia.');
        } catch (RuntimeException) {
            $fallar = false;
            $this->assertSame(ResultadoGeneracionAgendaEnum::EN_CURSO, $agenda->fresh()->resultado);
            $this->assertNull($agenda->fresh()->finalizado_at);
            $this->assertNull($agenda->fresh()->codigo_incidencia);
        }
        $this->assertSame(ResultadoGeneracionAgendaEnum::FALLIDA, $this->registro->fallarAgenda($agenda)->resultado);
    }

    public function test_preparar_y_registrar_solo_escribe_bitacora_y_no_congela_consulta_vigente(): void
    {
        $profesional = $this->profesionalUc07();
        $paciente = $this->pacienteUc07();
        $turno = Turno::factory()->for($profesional)->for($paciente)->create([
            'inicio_at' => '2026-11-05 06:00:00', 'fin_at' => '2026-11-05 06:45:00',
            'motivo_solicitud' => 'Información clínica privada',
        ]);
        $antes = [];
        foreach (Schema::getTableListing() as $tabla) {
            if (! in_array(Str::afterLast($tabla, '.'), ['ejecuciones_agenda_diaria', 'preparaciones_agenda_diaria'], true)) {
                $antes[$tabla] = DB::table($tabla)->get()->toJson();
            }
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $ejecucion = $this->registro->iniciar((string) Str::uuid());
            $agenda = $this->registro->iniciarAgenda($ejecucion, $profesional);
            $datos = app(PrepararJornadaAgendaService::class)->preparar($profesional);
            $this->registro->completarAgenda($agenda, $datos);
            $this->registro->finalizar($ejecucion);
            $consultas = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        foreach ($consultas as $consulta) {
            if (preg_match('/^(insert|update|delete)\b/i', $consulta['query'])) {
                $this->assertMatchesRegularExpression('/^(insert into|update) "(ejecuciones_agenda_diaria|preparaciones_agenda_diaria)"/i', $consulta['query']);
            }
        }
        foreach ($antes as $tabla => $contenido) {
            $this->assertSame($contenido, DB::table($tabla)->get()->toJson(), $tabla);
        }
        $this->assertSame(1, $agenda->fresh()->total_turnos);
        $this->assertStringNotContainsString('Información clínica', $agenda->fresh()->toJson());
        $turno->forceFill(['estado' => EstadoTurnoEnum::CANCELADO])->save();
        $lectura = app(AgendaAtencionQueryService::class)->informacionDePeriodo($profesional, $datos->fechaLocal, $datos->fechaLocal);
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $lectura[0]['estado']);
        $this->assertSame(1, $agenda->fresh()->turnos_operativos);
    }

    public function test_ddl_mariadb_se_compila_offline_sin_pdo_mysql_ni_enum_nativo(): void
    {
        $this->assertFalse(extension_loaded('pdo_mysql'));
        $conexion = new class(fn () => throw new RuntimeException('Prohibida conexión real'), 'prueba_offline', '', ['driver' => 'mariadb', 'version' => '10.4.32', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']) extends MariaDbConnection
        {
            public function getServerVersion(): string
            {
                return '10.4.32';
            }
        };
        $original = Schema::getFacadeRoot();
        Schema::swap($conexion->getSchemaBuilder());
        try {
            $migration = require database_path('migrations/2026_11_05_000001_create_ejecuciones_agenda_diaria_tables.php');
            $sql = array_column($conexion->pretend(fn () => $migration->up()), 'query');
        } finally {
            Schema::swap($original);
        }
        $ddl = strtolower(implode("\n", $sql));
        $this->assertStringContainsString('create table `ejecuciones_agenda_diaria`', $ddl);
        $this->assertStringContainsString('create table `preparaciones_agenda_diaria`', $ddl);
        $this->assertSame(2, substr_count($ddl, 'engine = innodb'));
        $this->assertStringContainsString('prep_agenda_ejec_prof_unique', $ddl);
        $this->assertStringContainsString('ejec_agenda_clave_unique', $ddl);
        $this->assertStringContainsString('on delete restrict on update restrict', $ddl);
        $this->assertStringNotContainsString('enum(', $ddl);
        $this->assertStringNotContainsString('cascade', $ddl);
        $this->assertStringNotContainsString('`turnos`', $ddl);
    }
}
