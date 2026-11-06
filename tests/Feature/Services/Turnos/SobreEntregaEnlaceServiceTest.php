<?php

namespace Tests\Feature\Services\Turnos;

use App\Exceptions\Turnos\EntregaEnlaceNoDisponibleException;
use App\Models\Auditoria;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\SobreEntregaEnlace;
use App\Models\Turno;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use App\Services\Turnos\SobreEntregaEnlaceService;
use App\Services\Turnos\ValidarEnlaceAsistenciaTurnoService;
use Carbon\CarbonImmutable;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class SobreEntregaEnlaceServiceTest extends TestCase
{
    use CreaPerfilesUc07;

    private Turno $turno;

    private SobreEntregaEnlaceService $sobres;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || extension_loaded('pdo_mysql')) {
            throw new LogicException('Estas migraciones requieren SQLite en memoria y PDO MySQL ausente.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Conexión :memory: propia de esta aplicación, sin transacción envolvente:
        // permite demostrar el callback externo y se descarta al destruir la app.
        // No revertir migraciones históricas para limpiar los datos sintéticos.
        $this->beforeRefreshingDatabase();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 07:00:00', config('app.timezone')));
        $this->turno = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'inicio_at' => '2026-11-07 10:00:00', 'fin_at' => '2026-11-07 10:30:00',
            'motivo_solicitud' => 'CLINICO-NO-EXPONER',
        ])->fresh();
        $this->sobres = app(SobreEntregaEnlaceService::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_persistencia_cifrada_idempotente_y_compatibilidad_con_validacion(): void
    {
        DB::enableQueryLog();
        $antes = $this->turno->getAttributes();
        $emitido = $this->emitir();
        $url = $emitido->urlParaCorreo();
        $token = $this->token($url);
        $sobre = SobreEntregaEnlace::sole();
        $queries = json_encode(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($sobre->id, $emitido->sobreId);
        $this->assertSame($emitido->enlaceId, $sobre->enlace_id);
        $this->assertSame($url, json_decode(Crypt::decryptString($sobre->material_cifrado), true)['url']);
        foreach ([$queries, DB::table('sobres_entrega_enlace')->get()->toJson(), DB::table('enlaces_asistencia_turno')->get()->toJson(), Auditoria::all()->toJson(), $sobre->toJson(), print_r($sobre, true), json_encode($emitido)] as $representacion) {
            $this->assertStringNotContainsString($token, $representacion);
            $this->assertStringNotContainsString($url, $representacion);
            $this->assertStringNotContainsString('CLINICO-NO-EXPONER', $representacion);
        }
        $this->assertArrayNotHasKey('material_cifrado', $sobre->toArray());
        $segundo = $this->emitir();
        $this->assertSame($emitido->sobreId, $segundo->sobreId);
        $this->assertSame($emitido->enlaceId, $segundo->enlaceId);
        $this->assertNull($segundo->urlParaCorreo());
        $this->assertSame('valido', app(ValidarEnlaceAsistenciaTurnoService::class)->validar($token)->estado);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('sobres_entrega_enlace', 1);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
        $this->assertDatabaseCount('auditorias', 1);
        $this->expectException(LogicException::class);
        serialize($sobre);
    }

    #[DataProvider('fallosPreparacion')]
    public function test_fallo_de_preparacion_revierte_sobre_enlace_y_auditoria(string $fallo): void
    {
        if ($fallo === 'cifrado') {
            Crypt::shouldReceive('encryptString')->once()->andThrow(new RuntimeException('SECRETO-DEL-PROVEEDOR'));
        } else {
            DB::connection()->beforeExecuting(function (string $sql): void {
                if (str_starts_with($sql, 'insert into "sobres_entrega_enlace"')) {
                    throw new RuntimeException('SECRETO-DEL-PROVEEDOR');
                }
            });
        }
        try {
            $this->emitir();
            $this->fail('La preparación debe ser atómica.');
        } catch (EntregaEnlaceNoDisponibleException $error) {
            $this->assertNull($error->getPrevious());
            $this->assertStringNotContainsString('SECRETO-DEL-PROVEEDOR', (string) $error);
            $this->assertDatabaseCount('enlaces_asistencia_turno', 0);
            $this->assertDatabaseCount('sobres_entrega_enlace', 0);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public static function fallosPreparacion(): array
    {
        return [['cifrado'], ['persistencia']];
    }

    public function test_material_recuperado_fuera_de_transaccion_mismo_token_y_reclamo_no_reutilizable(): void
    {
        $emitido = $this->emitir();
        $reclamoAnterior = null;
        $this->sobres->conMaterialParaDespacho($emitido->sobreId, function ($material, $reclamo) use ($emitido, &$reclamoAnterior): void {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame($emitido->urlParaCorreo(), $material->urlParaCorreo());
            $this->assertSame('valido', app(ValidarEnlaceAsistenciaTurnoService::class)->validar($this->token($material->urlParaCorreo()))->estado);
            $reclamoAnterior = $reclamo;
            $this->sobres->registrarResultado($material->sobreId, $reclamo, 'FALLO_CONFIRMADO');
        });
        $this->sobres->conMaterialParaDespacho($emitido->sobreId, function ($material, $reclamo) use ($emitido, $reclamoAnterior): void {
            $this->assertSame($emitido->urlParaCorreo(), $material->urlParaCorreo());
            $this->assertNotSame($reclamoAnterior, $reclamo);
            try {
                $this->sobres->registrarResultado($material->sobreId, $reclamoAnterior, 'ACEPTADO');
                $this->fail('Un reclamo anterior no debe finalizar el actual.');
            } catch (EntregaEnlaceNoDisponibleException $error) {
                $this->assertSame('reclamo_invalido', $error->motivo);
            }
        });
        $this->assertSame('INCIERTO', SobreEntregaEnlace::sole()->estado);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
    }

    #[DataProvider('contextosRechazados')]
    public function test_recuperacion_rechaza_contexto_no_autorizado(string $contexto): void
    {
        $emitido = $this->emitir();
        if ($contexto === 'http') {
            request()->setRouteResolver(fn () => new Route('GET', 'simulada', fn () => null));
        } elseif ($contexto === 'sesion') {
            $this->actingAs($this->turno->paciente->user);
        } else {
            DB::beginTransaction();
        }
        try {
            $this->sobres->conMaterialParaDespacho($emitido->sobreId, fn () => $this->fail('No debe exponerse material.'));
            $this->fail('Debe rechazarse este contexto.');
        } catch (EntregaEnlaceNoDisponibleException $error) {
            $this->assertSame($contexto === 'transaccion' ? 'transaccion_externa' : 'contexto_no_autorizado', $error->motivo);
        } finally {
            if ($contexto === 'transaccion') {
                DB::rollBack();
            }
        }
        $this->assertSame('DISPONIBLE', SobreEntregaEnlace::sole()->estado);
    }

    public static function contextosRechazados(): array
    {
        return [['http'], ['sesion'], ['transaccion']];
    }

    #[DataProvider('materialesInvalidos')]
    public function test_cifrado_alterado_o_sustituido_no_entrega(string $caso): void
    {
        $emitido = $this->emitir();
        $cifrado = 'alterado';
        if ($caso === 'asociacion') {
            $otro = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create(['inicio_at' => '2026-11-07 11:00:00', 'fin_at' => '2026-11-07 11:30:00']);
            $segundo = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($otro->id);
            $cifrado = SobreEntregaEnlace::findOrFail($segundo->sobreId)->material_cifrado;
        }
        DB::table('sobres_entrega_enlace')->where('id', $emitido->sobreId)->update(['material_cifrado' => $cifrado]);
        try {
            $this->sobres->conMaterialParaDespacho($emitido->sobreId, fn () => $this->fail('No debe descifrarse material inválido.'));
            $this->fail('Debe rechazarse el material.');
        } catch (EntregaEnlaceNoDisponibleException $error) {
            $this->assertSame('material_invalido', $error->motivo);
            $this->assertNull($error->getPrevious());
        }
    }

    public static function materialesInvalidos(): array
    {
        return [['mac'], ['asociacion']];
    }

    #[DataProvider('invalidaciones')]
    public function test_revocacion_expiracion_y_aba_impiden_despacho_y_eliminan_material(string $caso): void
    {
        $emitido = $this->emitir();
        if ($caso === 'revocacion') {
            EnlaceAsistenciaTurno::findOrFail($emitido->enlaceId)->forceFill(['revocado_at' => CarbonImmutable::now(config('app.timezone'))])->save();
        } elseif ($caso === 'expiracion') {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-07 10:00:00', config('app.timezone')));
        } else {
            $this->turno->forceFill(['inicio_at' => '2026-11-08 10:00:00'])->save();
            $this->turno->forceFill(['inicio_at' => '2026-11-07 10:00:00'])->save();
        }
        try {
            $this->sobres->conMaterialParaDespacho($emitido->sobreId, fn () => $this->fail('No debe despacharse.'));
            $this->fail('Debe rechazarse este despacho.');
        } catch (EntregaEnlaceNoDisponibleException) {
            $sobre = SobreEntregaEnlace::sole();
            $this->assertSame('CERRADO', $sobre->estado);
            $this->assertNull($sobre->material_cifrado);
            $this->assertNotNull($sobre->cerrado_at);
            $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
        }
    }

    public static function invalidaciones(): array
    {
        return [['revocacion'], ['expiracion'], ['aba']];
    }

    #[DataProvider('resultadosTerminales')]
    public function test_cierre_idempotente_borra_material_sin_reemitir(string $resultado): void
    {
        $emitido = $this->emitir();
        $this->sobres->conMaterialParaDespacho($emitido->sobreId, function ($material, $reclamo) use ($resultado): void {
            $this->sobres->registrarResultado($material->sobreId, $reclamo, $resultado);
            $auditorias = Auditoria::count();
            $this->sobres->registrarResultado($material->sobreId, $reclamo, $resultado);
            $this->assertSame($auditorias, Auditoria::count());
        });
        $sobre = SobreEntregaEnlace::sole();
        $this->assertNull($sobre->material_cifrado);
        $this->assertSame($resultado, $sobre->resultado);
        $this->assertSame($emitido->sobreId, $this->emitir()->sobreId);
        $this->assertNull($this->emitir()->urlParaCorreo());
        $this->expectException(EntregaEnlaceNoDisponibleException::class);
        $this->sobres->conMaterialParaDespacho($emitido->sobreId, fn () => null);
    }

    public static function resultadosTerminales(): array
    {
        return [['ACEPTADO'], ['FALLO_DEFINITIVO']];
    }

    public function test_interrupcion_conserva_incertidumbre_no_filtra_ni_reenvia(): void
    {
        $emitido = $this->emitir();
        Log::spy();
        try {
            $this->sobres->conMaterialParaDespacho($emitido->sobreId, fn ($material) => throw new RuntimeException($material->urlParaCorreo()));
            $this->fail('Debe comunicarse la interrupción de forma segura.');
        } catch (EntregaEnlaceNoDisponibleException $error) {
            $this->assertSame('consumidor_interrumpido', $error->motivo);
            $this->assertNull($error->getPrevious());
            $this->assertStringNotContainsString($this->token($emitido->urlParaCorreo()), (string) $error);
        }
        Log::shouldNotHaveReceived('error');
        $this->assertSame('INCIERTO', SobreEntregaEnlace::sole()->estado);
        $this->expectException(EntregaEnlaceNoDisponibleException::class);
        $this->sobres->conMaterialParaDespacho($emitido->sobreId, fn () => $this->fail('No repetir un despacho incierto.'));
    }

    public function test_vigencia_solo_se_reduce_y_depuracion_borra_cifrado(): void
    {
        $emitido = $this->emitir();
        $limite = CarbonImmutable::now(config('app.timezone'))->addHour();
        $this->sobres->limitarVigencia($emitido->sobreId, $limite);
        $this->sobres->limitarVigencia($emitido->sobreId, $limite->addDay());
        $this->assertTrue(SobreEntregaEnlace::sole()->vence_at->equalTo($limite));
        CarbonImmutable::setTestNow($limite);
        $this->sobres->depurar($emitido->sobreId);
        $this->assertNull(SobreEntregaEnlace::sole()->material_cifrado);
        $auditorias = Auditoria::count();
        $this->sobres->depurar($emitido->sobreId);
        $this->assertSame($auditorias, Auditoria::count());
    }

    public function test_rotacion_con_clave_anterior_recupera_y_recifra_con_clave_activa(): void
    {
        $anterior = random_bytes(32);
        $this->clave($anterior);
        $emitido = $this->emitir();
        $cifrado = SobreEntregaEnlace::sole()->material_cifrado;
        $actual = random_bytes(32);
        $this->clave($actual, [$anterior]);
        $this->sobres->conMaterialParaDespacho($emitido->sobreId, function ($material) use ($emitido): void {
            $this->assertSame($emitido->urlParaCorreo(), $material->urlParaCorreo());
        });
        $nuevo = SobreEntregaEnlace::sole()->material_cifrado;
        $this->assertNotSame($cifrado, $nuevo);
        $this->clave($actual);
        $this->assertSame($emitido->urlParaCorreo(), json_decode(Crypt::decryptString($nuevo), true)['url']);
    }

    public function test_migracion_sin_backfill_historico_no_recuperable_sin_reemision(): void
    {
        $emitido = $this->emitir();
        $migracion = require database_path('migrations/2026_11_06_000001_create_sobres_entrega_enlace_table.php');
        $migracion->down();
        $antes = DB::table('enlaces_asistencia_turno')->get()->toJson();
        $migracion->up();
        $repetido = $this->emitir();
        $this->assertSame($emitido->enlaceId, $repetido->enlaceId);
        $this->assertNull($repetido->sobreId);
        $this->assertNull($repetido->urlParaCorreo());
        $this->assertSame($antes, DB::table('enlaces_asistencia_turno')->get()->toJson());
        $this->assertDatabaseCount('sobres_entrega_enlace', 0);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_integridad_unicidad_fk_y_ddl_mariadb_offline(): void
    {
        $emitido = $this->emitir();
        $datos = (array) DB::table('sobres_entrega_enlace')->sole();
        foreach ([['id' => 999], ['id' => 999, 'enlace_id' => 99999]] as $cambio) {
            try {
                DB::table('sobres_entrega_enlace')->insert(array_replace($datos, $cambio));
                $this->fail('Debe preservarse la integridad.');
            } catch (QueryException) {
                $this->assertDatabaseCount('sobres_entrega_enlace', 1);
            }
        }
        $conexion = new class(fn () => throw new RuntimeException('Sin conexión real'), 'offline', '', ['driver' => 'mariadb']) extends MariaDbConnection
        {
            public function getServerVersion(): string
            {
                return '10.4.32';
            }
        };
        $original = Schema::getFacadeRoot();
        Schema::swap($conexion->getSchemaBuilder());
        try {
            $migracion = require database_path('migrations/2026_11_06_000001_create_sobres_entrega_enlace_table.php');
            $sql = implode("\n", array_column($conexion->pretend(fn () => $migracion->up()), 'query'));
        } finally {
            Schema::swap($original);
        }
        $this->assertStringContainsString('longtext', $sql);
        $this->assertStringContainsString('on delete restrict on update restrict', $sql);
        $this->assertStringContainsString('unique', $sql);
        $this->assertStringNotContainsString('enum(', strtolower($sql));
        $this->assertSame($emitido->enlaceId, SobreEntregaEnlace::sole()->enlace_id);
    }

    public function test_fallo_auditoria_de_reclamo_no_expone_material_y_revierte_estado(): void
    {
        $emitido = $this->emitir();
        DB::connection()->beforeExecuting(function (string $sql, array $bindings) use ($emitido): void {
            if (str_starts_with($sql, 'insert into "auditorias"') && in_array('sobre_entrega_actualizado', $bindings, true)) {
                throw new RuntimeException($emitido->urlParaCorreo());
            }
        });
        try {
            $this->sobres->conMaterialParaDespacho($emitido->sobreId, fn () => $this->fail('No exponer material sin auditoría.'));
            $this->fail('Debe revertir el reclamo.');
        } catch (EntregaEnlaceNoDisponibleException $error) {
            $this->assertSame('preparacion_interrumpida', $error->motivo);
            $this->assertNull($error->getPrevious());
            $this->assertStringNotContainsString($this->token($emitido->urlParaCorreo()), (string) $error);
        }
        $this->assertSame('DISPONIBLE', SobreEntregaEnlace::sole()->estado);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_vencimiento_durante_preparacion_impide_exponer_material(): void
    {
        $emitido = $this->emitir();
        DB::connection()->beforeExecuting(function (string $sql, array $bindings): void {
            if (str_starts_with($sql, 'insert into "auditorias"') && in_array('sobre_entrega_actualizado', $bindings, true)) {
                CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-07 10:00:00', config('app.timezone')));
            }
        });
        try {
            $this->sobres->conMaterialParaDespacho($emitido->sobreId, fn () => $this->fail('No exponer un enlace vencido.'));
            $this->fail('Debe revalidarse el instante final.');
        } catch (EntregaEnlaceNoDisponibleException $error) {
            $this->assertSame('vigencia_modificada', $error->motivo);
        }
        $this->assertSame('DISPONIBLE', SobreEntregaEnlace::sole()->estado);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_resultado_incierto_repetido_despues_del_vencimiento_elimina_material(): void
    {
        $emitido = $this->emitir();
        $reclamo = null;
        $this->sobres->conMaterialParaDespacho($emitido->sobreId, function ($material, $id) use (&$reclamo): void {
            $reclamo = $id;
        });
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-07 10:00:00', config('app.timezone')));
        $this->sobres->registrarResultado($emitido->sobreId, $reclamo, 'INCIERTO');
        $this->assertSame('VENCIDO', SobreEntregaEnlace::sole()->resultado);
        $this->assertNull(SobreEntregaEnlace::sole()->material_cifrado);
    }

    public function test_fallo_auditoria_emision_revierte_tambien_el_sobre(): void
    {
        DB::connection()->beforeExecuting(function (string $sql): void {
            if (str_starts_with($sql, 'insert into "auditorias"')) {
                throw new RuntimeException('Fallo sintético de auditoría');
            }
        });
        try {
            $this->emitir();
            $this->fail('No debe consolidarse una emisión sin auditoría.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('enlaces_asistencia_turno', 0);
            $this->assertDatabaseCount('sobres_entrega_enlace', 0);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public function test_clave_anterior_retirada_prematuramente_falla_sin_exponer_material(): void
    {
        $this->clave(random_bytes(32));
        $emitido = $this->emitir();
        $this->clave(random_bytes(32));
        try {
            $this->sobres->conMaterialParaDespacho($emitido->sobreId, fn () => $this->fail('No exponer sin clave válida.'));
            $this->fail('No debe recuperarse un cifrado sin su clave.');
        } catch (EntregaEnlaceNoDisponibleException $error) {
            $this->assertSame('material_invalido', $error->motivo);
            $this->assertNull($error->getPrevious());
            $this->assertStringNotContainsString($this->token($emitido->urlParaCorreo()), (string) $error);
        }
        $this->assertSame('DISPONIBLE', SobreEntregaEnlace::sole()->estado);
    }

    private function emitir()
    {
        return app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
    }

    private function token(string $url): string
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return $query['token'];
    }

    private function clave(string $actual, array $anteriores = []): void
    {
        config(['app.key' => 'base64:'.base64_encode($actual), 'app.previous_keys' => array_map(fn ($clave) => 'base64:'.base64_encode($clave), $anteriores)]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }
}
