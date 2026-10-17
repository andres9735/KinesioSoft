<?php

namespace Tests\Feature\Services\Turnos;

use App\Models\DocumentoClinico;
use App\Models\Paciente;
use App\Models\User;
use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumental;
use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumentalMariaDb;
use App\Services\Turnos\Documentos\ResultadoReconciliacionDocumental;
use App\Services\Turnos\ReconciliadorDocumentosClinicosService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CoordinadorBloqueoDocumentalEnMemoria;
use Tests\TestCase;

class ReconciliarDocumentosClinicosTest extends TestCase
{
    use DatabaseMigrations;

    private const A = 'originales/11111111-1111-4111-8111-111111111111';

    private const B = 'originales/22222222-2222-4222-8222-222222222222';

    private const C = 'originales/33333333-3333-4333-8333-333333333333';

    private FilesystemAdapter $disco;

    private CoordinadorBloqueoDocumentalEnMemoria $coordinador;

    public function runDatabaseMigrations(): void
    {
        // Commits reales en SQLite efímero, sin el down histórico ajeno a este módulo.
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$inMemoryConnections = [];
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-17 12:00:00', config('app.timezone')));
        $fake = Storage::fake('documentos_clinicos');
        $this->disco = Storage::build(array_replace(config('filesystems.disks.documentos_clinicos'), ['root' => $fake->path('')]));
        Storage::set('documentos_clinicos', $this->disco);
        $this->coordinador = new CoordinadorBloqueoDocumentalEnMemoria;
        $this->app->instance(CoordinadorBloqueoDocumental::class, $this->coordinador);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        $this->assertSame([], $this->coordinador->activos);
        parent::tearDown();
    }

    public function test_default_is_dry_run_and_structured_classification_matches_explicit_deletion(): void
    {
        $this->archivo(self::A);
        $this->archivo(self::B, 1);
        $this->archivo(self::C);
        $this->referenciar(self::C);
        $this->disco->put('originales/inesperado.pdf', 'ajeno');
        $simulacion = $this->reconciliar();
        $this->assertTrue($simulacion->exitoso());
        $this->assertTrue($simulacion->dryRun);
        $this->assertSame([
            'examinados' => 4, 'ignorados_nombre_invalido' => 1, 'inseguros' => 0,
            'desaparecidos' => 0, 'recientes' => 1, 'referenciados' => 1,
            'en_uso_lock' => 0, 'candidatos' => 1, 'eliminados' => 0,
        ], $simulacion->conteos);
        $this->assertCount(4, $this->disco->allFiles());
        $this->assertCount(2, $this->coordinador->liberaciones);

        $real = $this->reconciliar(true);
        $this->assertTrue($real->exitoso());
        $this->assertFalse($real->dryRun);
        $esperados = $simulacion->conteos;
        $esperados['eliminados'] = 1;
        $this->assertSame($esperados, $real->conteos);
        $this->disco->assertMissing(self::A);
        $this->disco->assertExists([self::B, self::C, 'originales/inesperado.pdf']);
        $this->assertDatabaseCount('documentos_clinicos', 1);
    }

    #[DataProvider('antiguedades')]
    public function test_grace_boundary_and_configurable_clock(int $desplazamiento, bool $candidato): void
    {
        config(['documentos_clinicos.reconciliacion.gracia_horas' => 2]);
        $this->archivo(self::A, $desplazamiento, 2);
        $resultado = $this->reconciliar(true);
        $this->assertTrue($resultado->exitoso());
        $this->assertSame($candidato ? 1 : 0, $resultado->conteos['eliminados']);
        $this->assertSame(! $candidato, $this->disco->exists(self::A));
        $this->assertSame($candidato ? 0 : 1, $resultado->conteos['recientes']);
    }

    public static function antiguedades(): array
    {
        return ['anterior' => [-1, true], 'frontera inclusiva' => [0, true], 'segundo reciente' => [1, false]];
    }

    public function test_does_not_recurse_or_operate_outside_expected_namespace(): void
    {
        foreach (['otro/11111111-1111-4111-8111-111111111111', 'originales/sub/11111111-1111-4111-8111-111111111111', 'externo', 'originales/11111111-1111-4111-8111-111111111111.pdf'] as $ruta) {
            $this->archivo($ruta);
        }
        $resultado = $this->reconciliar(true);
        $this->assertTrue($resultado->exitoso());
        $this->assertSame(2, $resultado->conteos['ignorados_nombre_invalido']);
        $this->assertSame(0, $resultado->conteos['eliminados']);
        $this->assertCount(4, $this->disco->allFiles());
        $this->assertSame([], $this->coordinador->adquisiciones);
    }

