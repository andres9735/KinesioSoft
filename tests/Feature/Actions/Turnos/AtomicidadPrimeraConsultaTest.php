<?php

namespace Tests\Feature\Actions\Turnos;

use App\Actions\Turnos\ConfirmarPrimeraConsultaAction;
use App\Enums\DiaSemanaEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\AlmacenamientoDocumentalException;
use App\Exceptions\Turnos\ClaveIdempotenciaIncompatibleException;
use App\Exceptions\Turnos\PrimeraConsultaActivaException;
use App\Exceptions\Turnos\ProfesionalNoElegibleException;
use App\Exceptions\Turnos\ResultadoConfirmacionInciertoException;
use App\Exceptions\Turnos\SlotNoDisponibleException;
use App\Models\Auditoria;
use App\Models\ColegioEmisor;
use App\Models\ConfiguracionAgenda;
use App\Models\DocumentoClinico;
use App\Models\DocumentoClinicoOrigenTurno;
use App\Models\EspecialidadKinesiologia;
use App\Models\FranjaHorariaAgenda;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\Paciente;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumental;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\Support\CoordinadorBloqueoDocumentalEnMemoria;
use Tests\TestCase;

class AtomicidadPrimeraConsultaTest extends TestCase
{
    // Sin transacción envolvente: estos tests necesitan commits físicos en SQLite.
    use DatabaseMigrations;

    private User $actor;

    private Paciente $paciente;

    private Kinesiologa $kinesiologa;

    private string $uuid;

    private FilesystemAdapter $disco;

    private CoordinadorBloqueoDocumentalEnMemoria $coordinador;

    private PDO $pdo;

    private array $temporales = [];

    public function runDatabaseMigrations(): void
    {
        // SQLite efímero por test, con commits reales. No revertir migraciones históricas
        // cuyo down requiere historias clínicas ajenas al fixture documental.
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$inMemoryConnections = [];
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = DB::connection()->getPdo();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 09:00:00', config('app.timezone')));
        $this->actor = User::factory()->create();
        $this->paciente = Paciente::create(['user_id' => $this->actor->id, 'activo' => true]);
        $this->kinesiologa = $this->profesional();
        $configuracion = ConfiguracionAgenda::factory()->for($this->kinesiologa)->create([
            'vigencia_desde' => '2026-10-01', 'duracion_turno_minutos' => 30,
        ]);
        FranjaHorariaAgenda::factory()->for($configuracion, 'configuracionAgenda')->create([
            'dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00',
        ]);
        $this->uuid = (string) Str::uuid();
        $this->coordinador = new CoordinadorBloqueoDocumentalEnMemoria;
        $this->app->instance(CoordinadorBloqueoDocumental::class, $this->coordinador);
        $fake = Storage::fake('documentos_clinicos');
        $this->disco = Storage::build(array_replace(config('filesystems.disks.documentos_clinicos'), ['root' => $fake->path('')]));
        Storage::set('documentos_clinicos', $this->disco);
    }

