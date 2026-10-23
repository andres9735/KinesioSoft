<?php

namespace Tests\Feature\Services\Turnos;

use App\Exceptions\Turnos\AlmacenamientoDocumentalException;
use App\Services\Turnos\AlmacenamientoDocumentosClinicosService;
use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumental;
use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumentalMariaDb;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CoordinadorBloqueoDocumentalEnMemoria;
use Tests\TestCase;

class AlmacenamientoDocumentosClinicosTest extends TestCase
{
    private const UUID_A = '11111111-1111-4111-8111-111111111111';

    private const UUID_B = '22222222-2222-4222-8222-222222222222';

    private CoordinadorBloqueoDocumentalEnMemoria $coordinador;

    private FilesystemAdapter $disco;

    /** @var list<string> */
    private array $temporales = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->coordinador = new CoordinadorBloqueoDocumentalEnMemoria;
        $this->app->instance(CoordinadorBloqueoDocumental::class, $this->coordinador);
        $fake = Storage::fake('documentos_clinicos');
        // fake() agrega callbacks de URL y no conserva toda la configuración del disco.
        // Usar el driver real con la configuración privada y sólo el root aislado de PHPUnit.
        $this->disco = Storage::build(array_replace(config('filesystems.disks.documentos_clinicos'), [
            'root' => $fake->path(''),
        ]));
        Storage::set('documentos_clinicos', $this->disco);
    }

    protected function tearDown(): void
    {
        try {
            Str::createUuidsNormally();
            foreach ($this->temporales as $ruta) {
                if (is_file($ruta)) {
                    unlink($ruta);
                }
            }
            $this->assertSame([], $this->coordinador->activos, 'No deben filtrarse locks del sustituto entre tests.');
        } finally {
            parent::tearDown();
        }
    }

    public function test_dedicated_disk_is_private_not_served_and_has_no_public_url_or_link(): void
    {
        $config = config('filesystems.disks.documentos_clinicos');
        $this->assertSame('documentos_clinicos', config('documentos_clinicos.disk'));
        $this->assertSame('local', $config['driver']);
        $this->assertSame(storage_path('app/private/documentos_clinicos'), $config['root']);
        $this->assertSame('private', $config['visibility']);
        $this->assertSame('private', $config['directory_visibility']);
        $this->assertFalse($config['serve']);
        $this->assertTrue($config['throw']);
        $this->assertArrayNotHasKey('url', $config);
        $this->assertNotContains($config['root'], config('filesystems.links'));
        $this->assertFalse($this->disco->providesTemporaryUrls());
    }

    public function test_stores_real_content_and_metadata_without_database_writes_until_explicit_preservation(): void
    {
        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas): void {
            $consultas[] = $consulta->sql;
        });
        $adapter = $this->adapter();
        $adapter->shouldReceive('put')->twice()->andReturnUsing(function ($ruta, $stream, $opciones) {
            $this->assertSame(['visibility' => 'private'], $opciones);

            return $this->disco->put($ruta, $stream, $opciones);
        });
        Storage::set('documentos_clinicos', $adapter);
        $archivos = [2 => $this->pdf('informe paciente ../../secreto.pdf'), 8 => $this->pdf('otro.jpg')];
        $adjuntos = [
            2 => ['archivo' => $archivos[2], 'descripcion' => 'Adjunto sintético A ../secreto'],
            8 => ['archivo' => $archivos[8], 'descripcion' => 'Adjunto sintético B 日本語'],
        ];
        Str::createUuidsUsingSequence([self::UUID_A, self::UUID_B]);
        $operacion = $this->service()->almacenar($adjuntos);
        $documentos = $operacion->documentos();

        $this->assertSame([2, 8], array_keys($documentos));
        foreach ($documentos as $indice => $documento) {
            $ruta = $documento['ruta_storage'];
            $this->assertMatchesRegularExpression('/\Aoriginales\/[a-f0-9-]{36}\z/', $ruta);
            $this->assertLessThanOrEqual(255, strlen($ruta));
            $this->assertStringNotContainsString('secreto', $ruta);
            $this->assertStringNotContainsString('..', $ruta);
            $this->assertSame($archivos[$indice]->getClientOriginalName(), $documento['nombre_original']);
            $this->assertSame($adjuntos[$indice]['descripcion'], $documento['descripcion']);
            $this->assertSame('application/pdf', $documento['mime_type']);
            $this->assertSame($archivos[$indice]->getSize(), $documento['tamanio_bytes']);
            $this->assertSame(file_get_contents($archivos[$indice]->getPathname()), $this->disco->get($ruta));
            $this->assertSame('private', $this->disco->getConfig()['visibility']);
            $this->assertArrayHasKey($ruta, $this->coordinador->activos);
        }
        $this->assertCount(2, array_unique(array_column($documentos, 'ruta_storage')));
        $this->assertSame(['originales/'.self::UUID_A, 'originales/'.self::UUID_B], $this->coordinador->adquisiciones);
        $this->assertSame([], $consultas);

        $operacion->conservarYLiberar();
        $this->assertCount(2, $this->disco->allFiles());
        $this->assertSame([], $this->coordinador->activos);
    }

    public function test_empty_batch_has_no_files_or_locks(): void
    {
        $operacion = $this->service()->almacenar([]);
        $this->assertSame([], $operacion->documentos());
        $this->assertSame([], $this->coordinador->adquisiciones);
        $this->assertSame([], $this->disco->allFiles());
        $operacion->conservarYLiberar();
    }

    public function test_reuses_policy_before_resolving_storage_or_acquiring_locks(): void
    {
        config(['documentos_clinicos.max_archivos' => 0]);
        Storage::shouldReceive('disk')->never();

        try {
            $this->service()->almacenar([$this->pdf()]);
            $this->fail('Debe fallar la política central.');
        } catch (AlmacenamientoDocumentalException $error) {
            $this->assertSame(['coleccion' => ['cantidad_excedida'], 'archivos' => []], $error->violacionesPolitica);
        }
        $this->assertSame([], $this->coordinador->adquisiciones);
        $this->assertSame([], $this->disco->allFiles());
    }

    public function test_collision_preserves_existing_file_releases_candidate_and_uses_next_uuid(): void
    {
        $previa = 'originales/'.self::UUID_A;
        $this->disco->put($previa, 'previo');
        Str::createUuidsUsingSequence([self::UUID_A, self::UUID_B]);

        $operacion = $this->service()->almacenar([$this->pdf()]);
        $this->assertSame('originales/'.self::UUID_B, $operacion->documentos()[0]['ruta_storage']);
        $this->assertSame('previo', $this->disco->get($previa));
        $this->assertSame([$previa], $this->coordinador->liberaciones);
        $operacion->compensarYLiberar();
        $this->assertSame([$previa], $this->disco->allFiles());
    }

    public function test_metadata_becoming_unreadable_after_policy_does_not_produce_a_successful_batch(): void
    {
        $archivo = $this->createMock(UploadedFile::class);
        $archivo->method('isValid')->willReturn(true);
        $archivo->method('getMimeType')->willReturn('application/pdf');
        $archivo->method('getSize')->willReturnOnConsecutiveCalls(100, false);

        try {
            $this->service()->almacenar([$archivo]);
            $this->fail('La metadata ilegible no puede producir un resultado exitoso.');
        } catch (AlmacenamientoDocumentalException $error) {
            $this->assertStringContainsString('metadata', $error->getPrevious()->getMessage());
        }
        $this->assertSame([], $this->coordinador->adquisiciones);
        $this->assertSame([], $this->disco->allFiles());
    }

    public function test_collision_attempts_are_bounded_and_all_candidate_locks_are_released(): void
    {
        $ruta = 'originales/'.self::UUID_A;
        $this->disco->put($ruta, 'previo');
        Str::createUuidsUsingSequence([self::UUID_A, self::UUID_A, self::UUID_A]);

        try {
            $this->service()->almacenar([$this->pdf()]);
            $this->fail('Debe agotarse la generación acotada.');
        } catch (AlmacenamientoDocumentalException $error) {
            $this->assertStringContainsString('agotaron', $error->getPrevious()->getMessage());
        }
        $this->assertCount(3, $this->coordinador->adquisiciones);
        $this->assertCount(3, $this->coordinador->liberaciones);
        $this->assertSame('previo', $this->disco->get($ruta));
    }

    #[DataProvider('numeroDeArchivo')]
    public function test_failure_to_acquire_prevents_dependent_write_and_compensates_prior_files(int $numero): void
    {
        $this->coordinador->fallarAdquisicion = $numero;
        $this->disco->put('previo', 'ajeno');

        try {
            $this->service()->almacenar([$this->pdf(), $this->pdf()]);
            $this->fail('Debe fallar la adquisición.');
        } catch (AlmacenamientoDocumentalException $error) {
            $this->assertStringContainsString('no adquirido', $error->getPrevious()->getMessage());
        }
        $this->assertCount($numero, $this->coordinador->adquisiciones);
        $this->assertCount($numero - 1, $this->coordinador->liberaciones);
        $this->assertSame(['previo'], $this->disco->allFiles());
    }

    public static function numeroDeArchivo(): array
    {
        return ['primero' => [1], 'segundo' => [2]];
    }

    #[DataProvider('fallasEscritura')]
    public function test_write_failure_compensates_complete_and_partial_files_preserving_original_cause(int $numero, bool $parcial, bool $devuelveFalse): void
    {
        $this->disco->put('previo', 'ajeno');
        $original = new RuntimeException('Falla original de escritura.');
        $escrituras = 0;
        $adapter = $this->adapter();
        $adapter->shouldReceive('put')->andReturnUsing(function ($ruta, $stream, $opciones) use (&$escrituras, $numero, $parcial, $devuelveFalse, $original) {
            $this->assertArrayHasKey($ruta, $this->coordinador->activos);
            if (++$escrituras === $numero) {
                if ($parcial) {
                    $this->disco->put($ruta, 'bytes parciales');
                }
                if ($devuelveFalse) {
                    return false;
                }
                throw $original;
            }

            return $this->disco->put($ruta, $stream, $opciones);
        });
        Storage::set('documentos_clinicos', $adapter);

        try {
            $this->service()->almacenar([
                2 => ['archivo' => $this->pdf(), 'descripcion' => 'Adjunto sintético A.'],
                7 => ['archivo' => $this->pdf(), 'descripcion' => 'Adjunto sintético B.'],
            ]);
            $this->fail('Debe fallar la escritura.');
        } catch (AlmacenamientoDocumentalException $error) {
            if ($devuelveFalse) {
                $this->assertStringContainsString('escritura', $error->getPrevious()->getMessage());
            } else {
                $this->assertSame($original, $error->getPrevious());
            }
            $this->assertSame([], $error->rutasNoEliminadas());
        }
        $this->assertSame(['previo'], $this->disco->allFiles());
        $this->assertCount($numero, $this->coordinador->liberaciones);
    }

    public static function fallasEscritura(): array
    {
        return [
            'primer archivo' => [1, false, false],
            'primer parcial' => [1, true, false],
            'segundo archivo' => [2, false, false],
            'segundo parcial' => [2, true, false],
            'adapter devuelve false' => [2, true, true],
        ];
    }

    public function test_cleanup_failure_reports_route_and_release_failure_without_hiding_write_error(): void
    {
        Str::createUuidsUsingSequence([self::UUID_A, self::UUID_B]);
        $ruta = 'originales/'.self::UUID_A;
        $this->coordinador->fallarLiberacion = $ruta;
        $original = new RuntimeException('Escritura fallida.');
        $adapter = Mockery::mock(FilesystemAdapter::class);
        $adapter->shouldReceive('exists')->andReturn(false);
        $adapter->shouldReceive('put')->andReturnUsing(function ($ruta) use ($original) {
            $this->disco->put($ruta, 'parcial');
            throw $original;
        });
        $adapter->shouldReceive('delete')->with($ruta)->andReturn(false);
        Storage::set('documentos_clinicos', $adapter);
        $this->disco->put('previo', 'ajeno');

        try {
            $this->service()->almacenar([$this->pdf()]);
            $this->fail('Debe informar las tres fallas.');
        } catch (AlmacenamientoDocumentalException $error) {
            $this->assertSame($original, $error->getPrevious());
            $this->assertSame([$ruta], $error->rutasNoEliminadas());
            $this->assertSame([$ruta], array_keys($error->erroresLiberacion));
        }
        $this->assertSame([$ruta], $this->coordinador->liberaciones);
        $this->assertSame('ajeno', $this->disco->get('previo'));
        $this->disco->assertExists($ruta);
    }

    public function test_explicit_compensation_preserves_foreign_paths_even_if_returned_metadata_is_changed(): void
    {
        $ajenas = ['otro/documento', 'originales/previo', 'secreto'];
        foreach ($ajenas as $ruta) {
            $this->disco->put($ruta, 'ajeno');
        }
        $archivo = $this->pdf();
        $operacion = $this->service()->almacenar([$archivo, $this->pdf()]);
        $metadata = $operacion->documentos();
        $metadata[0]['ruta_storage'] = $archivo->getPathname();
        $metadata[1]['ruta_storage'] = '../secreto';
        $metadata[] = ['ruta_storage' => 'originales/previo'];
        $operacion->compensarYLiberar();

        $this->assertFileExists($archivo->getPathname());
        $this->assertEqualsCanonicalizing($ajenas, $this->disco->allFiles());
        $this->assertCount(2, $this->coordinador->liberaciones);
    }

    public function test_explicit_compensation_attempts_every_delete_and_release_even_if_one_delete_throws(): void
    {
        $adapter = Mockery::mock(FilesystemAdapter::class);
        $adapter->shouldReceive('exists')->andReturnUsing(fn ($ruta) => $this->disco->exists($ruta));
        $adapter->shouldReceive('put')->andReturnUsing(fn (...$args) => $this->disco->put(...$args));
        $eliminaciones = 0;
        $adapter->shouldReceive('delete')->andReturnUsing(function ($ruta) use (&$eliminaciones) {
            if (++$eliminaciones === 1) {
                throw new RuntimeException('Delete fallido.');
            }

            return $this->disco->delete($ruta);
        });
        Storage::set('documentos_clinicos', $adapter);
        $operacion = $this->service()->almacenar([$this->pdf(), $this->pdf()]);

        try {
            $operacion->compensarYLiberar();
            $this->fail('Debe informar el delete fallido.');
        } catch (AlmacenamientoDocumentalException $error) {
            $this->assertSame([$operacion->documentos()[0]['ruta_storage']], $error->rutasNoEliminadas());
            $this->assertNull($error->getPrevious());
        }
        $this->assertSame(2, $eliminaciones);
        $this->assertCount(2, $this->coordinador->liberaciones);
    }

    public function test_preservation_reports_release_failure_without_deleting_files(): void
    {
        $operacion = $this->service()->almacenar([$this->pdf()]);
        $ruta = $operacion->documentos()[0]['ruta_storage'];
        $this->coordinador->fallarLiberacion = $ruta;

        try {
            $operacion->conservarYLiberar();
            $this->fail('Debe reportar la liberación incierta.');
        } catch (AlmacenamientoDocumentalException $error) {
            $this->assertSame([$ruta], array_keys($error->erroresLiberacion));
            $this->assertSame([], $error->rutasNoEliminadas());
        }
        $this->disco->assertExists($ruta);
    }

    public function test_test_coordinator_rejects_second_acquisition_until_explicit_release(): void
    {
        $bloqueo = $this->coordinador->adquirir('originales/test');
        try {
            $this->coordinador->adquirir('originales/test');
            $this->fail('Debe rechazar un lock retenido.');
        } catch (RuntimeException) {
            $this->assertArrayHasKey('originales/test', $this->coordinador->activos);
        }
        $bloqueo->liberar();
        $this->coordinador->adquirir('originales/test')->liberar();
    }

    #[DataProvider('finalizaciones')]
    public function test_second_finalization_cannot_delete_files_after_preservation(bool $compensarPrimero, bool $compensarDespues): void
    {
        $operacion = $this->service()->almacenar([$this->pdf()]);
        $compensarPrimero ? $operacion->compensarYLiberar() : $operacion->conservarYLiberar();

        try {
            $compensarDespues ? $operacion->compensarYLiberar() : $operacion->conservarYLiberar();
            $this->fail('La segunda finalización debe rechazarse.');
        } catch (LogicException) {
            $this->assertCount($compensarPrimero ? 0 : 1, $this->disco->allFiles());
        }
        $this->assertCount(1, $this->coordinador->liberaciones);
    }

    public static function finalizaciones(): array
    {
        return [[false, true], [false, false], [true, true], [true, false]];
    }

    public function test_external_exception_does_not_automatically_compensate_a_delivered_batch(): void
    {
        $operacion = $this->service()->almacenar([$this->pdf()]);
        try {
            throw new RuntimeException('Resultado externo incierto.');
        } catch (RuntimeException) {
            $this->assertCount(1, $this->disco->allFiles());
            $this->assertCount(1, $this->coordinador->activos);
            $operacion->conservarYLiberar();
        }
        $this->assertCount(1, $this->disco->allFiles());
    }

    #[DataProvider('resultadosLock')]
    public function test_mariadb_capability_strictly_interprets_acquisition_and_releases_on_the_captured_session(mixed $resultado, bool $adquirido): void
    {
        $pdo = $this->createMock(PDO::class);
        $conexion = $this->createMock(Connection::class);
        $conexion->method('getDriverName')->willReturn('mysql');
        $conexion->method('getDatabaseName')->willReturn('base_exclusiva_test');
        $conexion->expects($this->once())->method('getPdo')->willReturn($pdo);
        $adquirir = $this->createMock(PDOStatement::class);
        $liberar = $this->createMock(PDOStatement::class);
        $nombre = null;
        $adquirir->expects($this->once())->method('execute')->willReturnCallback(function ($parametros) use (&$nombre) {
            [$nombre, $timeout] = $parametros;
            $this->assertStringStartsWith('ks-doc:', $nombre);
            $this->assertLessThanOrEqual(64, strlen($nombre));
            $this->assertStringNotContainsString('originales', $nombre);
            $this->assertSame(config('documentos_clinicos.lock_timeout_segundos'), $timeout);

            return true;
        });
        $adquirir->method('fetchColumn')->willReturn($resultado);
        $liberar->expects($adquirido ? $this->once() : $this->never())->method('execute')->willReturnCallback(function ($parametros) use (&$nombre) {
            $this->assertSame([$nombre], $parametros);

            return true;
        });
        $liberar->method('fetchColumn')->willReturn('1');
        $pdo->expects($this->exactly($adquirido ? 2 : 1))->method('prepare')->willReturnCallback(function ($sql) use ($adquirir, $liberar) {
            $this->assertContains($sql, ['SELECT GET_LOCK(?, ?)', 'SELECT RELEASE_LOCK(?)']);

            return $sql === 'SELECT GET_LOCK(?, ?)' ? $adquirir : $liberar;
        });
        $coordinador = new CoordinadorBloqueoDocumentalMariaDb($conexion);

        if (! $adquirido) {
            $this->expectException(RuntimeException::class);
        }
        $coordinador->adquirir('originales/'.self::UUID_A)->liberar();
    }

    public static function resultadosLock(): array
    {
        return ['int 1' => [1, true], 'string 1' => ['1', true], 'timeout' => [0, false], 'NULL' => [null, false], 'ambiguo' => [true, false]];
    }

    public function test_mariadb_release_failure_is_reported_without_any_real_connection(): void
    {
        $conexion = $this->createMock(Connection::class);
        $conexion->method('getDriverName')->willReturn('mariadb');
        $conexion->method('getDatabaseName')->willReturn('test');
        $pdo = $this->createMock(PDO::class);
        $conexion->expects($this->once())->method('getPdo')->willReturn($pdo);
        $consulta = $this->createMock(PDOStatement::class);
        $consulta->method('execute')->willReturn(true);
        $consulta->method('fetchColumn')->willReturnOnConsecutiveCalls(1, 0);
        $pdo->method('prepare')->willReturn($consulta);

        $bloqueo = (new CoordinadorBloqueoDocumentalMariaDb($conexion))->adquirir('originales/test');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('liberación');
        $bloqueo->liberar();
    }

    public function test_sqlite_never_attempts_mariadb_lock_sql_and_requires_the_explicit_test_substitute(): void
    {
        $conexion = $this->createMock(Connection::class);
        $conexion->method('getDriverName')->willReturn('sqlite');
        $conexion->expects($this->never())->method('getPdo');
        $this->expectException(RuntimeException::class);
        (new CoordinadorBloqueoDocumentalMariaDb($conexion))->adquirir('originales/test');
    }

    private function service(): AlmacenamientoDocumentosClinicosService
    {
        return app(AlmacenamientoDocumentosClinicosService::class);
    }

    private function adapter(): MockInterface
    {
        $adapter = Mockery::mock(FilesystemAdapter::class);
        $adapter->shouldReceive('exists')->andReturnUsing(fn ($ruta) => $this->disco->exists($ruta));
        $adapter->shouldReceive('delete')->andReturnUsing(fn ($ruta) => $this->disco->delete($ruta));

        return $adapter;
    }

    private function pdf(string $nombre = 'informe.pdf'): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'ks-storage-');
        $this->temporales[] = $ruta;
        file_put_contents($ruta, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");

        return new UploadedFile($ruta, $nombre, 'application/octet-stream', UPLOAD_ERR_OK, true);
    }
}