    #[DataProvider('directoriosInvalidos')]
    public function test_suspicious_namespace_is_rejected_instead_of_normalized(string $directorio): void
    {
        $this->archivo(self::A);
        config(['documentos_clinicos.directorio' => $directorio]);
        $resultado = $this->reconciliar(true);
        $this->assertFalse($resultado->exitoso());
        $this->assertSame(['configuracion'], $resultado->errores);
        $this->disco->assertExists(self::A);
        $this->assertSame([], $this->coordinador->adquisiciones);
    }

    public static function directoriosInvalidos(): array
    {
        return [['../originales'], ['/originales'], ['originales\\..\\otro'], ['originales/../otro']];
    }

    public function test_uuid_directory_is_not_a_regular_file_and_is_never_descended(): void
    {
        $this->archivo(self::A.'/contenido');
        $resultado = $this->reconciliar(true);
        $this->assertSame(1, $resultado->conteos['inseguros']);
        $this->disco->assertExists(self::A.'/contenido');
        $this->assertSame([], $this->coordinador->adquisiciones);
    }

    public function test_real_symlink_is_not_followed_when_platform_allows_creation(): void
    {
        $this->archivo('externo');
        $this->disco->makeDirectory('originales');
        $enlace = $this->disco->path(self::A);
        if (! @symlink($this->disco->path('externo'), $enlace)) {
            // Windows puede negar symlinks sin privilegio. No fingir que se creó uno.
            $this->assertFalse(is_link($enlace));
            $this->assertFileExists($this->disco->path('externo'));

            return;
        }
        try {
            $resultado = $this->reconciliar(true);
            $this->assertSame(1, $resultado->conteos['inseguros']);
            $this->assertSame(0, $resultado->conteos['eliminados']);
            $this->assertTrue(is_link($enlace));
            $this->disco->assertExists('externo');
        } finally {
            unlink($enlace);
        }
    }

    public function test_busy_lock_is_skipped_and_other_files_are_processed(): void
    {
        $this->archivo(self::A);
        $this->archivo(self::B);
        $escritor = $this->coordinador->adquirir(self::A);
        try {
            $resultado = $this->reconciliar(true);
            $this->assertTrue($resultado->exitoso());
            $this->assertSame(1, $resultado->conteos['en_uso_lock']);
            $this->assertSame(1, $resultado->conteos['eliminados']);
            $this->disco->assertExists(self::A);
            $this->disco->assertMissing(self::B);
        } finally {
            $escritor->liberar();
        }
    }

    public function test_ambiguous_coordination_error_aborts_without_deleting_further_files(): void
    {
        $this->archivo(self::A);
        $this->archivo(self::B);
        $this->coordinador->fallarAdquisicion = 1;
        $resultado = $this->reconciliar(true);
        $this->assertSame(['coordinacion'], $resultado->errores);
        $this->assertCount(1, $this->coordinador->adquisiciones);
        $this->assertCount(2, $this->disco->allFiles());
    }

    public function test_filesystem_age_is_rechecked_after_acquiring_lock(): void
    {
        $this->archivo(self::A);
        $this->alAdquirir(function ($ruta): void {
            touch($this->disco->path($ruta), CarbonImmutable::getTestNow()->getTimestamp());
        });
        $resultado = $this->reconciliar(true);
        $this->assertSame(1, $resultado->conteos['recientes']);
        $this->assertSame(0, $resultado->conteos['candidatos']);
        $this->disco->assertExists(self::A);
        $this->assertSame([self::A], $this->coordinador->liberaciones);
    }

    public function test_reference_created_during_acquisition_is_read_after_lock_and_preserved(): void
    {
        $this->archivo(self::A);
        $this->alAdquirir(function ($ruta): void {
            $this->assertArrayHasKey($ruta, $this->coordinador->activos);
            $this->referenciar($ruta);
        });
        $resultado = $this->reconciliar(true);
        $this->assertTrue($resultado->exitoso());
        $this->assertSame(1, $resultado->conteos['referenciados']);
        $this->assertSame(0, $resultado->conteos['candidatos']);
        $this->disco->assertExists(self::A);
    }