    protected function tearDown(): void
    {
        foreach ([Turno::class, DocumentoClinico::class, DocumentoClinicoOrigenTurno::class, Auditoria::class] as $modelo) {
            Event::forget('eloquent.creating: '.$modelo);
        }
        foreach ([TransactionCommitted::class, TransactionCommitting::class, TransactionRolledBack::class] as $evento) {
            Event::forget($evento);
        }
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        DB::connection()->setPdo($this->pdo);
        CarbonImmutable::setTestNow();
        foreach ($this->temporales as $ruta) {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
        $this->assertSame([], $this->coordinador->activos);
        parent::tearDown();
    }

    #[DataProvider('cantidades')]
    public function test_consolidates_exact_batch_metadata_associations_audits_and_releases_only_after_commit(int $cantidad): void
    {
        $archivos = $this->archivos($cantidad);
        $commitObservado = false;
        Event::listen(TransactionCommitted::class, function () use ($cantidad, &$commitObservado): void {
            $commitObservado = true;
            $this->assertFalse($this->pdo->inTransaction());
            $this->assertCount($cantidad, $this->coordinador->activos);
            $this->assertDatabaseCount('documentos_clinicos_origen_turno', $cantidad);
        });
        $resultado = $this->confirmar($archivos);
        $this->assertTrue($commitObservado);
        $this->assertFalse($resultado->esReplay);
        $this->conteos(1, $cantidad, 1 + $cantidad);
        $this->assertCount($cantidad, $this->disco->allFiles());
        $this->assertSame([], $this->coordinador->activos);
        $this->assertCount($cantidad, $this->coordinador->liberaciones);
        $documentos = DocumentoClinico::query()->orderBy('id')->get();
        foreach ($documentos as $indice => $documento) {
            $archivo = array_values($archivos)[$indice];
            $this->assertSame($archivo->getClientOriginalName(), $documento->nombre_original);
            $this->assertSame('application/pdf', $documento->mime_type);
            $this->assertSame($archivo->getSize(), $documento->tamanio_bytes);
            $this->assertSame(file_get_contents($archivo->getPathname()), $this->disco->get($documento->ruta_storage));
            $this->assertSame($this->paciente->id, $documento->paciente_id);
            $this->assertSame($this->actor->id, $documento->cargado_por_user_id);
            $this->assertSame(DocumentoClinico::ORIGEN_SOLICITUD_TURNO, $documento->origen);
            $this->assertSame(DocumentoClinico::ESTADO_VALIDACION_PENDIENTE, $documento->estado_validacion);
            $this->assertSame(DocumentoClinico::TIPO_SIN_CLASIFICAR, $documento->tipo_documento_clinico);
            $this->assertSame($resultado->turno->id, $documento->origenTurno->turno_id);
            $this->assertEqualsCanonicalizing(['documento_clinico_id', 'turno_id'], array_keys($documento->origenTurno->getAttributes()));
        }
        $this->assertSame($cantidad, $documentos->pluck('ruta_storage')->unique()->count());
        $this->assertSame(1, Auditoria::where('evento', 'turno_primera_consulta_creado')->count());
        $cargas = Auditoria::where('evento', 'documento_clinico_cargado')->get();
        $this->assertCount($cantidad, $cargas);
        foreach ($cargas as $carga) {
            $this->assertSame($this->actor->id, $carga->actor_user_id);
            $this->assertSame((new DocumentoClinico)->getMorphClass(), $carga->auditable_type);
            $this->assertSame($carga->auditable_id, $carga->valores_nuevos['documento_clinico_id']);
            $this->assertSame($resultado->turno->id, $carga->valores_nuevos['turno_id']);
            $this->assertSame(['documento_clinico_id', 'turno_id', 'paciente_id', 'origen', 'estado_validacion', 'tipo_documento_clinico', 'mime_type', 'tamanio_bytes'], array_keys($carga->valores_nuevos));
            foreach (['nombre_original', 'ruta_storage', 'motivo_solicitud', 'contenido', 'bytes', 'clave_idempotencia'] as $prohibido) {
                $this->assertArrayNotHasKey($prohibido, $carga->valores_nuevos);
            }
            $this->assertStringNotContainsString('Diagnóstico sensible', json_encode($carga->toArray()));
        }
    }

    public static function cantidades(): array
    {
        return [[0], [1], [5]];
    }

    #[DataProvider('reintentos')]
    public function test_lost_response_and_replay_never_reprocess_current_files(int $cantidad, string $payload): void
    {
        $originales = $this->archivos($cantidad);
        $id = $this->confirmar($originales)->turno->id;
        $paths = $this->disco->allFiles();
        $adquisiciones = $this->coordinador->adquisiciones;
        $documentos = DocumentoClinico::orderBy('id')->get()->toArray();
        $actuales = match ($payload) {
            'vacio' => [],
            'iguales' => $originales,
            'distintos' => $this->archivos(1),
            'seis' => $this->archivos(6),
            'invalido' => [new \stdClass],
            'fallido' => [new UploadedFile($this->pdf()->getPathname(), 'fallido.pdf', null, UPLOAD_ERR_PARTIAL, true)],
        };
        $resultado = app(ConfirmarPrimeraConsultaAction::class)->ejecutar($this->actor, $this->uuid, adjuntos: $actuales);
        $this->assertTrue($resultado->esReplay);
        $this->assertSame($id, $resultado->turno->id);
        $this->conteos(1, $cantidad, 1 + $cantidad);
        $this->assertSame($paths, $this->disco->allFiles());
        $this->assertSame($adquisiciones, $this->coordinador->adquisiciones);
        $this->assertSame($documentos, DocumentoClinico::orderBy('id')->get()->toArray());
    }

    public static function reintentos(): array
    {
        return [[1, 'vacio'], [1, 'iguales'], [5, 'iguales'], [5, 'distintos'], [1, 'seis'], [5, 'invalido'], [1, 'fallido']];
    }

    #[DataProvider('conflictos')]
    public function test_functional_conflicts_precede_any_file_write(string $conflicto, string $excepcion): void
    {
        if ($conflicto === 'd01') {
            $this->turnoGanador($this->paciente, (string) Str::uuid());
        } elseif ($conflicto === 'profesional') {
            $this->kinesiologa->user->update(['activo' => false]);
        } else {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 10:00:00', config('app.timezone')));
        }
        try {
            $this->confirmar($this->archivos(1));
            $this->fail('Debe rechazar antes de escribir archivos.');
        } catch (\Throwable $error) {
            $this->assertInstanceOf($excepcion, $error);
        }
        $this->assertSame([], $this->disco->allFiles());
        $this->assertSame([], $this->coordinador->adquisiciones);
    }

    public static function conflictos(): array
    {
        return [['d01', PrimeraConsultaActivaException::class], ['profesional', ProfesionalNoElegibleException::class], ['slot', SlotNoDisponibleException::class]];
    }

    public function test_policy_is_reused_for_new_invalid_batch_before_acquiring_documental_locks(): void
    {
        try {
            $this->confirmar($this->archivos(6));
            $this->fail('Debe rechazar seis adjuntos.');
        } catch (AlmacenamientoDocumentalException $error) {
            $this->assertSame(['cantidad_excedida'], $error->violacionesPolitica['coleccion']);
        }
        $this->conteos(0, 0, 0);
        $this->assertSame([], $this->coordinador->adquisiciones);
    }

    #[DataProvider('puntosDeFalla')]
    public function test_sql_failure_rolls_back_entire_aggregate_compensates_all_files_and_preserves_foreign_file(string $modelo, int $numero, int $cantidad): void
    {
        $this->disco->put('ajeno', 'previo');
        $original = new RuntimeException('Falla SQL del agregado');
        $contador = 0;
        $modelo::creating(function () use (&$contador, $numero, $original, $cantidad): void {
            $this->assertSame(1, DB::transactionLevel());
            $this->assertCount($cantidad, $this->coordinador->activos);
            if (++$contador === $numero) {
                throw $original;
            }
        });
        try {
            $this->confirmar($this->archivos($cantidad));
            $this->fail('Debe propagar la falla.');
        } catch (RuntimeException $error) {
            $this->assertSame($original, $error);
        }
        $this->conteos(0, 0, 0);
        $this->assertSame(['ajeno'], $this->disco->allFiles());
        $this->assertCount($cantidad, $this->coordinador->liberaciones);
    }

    public static function puntosDeFalla(): array
    {
        return [
            'documento' => [DocumentoClinico::class, 1, 1],
            'asociacion' => [DocumentoClinicoOrigenTurno::class, 1, 1],
            'auditoria turno' => [Auditoria::class, 1, 1],
            'auditoria documento' => [Auditoria::class, 2, 1],
            'tercer documento' => [DocumentoClinico::class, 3, 5],
            'tercera asociacion' => [DocumentoClinicoOrigenTurno::class, 3, 5],
            'tercera auditoria documental' => [Auditoria::class, 4, 5],
        ];
    }

    public function test_storage_failure_cleans_its_partial_batch_without_sql_aggregate(): void
    {
        $original = new RuntimeException('Escritura interrumpida');
        $adapter = $this->adapter();
        $numero = 0;
        $adapter->shouldReceive('put')->andReturnUsing(function ($ruta, $stream, $opciones) use (&$numero, $original) {
            $this->disco->put($ruta, $stream, $opciones);
            if (++$numero === 2) {
                throw $original;
            }

            return true;
        });
        $adapter->shouldReceive('delete')->andReturnUsing(fn ($ruta) => $this->disco->delete($ruta));
        Storage::set('documentos_clinicos', $adapter);
        try {
            $this->confirmar($this->archivos(3));
            $this->fail('Debe fallar storage.');
        } catch (AlmacenamientoDocumentalException $error) {
            $this->assertSame($original, $error->getPrevious());
        }
        $this->conteos(0, 0, 0);
        $this->assertSame([], $this->disco->allFiles());
        $this->assertCount(2, $this->coordinador->liberaciones);
    }

    public function test_cleanup_failure_reports_original_and_attempts_every_release(): void
    {
        $original = new RuntimeException('Falla principal');
        DocumentoClinico::creating(fn () => throw $original);
        $adapter = $this->adapter();
        $adapter->shouldReceive('put')->andReturnUsing(fn ($ruta, $stream, $opciones) => $this->disco->put($ruta, $stream, $opciones));
        $adapter->shouldReceive('delete')->twice()->andReturn(false);
        Storage::set('documentos_clinicos', $adapter);
        try {
            $this->confirmar($this->archivos(2));
            $this->fail('Debe reportar cleanup incompleto.');
        } catch (AlmacenamientoDocumentalException $error) {
            $this->assertSame($original, $error->getPrevious());
            $this->assertCount(2, $error->erroresEliminacion);
        }
        $this->conteos(0, 0, 0);
        $this->assertCount(2, $this->disco->allFiles());
        $this->assertCount(2, $this->coordinador->liberaciones);
    }

    public function test_post_commit_exception_verifies_full_references_never_deletes_and_retry_is_replay(): void
    {
        $adapter = $this->adapter();
        $adapter->shouldReceive('put')->twice()->andReturnUsing(fn ($ruta, $stream, $opciones) => $this->disco->put($ruta, $stream, $opciones));
        $adapter->shouldReceive('delete')->never();
        Storage::set('documentos_clinicos', $adapter);
        $original = new RuntimeException('Respuesta posterior al commit perdida');
        Event::listen(TransactionCommitted::class, fn () => throw $original);
        $resultado = $this->confirmar($this->archivos(2));
        $this->assertTrue($resultado->esReplay);
        $this->conteos(1, 2, 3);
        $this->assertCount(2, $this->disco->allFiles());
        $this->assertSame([], $this->coordinador->activos);
        $this->assertSame($resultado->turno->id, $this->confirmar([new \stdClass])->turno->id);
        $this->conteos(1, 2, 3);
    }

    public function test_verification_impossible_preserves_files_and_reports_original_without_reconnecting(): void
    {
        $original = new RuntimeException('Commit sin respuesta verificable');
        Event::listen(TransactionCommitted::class, function () use ($original): never {
            DB::connection()->setPdo(null);
            throw $original;
        });
        try {
            $this->confirmar($this->archivos(1));
            $this->fail('No debe inventar éxito ni rollback.');
        } catch (ResultadoConfirmacionInciertoException $error) {
            $this->assertSame($original, $error->getPrevious());
            $this->assertNotNull($error->errorVerificacion);
            $this->assertNull(DB::connection()->getRawPdo());
        } finally {
            DB::connection()->setPdo($this->pdo);
        }
        $this->assertCount(1, $this->disco->allFiles());
        $this->assertCount(1, $this->coordinador->liberaciones);
        $this->conteos(1, 1, 2);
        $this->assertTrue($this->confirmar()->esReplay);
    }

    public function test_active_transaction_after_commit_failure_is_not_mistaken_for_committed_rows(): void
    {
        $original = new RuntimeException('Commit interrumpido antes de PDO');
        Event::listen(TransactionCommitting::class, fn () => throw $original);
        try {
            $this->confirmar($this->archivos(1));
            $this->fail('Las filas propias no certifican un commit.');
        } catch (ResultadoConfirmacionInciertoException $error) {
            $this->assertSame($original, $error->getPrevious());
            $this->assertNotNull($error->errorVerificacion);
        }
        $this->assertTrue($this->pdo->inTransaction());
        $this->assertCount(1, $this->disco->allFiles());
        $this->assertCount(1, $this->coordinador->liberaciones);
        $this->pdo->rollBack();
        $this->conteos(0, 0, 0);
    }

    #[DataProvider('referenciasIncompletas')]
    public function test_partial_references_after_uncertain_result_preserve_entire_batch(bool $eliminarDocumentos): void
    {
        Event::listen(TransactionCommitted::class, function () use ($eliminarDocumentos): never {
            // Corrupción deliberada del fixture para certificar la salida conservadora.
            DB::table('documentos_clinicos_origen_turno')->delete();
            if ($eliminarDocumentos) {
                DB::table('documentos_clinicos')->delete();
            }
            throw new RuntimeException('Resultado inconsistente');
        });
        try {
            $this->confirmar($this->archivos(2));
            $this->fail('Una referencia incompleta debe quedar incierta.');
        } catch (ResultadoConfirmacionInciertoException $error) {
            $this->assertNull($error->errorVerificacion);
        }
        $this->assertCount(2, $this->disco->allFiles());
        $this->assertCount(2, $this->coordinador->liberaciones);
    }

    public static function referenciasIncompletas(): array
    {
        return [[false], [true]];
    }

    public function test_release_failure_during_confirmed_rollback_preserves_original_and_continues_releasing(): void
    {
        $original = new RuntimeException('Falla original antes de commit');
        DocumentoClinico::creating(function () use ($original): never {
            $this->coordinador->fallarLiberacion = array_key_first($this->coordinador->activos);
            throw $original;
        });
        try {
            $this->confirmar($this->archivos(2));
            $this->fail('Debe reportar release fallido y causa original.');
        } catch (AlmacenamientoDocumentalException $error) {
            $this->assertSame($original, $error->getPrevious());
            $this->assertCount(1, $error->erroresLiberacion);
            $this->assertSame([], $error->erroresEliminacion);
        }
        $this->conteos(0, 0, 0);
        $this->assertSame([], $this->disco->allFiles());
        $this->assertCount(2, $this->coordinador->liberaciones);
    }

    #[DataProvider('resultadosRelease')]
    public function test_release_failure_never_deletes_committed_files_or_hides_uncertain_cause(bool $incierto): void
    {
        $original = new RuntimeException('Falla principal alrededor del commit');
        Auditoria::creating(function (): void {
            $this->coordinador->fallarLiberacion = array_key_first($this->coordinador->activos);
        });
        if ($incierto) {
            Event::listen(TransactionCommitted::class, function () use ($original): never {
                DB::connection()->setPdo(null);
                throw $original;
            });
        }
        try {
            $this->confirmar($this->archivos(1));
            $this->fail('Debe reportar release fallido.');
        } catch (ResultadoConfirmacionInciertoException $error) {
            $this->assertTrue($incierto);
            $this->assertSame($original, $error->getPrevious());
            $this->assertInstanceOf(AlmacenamientoDocumentalException::class, $error->errorFinalizacion);
        } catch (AlmacenamientoDocumentalException $error) {
            $this->assertFalse($incierto);
            $this->assertCount(1, $error->erroresLiberacion);
        } finally {
            DB::connection()->setPdo($this->pdo);
        }
        $this->conteos(1, 1, 2);
        $this->assertCount(1, $this->disco->allFiles());
        $this->assertTrue($this->confirmar()->esReplay);
    }

    public static function resultadosRelease(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('contextosUuid')]
    public function test_losing_uuid_batch_is_compensated_before_replay_or_private_rejection(bool $ajeno, bool $conDocumento): void
    {
        $original = $this->errorSql('UNIQUE constraint failed: turnos.clave_idempotencia');
        $fallo = false;
        $intentos = 0;
        Turno::creating(function () use (&$fallo, &$intentos, $original): void {
            if (! $fallo) {
                $fallo = true;
                $intentos++;
                throw $original;
            }
        });
        $propietario = $ajeno ? Paciente::create(['user_id' => User::factory()->create()->id]) : $this->paciente;
        $ganador = null;
        Event::listen(TransactionRolledBack::class, function () use ($propietario, &$ganador, $conDocumento): void {
            $ganador = $this->turnoGanador($propietario, $this->uuid);
            if ($conDocumento) {
                $this->disco->put('originales/ganador', 'archivo previo consolidado');
                $documento = DocumentoClinico::factory()->for($propietario)->for($propietario->user, 'cargadoPor')
                    ->create(['ruta_storage' => 'originales/ganador']);
                $documento->origenTurno()->create(['turno_id' => $ganador->id]);
            }
        });
        try {
            $resultado = $this->confirmar($this->archivos(2));
            $this->assertFalse($ajeno);
            $this->assertTrue($resultado->esReplay);
            $this->assertSame($ganador->id, $resultado->turno->id);
        } catch (ClaveIdempotenciaIncompatibleException $error) {
            $this->assertTrue($ajeno);
            $this->assertSame('La clave de intención no puede utilizarse.', $error->getMessage());
        }
        $this->assertSame(1, $intentos);
        $this->conteos(1, $conDocumento ? 1 : 0, 0);
        $this->assertSame($conDocumento ? ['originales/ganador'] : [], $this->disco->allFiles());
        $this->assertCount(2, $this->coordinador->liberaciones);
    }

    public static function contextosUuid(): array
    {
        return [[false, false], [true, false], [false, true], [true, true]];
    }

    public function test_other_sql_errors_are_propagated_after_safe_compensation(): void
    {
        $original = $this->errorSql('UNIQUE constraint failed: turnos.id');
        Turno::creating(fn () => throw $original);
        try {
            $this->confirmar($this->archivos(1));
            $this->fail('Debe propagar el error SQL original.');
        } catch (QueryException $error) {
            $this->assertSame($original, $error);
        }
        $this->conteos(0, 0, 0);
        $this->assertSame([], $this->disco->allFiles());
    }

    private function confirmar(array $archivos = [])
    {
        return app(ConfirmarPrimeraConsultaAction::class)->ejecutar(
            $this->actor, $this->uuid, $this->kinesiologa->id,
            CarbonImmutable::parse('2026-10-05 10:00:00', config('app.timezone')), 'Diagnóstico sensible', $archivos,
        );
    }

    private function conteos(int $turnos, int $documentos, int $auditorias): void
    {
        $this->assertDatabaseCount('turnos', $turnos);
        $this->assertDatabaseCount('documentos_clinicos', $documentos);
        $this->assertDatabaseCount('documentos_clinicos_origen_turno', $documentos);
        $this->assertDatabaseCount('auditorias', $auditorias);
    }

    private function archivos(int $cantidad): array
    {
        $archivos = [];
        for ($indice = 0; $indice < $cantidad; $indice++) {
            $archivos[$indice * 2 + 1] = $this->pdf('estudio '.$indice.'.jpg', $indice);
        }

        return $archivos;
    }

    private function pdf(string $nombre = 'estudio.pdf', int $indice = 0): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'ks-atomicidad-');
        $this->temporales[] = $ruta;
        file_put_contents($ruta, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Numero $indice >>\nendobj\n%%EOF\n");

        return new UploadedFile($ruta, $nombre, 'image/jpeg', UPLOAD_ERR_OK, true);
    }

