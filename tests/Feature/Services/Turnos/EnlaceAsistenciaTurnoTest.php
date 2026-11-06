<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\Turno;
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

class EnlaceAsistenciaTurnoTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Turno $turno;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->turno = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'tipo' => TipoTurnoEnum::SEGUIMIENTO, 'inicio_at' => '2026-11-08 10:00:00', 'fin_at' => '2026-11-08 10:30:00',
        ])->fresh();
    }

    public function test_persiste_solo_hash_oculto_y_contexto_administrativo_sin_alterar_turno(): void
    {
        $token = bin2hex(random_bytes(32));
        $antes = $this->turno->getAttributes();
        $enlace = $this->crear(['token_hash' => hash('sha256', $token)]);
        $this->assertTrue($enlace->coincideConToken($token));
        $this->assertFalse($enlace->coincideConToken(bin2hex(random_bytes(32))));
        $this->assertFalse($enlace->coincideConToken($token.'x'));
        $this->assertArrayNotHasKey('token_hash', $enlace->toArray());
        $this->assertFalse(str_contains(DB::table('enlaces_asistencia_turno')->get()->toJson(), $token));
        foreach (['token', 'url', 'motivo_solicitud', 'documento_id'] as $columna) {
            $this->assertFalse(Schema::hasColumn('enlaces_asistencia_turno', $columna));
        }
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertTrue($enlace->turno->is($this->turno));
        $this->assertSame($this->turno->paciente_id, $enlace->paciente->id);
        $this->assertSame($this->turno->kinesiologa_id, $enlace->kinesiologa->id);
    }

    #[DataProvider('asociacionesInvalidas')]
    public function test_no_persiste_una_asociacion_incoherente(string $campo, mixed $valor): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->crear([$campo => $valor]);
    }

    public static function asociacionesInvalidas(): array
    {
        return [
            ['paciente_id', 999999], ['kinesiologa_id', 999999], ['version_programacion', 2],
            ['inicio_at', '2026-11-08 09:00:00'], ['fin_at', '2026-11-08 11:00:00'],
            ['tipo', 'PRIMERA_CONSULTA'], ['alcance', 'PORTAL_GENERAL'], ['token_hash', 'invalido'],
            ['vence_at', '2026-11-07 09:00:00'], ['ciclo_emision', 'no-uuid'],
        ];
    }

    public function test_vigencia_hasta_inicio_sin_caducidad_rigida_24_horas_y_confirmar_no_consume(): void
    {
        $enlace = $this->crear();
        $this->assertTrue($enlace->vigentePara($this->turno, CarbonImmutable::parse('2026-11-07 10:00:00')));
        $this->turno->forceFill(['estado' => EstadoTurnoEnum::CONFIRMADO])->save();
        $this->turno->refresh();
        $this->assertSame(1, EnlaceAsistenciaTurno::versionActualDe($this->turno));
        $this->assertTrue($enlace->vigentePara($this->turno, CarbonImmutable::parse('2026-11-07 22:00:00')));
        $this->assertTrue($enlace->vigentePara($this->turno, CarbonImmutable::parse('2026-11-08 09:59:59')));
        $this->assertFalse($enlace->vigentePara($this->turno, CarbonImmutable::parse('2026-11-08 10:00:00')));
        $this->assertFalse($enlace->vigentePara($this->turno, CarbonImmutable::parse('2026-11-05 23:59:59')));
    }

    public function test_reprogramacion_sql_ida_y_vuelta_no_rehabilita_el_enlace_anterior(): void
    {
        $enlace = $this->crear();
        DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-09 10:00:00', 'fin_at' => '2026-11-09 10:30:00']);
        $this->assertSame(2, EnlaceAsistenciaTurno::versionActualDe($this->turno));
        DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-08 10:00:00', 'fin_at' => '2026-11-08 10:30:00']);
        $this->assertSame(3, EnlaceAsistenciaTurno::versionActualDe($this->turno));
        $this->assertFalse($enlace->vigentePara($this->turno->fresh(), CarbonImmutable::parse('2026-11-07 10:00:00')));
    }

    #[DataProvider('estadosIncompatibles')]
    public function test_estado_incompatible_invalida_sin_rehabilitacion_por_reapertura(EstadoTurnoEnum $estado): void
    {
        $enlace = $this->crear();
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => $estado->value]);
        $this->assertFalse($enlace->vigentePara($this->turno->fresh(), CarbonImmutable::parse('2026-11-07 10:00:00')));
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'PENDIENTE']);
        $this->assertFalse($enlace->vigentePara($this->turno->fresh(), CarbonImmutable::parse('2026-11-07 10:00:00')));
    }

    public static function estadosIncompatibles(): array
    {
        return array_map(fn ($e) => [$e], [EstadoTurnoEnum::CANCELADO, EstadoTurnoEnum::EN_ATENCION, EstadoTurnoEnum::ATENDIDO, EstadoTurnoEnum::AUSENTE]);
    }

    public function test_revision_no_retrocede_y_cambios_ajenos_no_la_incrementan(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['motivo_solicitud' => 'Cambio ajeno a programación']);
        $this->assertSame(1, EnlaceAsistenciaTurno::versionActualDe($this->turno));
        $this->expectException(QueryException::class);
        DB::table('revisiones_enlace_asistencia_turno')->where('turno_id', $this->turno->id)->update(['version_programacion' => 0]);
    }

    #[DataProvider('camposDeProgramacion')]
    public function test_cambios_de_propiedad_tipo_o_fin_tambien_invalidan_por_revision(string $campo): void
    {
        $enlace = $this->crear();
        $original = $this->turno->getRawOriginal($campo);
        $nuevo = match ($campo) {
            'paciente_id' => $this->pacienteUc07()->id,
            'kinesiologa_id' => $this->profesionalUc07()->id,
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA->value,
            'fin_at' => '2026-11-08 11:00:00',
        };
        DB::table('turnos')->where('id', $this->turno->id)->update([$campo => $nuevo]);
        $this->assertSame(2, EnlaceAsistenciaTurno::versionActualDe($this->turno));
        DB::table('turnos')->where('id', $this->turno->id)->update([$campo => $original]);
        $this->assertSame(3, EnlaceAsistenciaTurno::versionActualDe($this->turno));
        $this->assertFalse($enlace->vigentePara($this->turno->fresh(), CarbonImmutable::parse('2026-11-07 10:00:00')));
    }

    public static function camposDeProgramacion(): array
    {
        return [['paciente_id'], ['kinesiologa_id'], ['tipo'], ['fin_at']];
    }

    public function test_trigger_no_recursa_con_recursive_triggers_activo(): void
    {
        DB::statement('PRAGMA recursive_triggers = ON');
        try {
            DB::table('turnos')->where('id', $this->turno->id)->update(['fin_at' => '2026-11-08 11:00:00']);
            $this->assertSame(2, EnlaceAsistenciaTurno::versionActualDe($this->turno));
        } finally {
            DB::statement('PRAGMA recursive_triggers = OFF');
        }
    }

    public function test_correctiva_migracion_inicializa_turnos_previos_sin_cambiar_su_esquema_o_datos(): void
    {
        $antes = $this->turno->getAttributes();
        $columnas = Schema::getColumnListing('turnos');
        $migracion = require database_path('migrations/2026_11_05_000003_create_enlaces_asistencia_turno_table.php');
        $migracion->down();
        $this->assertSame($columnas, Schema::getColumnListing('turnos'));
        $migracion->up();
        $this->assertSame($columnas, Schema::getColumnListing('turnos'));
        $this->assertArrayNotHasKey('version_enlace_asistencia', $antes);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertSame(1, EnlaceAsistenciaTurno::versionActualDe($this->turno));
        $this->assertTrue($this->crear()->vigentePara($this->turno, CarbonImmutable::parse('2026-11-07 10:00:00')));
    }

    public function test_correctiva_revision_no_puede_eliminarse_ni_reasignarse_para_rehabilitar_enlaces(): void
    {
        $enlace = $this->crear();
        $otro = Turno::factory()->for($this->turno->paciente)->for($this->turno->kinesiologa)->create();
        foreach ([
            fn () => DB::table('revisiones_enlace_asistencia_turno')->where('turno_id', $this->turno->id)->delete(),
            fn () => DB::table('revisiones_enlace_asistencia_turno')->where('turno_id', $this->turno->id)->update(['turno_id' => $otro->id]),
        ] as $operacion) {
            try {
                $operacion();
                $this->fail('La revisión histórica debe conservar su identidad.');
            } catch (QueryException) {
                $this->assertSame(1, EnlaceAsistenciaTurno::versionActualDe($this->turno));
                $this->assertTrue($enlace->vigentePara($this->turno, CarbonImmutable::parse('2026-11-07 10:00:00')));
            }
        }
    }

    public function test_correctiva_revision_ausente_falla_cerrado_sin_reconstruirla(): void
    {
        $sinRevision = (new Turno)->newFromBuilder(['id' => 999999]);
        $this->expectException(LogicException::class);
        EnlaceAsistenciaTurno::versionActualDe($sinRevision);
    }

    public function test_correctiva_eliminar_turno_sin_enlaces_no_borra_revision_y_reusar_id_no_la_reinicia(): void
    {
        $original = $this->turno->getAttributes();
        DB::table('turnos')->where('id', $this->turno->id)->delete();
        $this->assertDatabaseMissing('turnos', ['id' => $this->turno->id]);
        $this->assertDatabaseHas('revisiones_enlace_asistencia_turno', ['turno_id' => $this->turno->id, 'version_programacion' => 1]);
        DB::table('turnos')->insert($original);
        $this->assertSame(2, EnlaceAsistenciaTurno::versionActualDe($this->turno));
        $this->assertSame($original, $this->turno->fresh()->getAttributes());
    }

    #[DataProvider('caminosSinEventos')]
    public function test_correctiva_aba_tambien_se_detecta_sin_eventos_eloquent_y_en_actualizaciones_masivas(string $camino): void
    {
        $enlace = $this->crear();
        $otro = Turno::factory()->for($this->turno->paciente)->for($this->turno->kinesiologa)->create([
            'tipo' => TipoTurnoEnum::SEGUIMIENTO, 'inicio_at' => '2026-11-08 10:00:00', 'fin_at' => '2026-11-08 10:30:00',
        ])->fresh();
        $antes = $this->turno->getAttributes();
        $antesOtro = $otro->getAttributes();
        $ids = $camino === 'masiva' ? [$this->turno->id, $otro->id] : [$this->turno->id];
        foreach (['2026-11-09', '2026-11-08'] as $indice => $fecha) {
            $datos = ['inicio_at' => $fecha.' 10:00:00', 'fin_at' => $fecha.' 10:30:00'];
            match ($camino) {
                'quiet' => $this->turno->forceFill($datos)->saveQuietly(),
                'sql' => DB::update('UPDATE turnos SET inicio_at = ?, fin_at = ? WHERE id = ?', [...array_values($datos), $this->turno->id]),
                'masiva' => Turno::query()->whereIn('id', $ids)->update($datos),
            };
            $this->assertSame($indice + 2, EnlaceAsistenciaTurno::versionActualDe($this->turno));
            $this->assertSame($camino === 'masiva' ? $indice + 2 : 1, EnlaceAsistenciaTurno::versionActualDe($otro));
        }
        $this->assertFalse($enlace->vigentePara($this->turno->fresh(), CarbonImmutable::parse('2026-11-07 10:00:00')));
        $this->assertSame(array_diff_key($antes, ['updated_at' => true]), array_diff_key($this->turno->fresh()->getAttributes(), ['updated_at' => true]));
        $this->assertSame(array_diff_key($antesOtro, ['updated_at' => true]), array_diff_key($otro->fresh()->getAttributes(), ['updated_at' => true]));
    }

    public static function caminosSinEventos(): array
    {
        return [['quiet'], ['sql'], ['masiva']];
    }

    public function test_revocacion_es_irreversible_e_identidad_inmutable(): void
    {
        $enlace = $this->crear();
        $enlace->forceFill(['revocado_at' => '2026-11-07 09:00:00'])->save();
        $this->assertFalse($enlace->vigentePara($this->turno, CarbonImmutable::parse('2026-11-07 10:00:00')));
        $this->expectException(LogicException::class);
        $enlace->forceFill(['revocado_at' => null])->save();
    }

    public function test_unicidad_de_version_hash_y_ciclo_y_fk_restrictivas(): void
    {
        $enlace = $this->crear();
        $datos = DB::table('enlaces_asistencia_turno')->where('id', $enlace->id)->first();
        $otra = Turno::factory()->for($this->turno->paciente)->for($this->turno->kinesiologa)->create()->fresh();
        foreach ([
            fn () => DB::table('enlaces_asistencia_turno')->insert(array_merge((array) $datos, ['id' => 999])),
            fn () => DB::table('enlaces_asistencia_turno')->insert(array_merge((array) $datos, ['id' => 999, 'turno_id' => $otra->id, 'ciclo_emision' => (string) Str::uuid()])),
            fn () => DB::table('enlaces_asistencia_turno')->insert(array_merge((array) $datos, ['id' => 999, 'turno_id' => $otra->id, 'token_hash' => hash('sha256', 'otro')])),
            fn () => DB::table('turnos')->where('id', $this->turno->id)->delete(),
            fn () => DB::table('enlaces_asistencia_turno')->where('id', $enlace->id)->update(['turno_id' => 999999]),
        ] as $operacion) {
            try {
                $operacion();
                $this->fail('La integridad debe impedir esta operación.');
            } catch (QueryException) {
                $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
            }
        }
    }

    public function test_identidad_no_puede_reescribirse_ni_eliminarse(): void
    {
        $enlace = $this->crear();
        foreach ([fn () => $enlace->forceFill(['token_hash' => hash('sha256', 'cambio')])->save(), fn () => $enlace->fresh()->delete()] as $operacion) {
            try {
                $operacion();
                $this->fail('El enlace debe conservarse.');
            } catch (LogicException) {
                $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
            }
        }
    }

    public function test_ddl_mariadb_compilado_y_sql_del_trigger_generado_offline_sin_conexion(): void
    {
        $this->assertFalse(extension_loaded('pdo_mysql'));
        $conexion = new class(fn () => throw new RuntimeException('Prohibida conexión real'), 'offline', '', ['driver' => 'mariadb']) extends MariaDbConnection
        {
            public function getServerVersion(): string
            {
                return '10.4.32';
            }
        };
        $original = Schema::getFacadeRoot();
        Schema::swap($conexion->getSchemaBuilder());
        try {
            $migracion = require database_path('migrations/2026_11_05_000003_create_enlaces_asistencia_turno_table.php');
            $sql = implode("\n", array_column($conexion->pretend(fn () => $migracion->up()), 'query'));
        } finally {
            Schema::swap($original);
        }
        $this->assertStringContainsString('CREATE TRIGGER turnos_enlace_version_actualizar AFTER UPDATE', $sql);
        $this->assertStringContainsString('UPDATE revisiones_enlace_asistencia_turno SET version_programacion = version_programacion + 1', $sql);
        $this->assertStringNotContainsString('alter table `turnos`', $sql);
        $this->assertStringNotContainsString('UPDATE turnos SET', $sql);
        $this->assertStringContainsString('enlace_asistencia_turno_version_unique', $sql);
        $this->assertStringContainsString('on delete restrict on update restrict', $sql);
        $this->assertStringNotContainsString('enum(', strtolower($sql));
        $this->assertStringNotContainsString('cascade', strtolower($sql));
    }

    private function crear(array $datos = []): EnlaceAsistenciaTurno
    {
        $enlace = new EnlaceAsistenciaTurno;
        $enlace->forceFill(array_merge([
            'turno_id' => $this->turno->id, 'paciente_id' => $this->turno->paciente_id,
            'kinesiologa_id' => $this->turno->kinesiologa_id, 'version_programacion' => EnlaceAsistenciaTurno::versionActualDe($this->turno),
            'ciclo_emision' => (string) Str::uuid(), 'token_hash' => hash('sha256', bin2hex(random_bytes(32))),
            'alcance' => EnlaceAsistenciaTurno::ALCANCE, 'tipo' => $this->turno->tipo->value,
            'inicio_at' => $this->turno->getRawOriginal('inicio_at'), 'fin_at' => $this->turno->getRawOriginal('fin_at'),
            'zona_horaria' => config('app.timezone'), 'emitido_at' => '2026-11-06 00:00:00', 'vence_at' => '2026-11-08 10:00:00',
        ], $datos))->saveOrFail();

        return $enlace;
    }
}