    public function test_disappearance_during_acquisition_is_a_safe_skip_with_release(): void
    {
        $this->archivo(self::A);
        $this->alAdquirir(fn ($ruta) => $this->disco->delete($ruta));
        $resultado = $this->reconciliar(true);
        $this->assertTrue($resultado->exitoso());
        $this->assertSame(1, $resultado->conteos['desaparecidos']);
        $this->assertSame(0, $resultado->conteos['eliminados']);
        $this->assertSame([self::A], $this->coordinador->liberaciones);
    }

    public function test_regular_file_replaced_by_directory_during_acquisition_is_preserved(): void
    {
        $this->archivo(self::A);
        $this->alAdquirir(function ($ruta): void {
            $this->disco->delete($ruta);
            $this->disco->makeDirectory($ruta);
        });
        $resultado = $this->reconciliar(true);
        $this->assertTrue($resultado->exitoso());
        $this->assertSame(1, $resultado->conteos['inseguros']);
        $this->assertSame(0, $resultado->conteos['candidatos']);
        $this->assertDirectoryExists($this->disco->path(self::A));
        $this->assertSame([self::A], $this->coordinador->liberaciones);
    }

    public function test_switched_database_session_is_not_used_after_acquisition(): void
    {
        $this->archivo(self::A);
        $conexion = DB::connection();
        $original = $conexion->getPdo();
        $this->alAdquirir(fn () => $conexion->setPdo(new PDO('sqlite::memory:')));
        try {
            $resultado = $this->reconciliar(true);
            $this->assertSame(['db'], $resultado->errores);
            $this->disco->assertExists(self::A);
            $this->assertSame([self::A], $this->coordinador->liberaciones);
        } finally {
            $conexion->setPdo($original);
        }
    }

    public function test_database_error_after_lock_aborts_conservatively_and_releases(): void
    {
        $this->archivo(self::A);
        $this->archivo(self::B);
        $this->alAdquirir(fn () => DB::statement('DROP TABLE documentos_clinicos'));
        $resultado = $this->reconciliar(true);
        $this->assertSame(['db'], $resultado->errores);
        $this->assertSame(0, $resultado->conteos['eliminados']);
        $this->assertCount(2, $this->disco->allFiles());
        $this->assertCount(1, $this->coordinador->liberaciones);
    }

    public function test_outer_transaction_cannot_supply_a_stale_reference_snapshot(): void
    {
        $this->archivo(self::A);
        DB::beginTransaction();
        try {
            $resultado = $this->reconciliar(true);
            $this->assertSame(['db'], $resultado->errores);
            $this->assertSame([], $this->coordinador->adquisiciones);
            $this->disco->assertExists(self::A);
        } finally {
            DB::rollBack();
        }
    }

    #[DataProvider('erroresDelete')]
    public function test_delete_failure_reports_nonzero_command_exit_and_releases(bool $excepcion): void
    {
        $this->archivo(self::A);
        $adapter = $this->adapter();
        $llamada = $adapter->shouldReceive('delete')->once()->with(self::A);
        $excepcion ? $llamada->andThrow(new RuntimeException('dato privado que no debe imprimirse')) : $llamada->andReturn(false);
        $this->assertSame(1, Artisan::call('documentos-clinicos:reconciliar-huerfanos', ['--delete' => true]));
        $salida = Artisan::output();
        $this->assertStringContainsString('eliminacion', $salida);
        $this->assertStringNotContainsString('dato privado', $salida);
        $this->disco->assertExists(self::A);
        $this->assertSame([self::A], $this->coordinador->liberaciones);
    }

    public static function erroresDelete(): array
    {
        return [[false], [true]];
    }

    public function test_release_failure_is_nonzero_and_dry_run_never_deletes(): void
    {
        $this->archivo(self::A);
        $this->archivo(self::B);
        $this->alAdquirir(function ($ruta): void {
            $this->coordinador->fallarLiberacion = $ruta;
        });
        $this->assertSame(1, Artisan::call('documentos-clinicos:reconciliar-huerfanos'));
        $this->assertStringContainsString('liberacion', Artisan::output());
        $this->assertCount(2, $this->disco->allFiles());
        $this->assertCount(1, $this->coordinador->adquisiciones);
    }

    public function test_command_defaults_to_safe_simulation_and_requires_explicit_delete(): void
    {
        $this->archivo(self::A);
        $this->assertSame(0, Artisan::call('documentos-clinicos:reconciliar-huerfanos'));
        $salida = Artisan::output();
        $this->assertStringContainsString('dry-run', $salida);
        $this->assertStringContainsString('candidatos', $salida);
        $this->assertStringNotContainsString(self::A, $salida);
        $this->disco->assertExists(self::A);
        $this->assertSame(0, Artisan::call('documentos-clinicos:reconciliar-huerfanos', ['--delete' => true]));
        $this->disco->assertMissing(self::A);
    }