    private function adapter()
    {
        $adapter = Mockery::mock(FilesystemAdapter::class);
        $adapter->shouldReceive('exists')->andReturnUsing(fn ($ruta) => $this->disco->exists($ruta));

        return $adapter;
    }

    private function errorSql(string $detalle): QueryException
    {
        $causa = new PDOException($detalle);
        $causa->errorInfo = ['23000', 19, $detalle];

        return new QueryException('sqlite', 'insert into turnos (...) values (...)', [], $causa);
    }

    private function turnoGanador(Paciente $paciente, string $uuid): Turno
    {
        $turno = Turno::factory()->for($paciente)->for($this->kinesiologa)->make([
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA,
            'inicio_at' => '2026-10-05 10:00:00', 'fin_at' => '2026-10-05 10:30:00',
        ]);
        $turno->clave_idempotencia = $uuid;
        $turno->save();

        return $turno;
    }

    private function profesional(): Kinesiologa
    {
        Role::findOrCreate(RoleEnum::KINESIOLOGO->value, 'web');
        $user = User::factory()->create(['dni' => '12345678', 'fecha_nacimiento' => '1990-01-01', 'sexo' => 'femenino', 'telefono' => '3764123456', 'activo' => true]);
        $user->assignRole(RoleEnum::KINESIOLOGO->value);
        $titulo = TituloProfesional::create(['nombre' => 'Título', 'slug' => 'titulo', 'activo' => true]);
        $especialidad = EspecialidadKinesiologia::create(['nombre' => 'Especialidad', 'slug' => 'especialidad', 'activo' => true]);
        $jurisdiccion = Jurisdiccion::create(['nombre' => 'Jurisdicción', 'slug' => 'jurisdiccion', 'activo' => true]);
        $colegio = ColegioEmisor::create(['nombre' => 'Colegio', 'sigla' => 'AT', 'jurisdiccion_id' => $jurisdiccion->id, 'activo' => true]);
        $kinesiologa = Kinesiologa::create(['user_id' => $user->id, 'titulo_profesional_id' => $titulo->id]);
        MatriculaProfesionalKinesiologia::create(['kinesiologa_id' => $kinesiologa->id, 'nro_matricula_profesional' => 'MP-1', 'jurisdiccion_id' => $jurisdiccion->id, 'colegio_emisor_id' => $colegio->id, 'activa' => true]);
        $kinesiologa->especialidades()->attach($especialidad);

        return $kinesiologa;
    }
}
