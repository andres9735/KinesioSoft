<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\EstadoRecordatorioTurnoEnum as Estado;
use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Models\Auditoria;
use App\Models\Turno;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use App\Services\Turnos\RegistrarRecordatorioTurnoService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Grammars\MariaDbGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class RegistrarRecordatorioTurnoServiceTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Turno $turno;

    private RegistrarRecordatorioTurnoService $servicio;

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
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 10:00:00', config('app.timezone')));
        $this->turno = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'inicio_at' => '2026-11-07 10:00:00', 'fin_at' => '2026-11-07 10:30:00',
            'motivo_solicitud' => 'CLINICO-NO-EXPONER',
        ])->fresh();
        $this->servicio = app(RegistrarRecordatorioTurnoService::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_identidad_idempotente_snapshot_minimo_y_turno_intacto(): void
    {
        Mail::fake();
        Queue::fake();
        $antes = $this->turno->getAttributes();
        $registro = $this->servicio->registrar($this->turno->id);
        $otra = $this->servicio->registrar($this->turno->id);
        $this->assertSame($registro->id, $otra->id);
        $this->assertTrue(Str::isUuid($registro->identidad));
        $this->assertSame(Estado::PENDIENTE, $registro->estado);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('recordatorios_turno', 1);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 0);
        $this->assertDatabaseCount('auditorias', 1);
        foreach ([DB::table('recordatorios_turno')->get()->toJson(), Auditoria::all()->toJson(), $registro->toJson(), print_r($registro, true)] as $salida) {
            $this->assertStringNotContainsString('CLINICO-NO-EXPONER', $salida);
            $this->assertStringNotContainsString('?token=', $salida);
        }
        $this->assertArrayNotHasKey('destinatario', $registro->toArray());
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_confirmar_no_crea_identidad_y_aba_si_crea_otra_sin_reactivar_historia(): void
    {
        $registro = $this->servicio->registrar($this->turno->id);
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'CONFIRMADO']);
        $this->assertSame($registro->id, $this->servicio->registrar($this->turno->id)->id);
        DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-08 10:00:00']);
        DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-07 10:00:00']);
        $actual = $this->servicio->registrar($this->turno->id);
        $this->assertNotSame($registro->id, $actual->id);
        $this->assertGreaterThan($registro->version_programacion, $actual->version_programacion);
        $this->assertFalse($registro->correspondeA($this->turno->fresh()));
    }

    #[DataProvider('resultados')]
    public function test_resultados_conocidos_y_aceptacion_no_presume_entrega(Resultado $resultado, Estado $estado): void
    {
        $registro = $this->servicio->registrar($this->turno->id);
        $clave = (string) Str::uuid();
        $intento = $this->servicio->iniciarIntento($registro->id, $clave);
        $this->assertSame(Resultado::INCIERTO, $intento->resultado);
        $this->assertNull($intento->resuelto_at);
        $this->assertSame($intento->id, $this->servicio->iniciarIntento($registro->id, $clave)->id);
        $evidencia = $resultado === Resultado::ENTREGA_VERIFICADA ? (string) Str::uuid() : null;
        $this->servicio->registrarResultado($intento->id, $resultado, $evidencia);
        $cantidad = Auditoria::count();
        $this->servicio->registrarResultado($intento->id, $resultado, $evidencia);
        $this->assertSame($cantidad, Auditoria::count());
        $this->assertSame($estado, $registro->fresh()->estado);
        $this->assertSame($resultado, $intento->fresh()->resultado);
        $this->assertSame($evidencia, $intento->fresh()->evidencia_entrega);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 1);
    }

    public static function resultados(): array
    {
        return [
            [Resultado::ACEPTADO, Estado::PROCESADO], [Resultado::ENTREGA_VERIFICADA, Estado::PROCESADO],
            [Resultado::FALLO_RECUPERABLE, Estado::FALLIDO], [Resultado::FALLO_DEFINITIVO, Estado::FALLIDO],
            [Resultado::INCIERTO, Estado::INCIERTO],
        ];
    }

    public function test_aceptado_solo_puede_evolucionar_a_entrega_con_evidencia(): void
    {
        $registro = $this->servicio->registrar($this->turno->id);
        $intento = $this->servicio->iniciarIntento($registro->id, (string) Str::uuid());
        $this->servicio->registrarResultado($intento->id, Resultado::ACEPTADO);
        $this->assertNull($intento->fresh()->evidencia_entrega);
        try {
            $this->servicio->registrarResultado($intento->id, Resultado::ENTREGA_VERIFICADA);
            $this->fail('No admite entrega sin evidencia.');
        } catch (DomainException) {
            $this->assertSame(Resultado::ACEPTADO, $intento->fresh()->resultado);
        }
        $this->servicio->registrarResultado($intento->id, Resultado::ENTREGA_VERIFICADA, (string) Str::uuid());
        $this->assertSame(Resultado::ENTREGA_VERIFICADA, $intento->fresh()->resultado);
    }

    public function test_incierto_impide_nuevo_intento_y_no_es_fallo_confirmado(): void
    {
        $registro = $this->servicio->registrar($this->turno->id);
        $this->servicio->iniciarIntento($registro->id, (string) Str::uuid());
        $this->expectException(DomainException::class);
        $this->servicio->iniciarIntento($registro->id, (string) Str::uuid());
    }

    public function test_persistencia_limita_a_tres_reintentos_sin_ejecutarlos(): void
    {
        $registro = $this->servicio->registrar($this->turno->id);
        for ($numero = 1; $numero <= 4; $numero++) {
            $intento = $this->servicio->iniciarIntento($registro->id, (string) Str::uuid());
            $this->assertSame($numero, $intento->numero);
            $this->servicio->registrarResultado($intento->id, Resultado::FALLO_RECUPERABLE);
        }
        $this->expectException(DomainException::class);
        $this->servicio->iniciarIntento($registro->id, (string) Str::uuid());
    }

    public function test_ventana_de_hora_desde_primer_fallo_no_se_reinicia(): void
    {
        $registro = $this->servicio->registrar($this->turno->id);
        $intento = $this->servicio->iniciarIntento($registro->id, (string) Str::uuid());
        $this->servicio->registrarResultado($intento->id, Resultado::FALLO_RECUPERABLE);
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addHour());
        $intento = $this->servicio->iniciarIntento($registro->id, (string) Str::uuid());
        $this->servicio->registrarResultado($intento->id, Resultado::FALLO_RECUPERABLE);
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMicrosecond());
        $this->expectException(DomainException::class);
        $this->servicio->iniciarIntento($registro->id, (string) Str::uuid());
    }

    public function test_sobre_se_asocia_solo_por_referencias_sin_descifrar_y_sin_duplicar(): void
    {
        $registro = $this->servicio->registrar($this->turno->id);
        $emitido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
        $this->servicio->asociarSobre($registro->id, $emitido->sobreId);
        $auditorias = Auditoria::count();
        $this->servicio->asociarSobre($registro->id, $emitido->sobreId);
        $this->assertSame($auditorias, Auditoria::count());
        $this->assertSame($emitido->sobreId, (int) $registro->fresh()->sobre_id);
        $url = $emitido->urlParaCorreo();
        $this->assertStringNotContainsString($url, DB::table('recordatorios_turno')->get()->toJson());
        $this->assertStringNotContainsString($url, Auditoria::all()->toJson());
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
    }

    public function test_referencia_revocada_no_se_asocia(): void
    {
        $registro = $this->servicio->registrar($this->turno->id);
        $emitido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
        DB::table('enlaces_asistencia_turno')->where('id', $emitido->enlaceId)->update(['revocado_at' => '2026-11-06 10:00:00']);
        $this->expectException(DomainException::class);
        $this->servicio->asociarSobre($registro->id, $emitido->sobreId);
    }

    public function test_correo_invalido_registra_incidencia_sin_copiar_entrada(): void
    {
        $this->turno->paciente->user->update(['email' => 'https://ajeno.test/?token=NO-PERSISTIR']);
        $registro = $this->servicio->registrar($this->turno->id);
        $this->assertNull($registro->destinatario);
        $this->assertSame('CORREO_NO_UTILIZABLE', $registro->incidencia);
        $this->assertSame(Estado::FALLIDO, $registro->estado);
        $this->assertStringNotContainsString('NO-PERSISTIR', DB::table('recordatorios_turno')->get()->toJson());
        $this->assertDatabaseCount('intentos_recordatorio_turno', 0);
    }

    public function test_auditoria_obligatoria_atomica(): void
    {
        DB::connection()->beforeExecuting(function (string $sql): void {
            if (str_starts_with(strtolower($sql), 'insert into "auditorias"')) {
                throw new RuntimeException('Fallo sintético de auditoría');
            }
        });
        try {
            $this->servicio->registrar($this->turno->id);
            $this->fail('Debió fallar auditoría.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('recordatorios_turno', 0);
        }
    }

    public function test_unicidad_real_de_turno_revision_aun_por_sql_directo(): void
    {
        $registro = $this->servicio->registrar($this->turno->id);
        $datos = $registro->getAttributes();
        unset($datos['id']);
        $datos['identidad'] = (string) Str::uuid();
        $this->expectException(QueryException::class);
        DB::table('recordatorios_turno')->insert($datos);
    }

    public function test_fk_real_impide_intento_huerfano(): void
    {
        $this->expectException(QueryException::class);
        DB::table('intentos_recordatorio_turno')->insert([
            'recordatorio_id' => 999999, 'identidad' => (string) Str::uuid(), 'numero' => 1,
            'destinatario' => 'destino@example.test', 'resultado' => 'INCIERTO', 'iniciado_at' => '2026-11-06 10:00:00',
        ]);
    }

    public function test_migracion_aditiva_up_down_aislados_y_ddl_mariadb_sin_conexion(): void
    {
        // Sólo las dos tablas nuevas; nunca revertir infraestructura histórica.
        $migration = require database_path('migrations/2026_11_06_000002_create_recordatorios_turno_tables.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseCount('recordatorios_turno', 0);
        $connection = new class(fn () => throw new RuntimeException('Prohibido conectar'), '', '', []) extends MariaDbConnection
        {
            public function getServerVersion(): string
            {
                return '10.4.32';
            }
        };
        $connection->setSchemaGrammar(new MariaDbGrammar($connection));
        $original = DB::getDefaultConnection();
        DB::extend('recordatorio_offline', fn () => $connection);
        config(['database.connections.recordatorio_offline' => ['driver' => 'recordatorio_offline']]);
        DB::setDefaultConnection('recordatorio_offline');
        try {
            $sql = $connection->pretend(fn () => $migration->up());
            $texto = strtolower(json_encode($sql));
            $this->assertStringContainsString('innodb', $texto);
            $this->assertStringContainsString('recordatorio_turno_version_unique', $texto);
            $this->assertStringContainsString('foreign key', $texto);
            $this->assertStringNotContainsString(' enum(', $texto);
        } finally {
            DB::setDefaultConnection($original);
        }
    }

    public function test_modelos_no_aceptan_secretos_campos_libres_o_serializacion_para_jobs(): void
    {
        $registro = $this->servicio->registrar($this->turno->id);
        foreach (['token' => 'NO-PERSISTIR', 'incidencia' => 'https://ajeno.test/?token=NO-PERSISTIR', 'estado' => 'INVENTADO'] as $campo => $valor) {
            try {
                DB::transaction(fn () => $registro->fresh()->forceFill([$campo => $valor])->saveOrFail());
                $this->fail('No admite campo o valor ajeno al contrato.');
            } catch (LogicException|\ValueError) {
                $this->assertStringNotContainsString('NO-PERSISTIR', DB::table('recordatorios_turno')->get()->toJson());
            }
        }
        $this->expectException(LogicException::class);
        serialize($registro);
    }

    public function test_unicidad_real_de_numero_de_intento_y_credenciales_ausentes(): void
    {
        $registro = $this->servicio->registrar($this->turno->id);
        $intento = $this->servicio->iniciarIntento($registro->id, (string) Str::uuid());
        $datos = $intento->getAttributes();
        $this->assertSame($registro->destinatario, $intento->destinatario);
        $this->assertArrayNotHasKey('destinatario', $intento->toArray());
        $this->assertStringNotContainsString('CLINICO-NO-EXPONER', json_encode($datos));
        unset($datos['id']);
        $datos['identidad'] = (string) Str::uuid();
        $this->expectException(QueryException::class);
        DB::table('intentos_recordatorio_turno')->insert($datos);
    }

    public function test_fallo_auditoria_resultado_revierte_a_incierto_y_no_modifica_turno(): void
    {
        $antes = $this->turno->getAttributes();
        $registro = $this->servicio->registrar($this->turno->id);
        $intento = $this->servicio->iniciarIntento($registro->id, (string) Str::uuid());
        DB::connection()->beforeExecuting(function (string $sql): void {
            if (str_starts_with(strtolower($sql), 'insert into "auditorias"')) {
                throw new RuntimeException('Fallo sintético');
            }
        });
        try {
            $this->servicio->registrarResultado($intento->id, Resultado::ACEPTADO);
            $this->fail('Debió revertirse el resultado.');
        } catch (RuntimeException) {
            $this->assertSame(Resultado::INCIERTO, $intento->fresh()->resultado);
            $this->assertSame(Estado::INCIERTO, $registro->fresh()->estado);
            $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        }
    }

    public function test_modelo_no_permite_asociar_sobre_de_otro_turno(): void
    {
        $registro = $this->servicio->registrar($this->turno->id);
        $otro = Turno::factory()->for($this->turno->paciente)->for($this->turno->kinesiologa)->create([
            'inicio_at' => '2026-11-07 11:00:00', 'fin_at' => '2026-11-07 11:30:00',
        ]);
        $emitido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($otro->id);
        $this->expectException(LogicException::class);
        DB::transaction(fn () => $registro->forceFill(['enlace_id' => $emitido->enlaceId, 'sobre_id' => $emitido->sobreId])->saveOrFail());
    }
}