    public function test_command_database_failure_is_nonzero_and_preserves_files(): void
    {
        $this->archivo(self::A);
        DB::statement('DROP TABLE documentos_clinicos');
        $this->assertSame(1, Artisan::call('documentos-clinicos:reconciliar-huerfanos', ['--delete' => true]));
        $this->assertStringContainsString('db', Artisan::output());
        $this->disco->assertExists(self::A);
        $this->assertSame([self::A], $this->coordinador->liberaciones);
    }

    #[DataProvider('resultadosNoBloqueantes')]
    public function test_real_coordinator_nonblocking_api_uses_zero_and_distinguishes_busy_from_error(mixed $valor, string $esperado): void
    {
        config(['documentos_clinicos.lock_timeout_segundos' => 5]);
        $conexion = $this->createMock(Connection::class);
        $conexion->method('getDriverName')->willReturn('mysql');
        $conexion->method('getDatabaseName')->willReturn('base_test');
        $pdo = $this->createMock(PDO::class);
        $conexion->expects($this->once())->method('getPdo')->willReturn($pdo);
        $consulta = $this->createMock(PDOStatement::class);
        $consulta->expects($this->once())->method('execute')->willReturnCallback(function ($parametros): bool {
            $this->assertSame(0, $parametros[1]);
            $this->assertStringStartsWith('ks-doc:', $parametros[0]);

            return true;
        });
        $consulta->method('fetchColumn')->willReturn($valor);
        $liberacion = $this->createMock(PDOStatement::class);
        $liberacion->method('execute')->willReturn(true);
        $liberacion->method('fetchColumn')->willReturn(1);
        $pdo->method('prepare')->willReturnCallback(fn ($sql) => $sql === 'SELECT GET_LOCK(?, ?)' ? $consulta : $liberacion);
        if ($esperado === 'error') {
            $this->expectException(RuntimeException::class);
        }
        $bloqueo = (new CoordinadorBloqueoDocumentalMariaDb($conexion))->intentarAdquirir(self::A);
        if ($esperado === 'ocupado') {
            $this->assertNull($bloqueo);
        } elseif ($esperado === 'adquirido') {
            $this->assertNotNull($bloqueo);
            $bloqueo->liberar();
        }
        $this->assertSame(5, config('documentos_clinicos.lock_timeout_segundos'));
    }

    public static function resultadosNoBloqueantes(): array
    {
        return [[0, 'ocupado'], ['0', 'ocupado'], [1, 'adquirido'], ['1', 'adquirido'], [null, 'error'], [false, 'error'], [true, 'error']];
    }

    private function reconciliar(bool $eliminar = false): ResultadoReconciliacionDocumental
    {
        return app(ReconciliadorDocumentosClinicosService::class)->reconciliar($eliminar);
    }

    private function archivo(string $ruta, int $desplazamiento = -1, int $horas = 24): void
    {
        $this->disco->put($ruta, 'fixture documental privado');
        $this->assertTrue(touch($this->disco->path($ruta), CarbonImmutable::getTestNow()->subHours($horas)->getTimestamp() + $desplazamiento));
    }

    private function referenciar(string $ruta): void
    {
        $user = User::factory()->create();
        $paciente = Paciente::create(['user_id' => $user->id, 'activo' => true]);
        DocumentoClinico::factory()->for($paciente)->for($user, 'cargadoPor')->create(['ruta_storage' => $ruta]);
    }

    private function alAdquirir(Closure $accion): void
    {
        $coordinador = Mockery::mock(CoordinadorBloqueoDocumental::class);
        $coordinador->shouldReceive('intentarAdquirir')->andReturnUsing(function ($ruta) use ($accion) {
            $bloqueo = $this->coordinador->intentarAdquirir($ruta);
            $accion($ruta);

            return $bloqueo;
        });
        $this->app->instance(CoordinadorBloqueoDocumental::class, $coordinador);
    }

    private function adapter(): MockInterface
    {
        $adapter = Mockery::mock(FilesystemAdapter::class);
        $adapter->shouldReceive('getConfig')->andReturn($this->disco->getConfig());
        $adapter->shouldReceive('path')->with('')->andReturn($this->disco->path(''));
        Storage::set('documentos_clinicos', $adapter);

        return $adapter;
    }
}
