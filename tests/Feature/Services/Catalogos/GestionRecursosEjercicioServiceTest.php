<?php

namespace Tests\Feature\Services\Catalogos;

use App\Enums\DificultadEjercicioEnum;
use App\Enums\OrigenRecursoEjercicioEnum;
use App\Enums\TipoRecursoEjercicioEnum;
use App\Models\EjercicioDomiciliario;
use App\Models\RecursoEjercicio;
use App\Services\Catalogos\GestionRecursosEjercicioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class GestionRecursosEjercicioServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_uses_public_disk_configured_directory_and_returns_relative_path(): void
    {
        $resultado = $this->service()->prepararNuevo(
            $this->datosLocal(UploadedFile::fake()->create('guia.pdf', 120))
        );
        $ruta = $resultado['atributos']['ruta_storage'];
        $directorio = config('kinesiosoft.ejercicios.recursos_directorio');

        $this->assertSame('ejercicios/recursos', $directorio);
        $this->assertIsString($ruta);
        $this->assertStringStartsWith($directorio.'/', $ruta);
        $this->assertFalse(str_starts_with($ruta, '/'));
        $this->assertSame(0, preg_match('/^[A-Za-z]:[\\\\\/]/', $ruta));
        $this->assertStringNotContainsString(storage_path(), $ruta);
        Storage::disk('public')->assertExists($ruta);
    }

    public function test_prepares_new_local_resource_and_marks_file_for_rollback(): void
    {
        $archivo = UploadedFile::fake()->create(
            'posicion-original.jpg',
            50,
            'image/jpeg'
        );

        $resultado = $this->service()->prepararNuevo($this->datosLocal(
            $archivo,
            ['tipo' => TipoRecursoEjercicioEnum::IMAGEN]
        ));
        $atributos = $resultado['atributos'];
        $ruta = $atributos['ruta_storage'];

        $this->assertSame(TipoRecursoEjercicioEnum::IMAGEN->value, $atributos['tipo']);
        $this->assertSame(OrigenRecursoEjercicioEnum::LOCAL->value, $atributos['origen']);
        $this->assertNull($atributos['url_externa']);
        $this->assertSame([$ruta], $resultado['archivos_nuevos']);
        $this->assertSame([], $resultado['archivos_antiguos_a_eliminar']);
        $this->assertNotSame('posicion-original.jpg', basename($ruta));
        Storage::disk('public')->assertExists($ruta);
    }

    public function test_prepares_new_external_resource_without_touching_storage(): void
    {
        $resultado = $this->service()->prepararNuevo($this->datosExterno());

        $this->assertNull($resultado['atributos']['ruta_storage']);
        $this->assertSame(
            'https://example.com/video',
            $resultado['atributos']['url_externa']
        );
        $this->assertSame([], $resultado['archivos_nuevos']);
        $this->assertSame([], $resultado['archivos_antiguos_a_eliminar']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_preserves_existing_local_file_without_replacement(): void
    {
        $rutaVieja = 'ejercicios/recursos/viejo.pdf';
        Storage::disk('public')->put($rutaVieja, 'contenido viejo');

        $resultado = $this->service()->prepararActualizacion(
            $this->recursoLocal($rutaVieja),
            $this->datosLocalSinArchivo(['titulo' => 'Título editado'])
        );

        $this->assertSame($rutaVieja, $resultado['atributos']['ruta_storage']);
        $this->assertNull($resultado['atributos']['url_externa']);
        $this->assertSame([], $resultado['archivos_nuevos']);
        $this->assertSame([], $resultado['archivos_antiguos_a_eliminar']);
        Storage::disk('public')->assertExists($rutaVieja);
    }

    public function test_inactivating_local_resource_preserves_its_file(): void
    {
        $rutaVieja = 'ejercicios/recursos/inactivar.pdf';
        Storage::disk('public')->put($rutaVieja, 'contenido');

        $resultado = $this->service()->prepararActualizacion(
            $this->recursoLocal($rutaVieja, ['activo' => true]),
            $this->datosLocalSinArchivo(['activo' => false])
        );

        $this->assertFalse($resultado['atributos']['activo']);
        $this->assertSame($rutaVieja, $resultado['atributos']['ruta_storage']);
        $this->assertSame([], $resultado['archivos_antiguos_a_eliminar']);
        Storage::disk('public')->assertExists($rutaVieja);
    }

    public function test_reactivating_local_resource_does_not_require_reupload(): void
    {
        $rutaVieja = 'ejercicios/recursos/reactivar.pdf';
        Storage::disk('public')->put($rutaVieja, 'contenido');

        $resultado = $this->service()->prepararActualizacion(
            $this->recursoLocal($rutaVieja, ['activo' => false]),
            $this->datosLocalSinArchivo(['activo' => true])
        );

        $this->assertTrue($resultado['atributos']['activo']);
        $this->assertSame($rutaVieja, $resultado['atributos']['ruta_storage']);
        $this->assertSame([], $resultado['archivos_nuevos']);
        Storage::disk('public')->assertExists($rutaVieja);
    }

    public function test_local_replacement_keeps_old_file_pending_until_commit(): void
    {
        $rutaVieja = 'ejercicios/recursos/reemplazado.pdf';
        Storage::disk('public')->put($rutaVieja, 'contenido viejo');

        $resultado = $this->service()->prepararActualizacion(
            $this->recursoLocal($rutaVieja),
            $this->datosLocal(UploadedFile::fake()->create('nuevo.pdf', 80))
        );
        $rutaNueva = $resultado['atributos']['ruta_storage'];

        $this->assertNotSame($rutaVieja, $rutaNueva);
        $this->assertSame([$rutaNueva], $resultado['archivos_nuevos']);
        $this->assertSame([$rutaVieja], $resultado['archivos_antiguos_a_eliminar']);
        Storage::disk('public')->assertExists($rutaNueva);
        Storage::disk('public')->assertExists($rutaVieja);
    }

    public function test_replacement_rollback_removes_new_file_and_keeps_old_file(): void
    {
        $rutaVieja = 'ejercicios/recursos/rollback-viejo.pdf';
        Storage::disk('public')->put($rutaVieja, 'contenido viejo');
        $resultado = $this->service()->prepararActualizacion(
            $this->recursoLocal($rutaVieja),
            $this->datosLocal(UploadedFile::fake()->create('rollback-nuevo.pdf', 80))
        );
        $rutaNueva = $resultado['atributos']['ruta_storage'];

        $this->service()->limpiarArchivosNuevos($resultado['archivos_nuevos']);

        Storage::disk('public')->assertMissing($rutaNueva);
        Storage::disk('public')->assertExists($rutaVieja);
    }

    public function test_replacement_post_commit_removes_old_file_and_keeps_new_file(): void
    {
        $rutaVieja = 'ejercicios/recursos/commit-viejo.pdf';
        Storage::disk('public')->put($rutaVieja, 'contenido viejo');
        $resultado = $this->service()->prepararActualizacion(
            $this->recursoLocal($rutaVieja),
            $this->datosLocal(UploadedFile::fake()->create('commit-nuevo.pdf', 80))
        );
        $rutaNueva = $resultado['atributos']['ruta_storage'];

        $this->service()->eliminarArchivosAntiguos(
            $resultado['archivos_antiguos_a_eliminar']
        );

        Storage::disk('public')->assertExists($rutaNueva);
        Storage::disk('public')->assertMissing($rutaVieja);
    }

    public function test_local_to_external_defers_old_file_deletion_until_post_commit(): void
    {
        $rutaVieja = 'ejercicios/recursos/local-a-externo.pdf';
        Storage::disk('public')->put($rutaVieja, 'contenido viejo');
        $resultado = $this->service()->prepararActualizacion(
            $this->recursoLocal($rutaVieja),
            $this->datosExterno(['url_externa' => 'https://example.com/nuevo'])
        );

        $this->assertNull($resultado['atributos']['ruta_storage']);
        $this->assertSame('https://example.com/nuevo', $resultado['atributos']['url_externa']);
        $this->assertSame([], $resultado['archivos_nuevos']);
        $this->assertSame([$rutaVieja], $resultado['archivos_antiguos_a_eliminar']);
        Storage::disk('public')->assertExists($rutaVieja);

        $this->service()->limpiarArchivosNuevos($resultado['archivos_nuevos']);
        Storage::disk('public')->assertExists($rutaVieja);

        $this->service()->eliminarArchivosAntiguos(
            $resultado['archivos_antiguos_a_eliminar']
        );
        Storage::disk('public')->assertMissing($rutaVieja);
    }

    public function test_external_to_local_creates_new_file_that_rollback_can_remove(): void
    {
        $resultado = $this->service()->prepararActualizacion(
            $this->recursoExterno('https://example.com/anterior'),
            $this->datosLocal(UploadedFile::fake()->create('local.pdf', 80))
        );
        $rutaNueva = $resultado['atributos']['ruta_storage'];

        $this->assertNull($resultado['atributos']['url_externa']);
        $this->assertSame([$rutaNueva], $resultado['archivos_nuevos']);
        $this->assertSame([], $resultado['archivos_antiguos_a_eliminar']);
        Storage::disk('public')->assertExists($rutaNueva);

        $this->service()->limpiarArchivosNuevos($resultado['archivos_nuevos']);
        Storage::disk('public')->assertMissing($rutaNueva);
    }

    public function test_existing_external_resource_preserves_url_without_new_url(): void
    {
        $resultado = $this->service()->prepararActualizacion(
            $this->recursoExterno('https://example.com/anterior'),
            $this->datosExterno(['url_externa' => null])
        );

        $this->assertNull($resultado['atributos']['ruta_storage']);
        $this->assertSame(
            'https://example.com/anterior',
            $resultado['atributos']['url_externa']
        );
        $this->assertSame([], $resultado['archivos_nuevos']);
        $this->assertSame([], $resultado['archivos_antiguos_a_eliminar']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_existing_external_resource_uses_new_url_without_touching_storage(): void
    {
        $resultado = $this->service()->prepararActualizacion(
            $this->recursoExterno('https://example.com/anterior'),
            $this->datosExterno(['url_externa' => 'https://example.com/actualizada'])
        );

        $this->assertNull($resultado['atributos']['ruta_storage']);
        $this->assertSame(
            'https://example.com/actualizada',
            $resultado['atributos']['url_externa']
        );
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_rejects_new_local_resource_without_file(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->prepararNuevo($this->datosLocalSinArchivo());
    }

    public function test_rejects_new_external_resource_without_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->prepararNuevo($this->datosExterno(['url_externa' => null]));
    }

    public function test_rejects_local_to_external_without_new_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->prepararActualizacion(
            $this->recursoLocal('ejercicios/recursos/viejo.pdf'),
            $this->datosExterno(['url_externa' => null])
        );
    }

    public function test_rejects_external_to_local_without_file(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->prepararActualizacion(
            $this->recursoExterno('https://example.com/anterior'),
            $this->datosLocalSinArchivo()
        );
    }

    public function test_rejects_url_for_local_resource(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->prepararNuevo($this->datosLocal(
            UploadedFile::fake()->create('local.pdf', 10),
            ['url_externa' => 'https://example.com/incorrecta']
        ));
    }

    public function test_rejects_uploaded_file_for_external_resource(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->prepararNuevo($this->datosExterno([
            'archivo' => UploadedFile::fake()->create('incorrecto.pdf', 10),
        ]));
    }

    public function test_throws_when_uploaded_file_cannot_be_stored(): void
    {
        $archivo = \Mockery::mock(UploadedFile::class);
        $archivo->shouldReceive('store')
            ->once()
            ->with('ejercicios/recursos', 'public')
            ->andReturn(false);

        $this->expectException(RuntimeException::class);

        $this->service()->prepararNuevo($this->datosLocal($archivo));
    }

    public function test_cleanup_methods_accept_empty_arrays_and_deduplicate_paths(): void
    {
        $rutaNueva = 'ejercicios/recursos/nueva.pdf';
        $rutaVieja = 'ejercicios/recursos/vieja.pdf';
        Storage::disk('public')->put($rutaNueva, 'nuevo');
        Storage::disk('public')->put($rutaVieja, 'viejo');

        $this->service()->limpiarArchivosNuevos([]);
        $this->service()->eliminarArchivosAntiguos([]);
        $this->service()->limpiarArchivosNuevos([$rutaNueva, $rutaNueva]);
        $this->service()->eliminarArchivosAntiguos([$rutaVieja, $rutaVieja]);

        Storage::disk('public')->assertMissing($rutaNueva);
        Storage::disk('public')->assertMissing($rutaVieja);
    }

    public function test_ignores_client_storage_paths_as_persistence_or_deletion_sources(): void
    {
        $rutaCliente = 'ejercicios/recursos/controlada-por-cliente.pdf';
        Storage::disk('public')->put($rutaCliente, 'no tocar');

        $resultado = $this->service()->prepararNuevo($this->datosLocal(
            UploadedFile::fake()->create('seguro.pdf', 10),
            [
                'ruta_storage' => 'C:\\fuera\\archivo.pdf',
                'route_storage' => $rutaCliente,
            ]
        ));
        $rutaGenerada = $resultado['atributos']['ruta_storage'];

        $this->assertNotSame('C:\\fuera\\archivo.pdf', $rutaGenerada);
        $this->assertNotSame($rutaCliente, $rutaGenerada);
        Storage::disk('public')->assertExists($rutaGenerada);
        Storage::disk('public')->assertExists($rutaCliente);
    }

    public function test_does_not_persist_or_modify_models_while_preparing_update(): void
    {
        $ejercicio = EjercicioDomiciliario::create([
            'codigo' => 'EJ-SERVICE-001',
            'nombre' => 'Ejercicio de prueba',
            'descripcion' => 'Descripción del ejercicio de prueba.',
            'dificultad_base' => DificultadEjercicioEnum::BAJA,
            'activo' => true,
        ]);
        $rutaVieja = 'ejercicios/recursos/persistido.pdf';
        Storage::disk('public')->put($rutaVieja, 'contenido');
        $recurso = $ejercicio->recursos()->create([
            'tipo' => TipoRecursoEjercicioEnum::DOCUMENTO,
            'origen' => OrigenRecursoEjercicioEnum::LOCAL,
            'ruta_storage' => $rutaVieja,
            'url_externa' => null,
            'titulo' => 'Título original',
            'orden' => 1,
            'activo' => true,
        ]);

        $resultado = $this->service()->prepararActualizacion(
            $recurso,
            $this->datosLocalSinArchivo([
                'titulo' => 'Título preparado',
                'orden' => 4,
                'activo' => false,
            ])
        );

        $this->assertSame('Título preparado', $resultado['atributos']['titulo']);
        $this->assertSame(4, $resultado['atributos']['orden']);
        $this->assertFalse($resultado['atributos']['activo']);

        $recurso->refresh();

        $this->assertSame('Título original', $recurso->titulo);
        $this->assertSame(1, $recurso->orden);
        $this->assertTrue($recurso->activo);
        $this->assertSame($rutaVieja, $recurso->ruta_storage);
        $this->assertDatabaseCount('recursos_ejercicio', 1);
    }

    private function service(): GestionRecursosEjercicioService
    {
        return app(GestionRecursosEjercicioService::class);
    }

    /**
     * @param  array<string, mixed>  $sobrescrituras
     * @return array<string, mixed>
     */
    private function datosLocal(
        UploadedFile $archivo,
        array $sobrescrituras = []
    ): array {
        return array_merge($this->datosLocalSinArchivo(), [
            'archivo' => $archivo,
        ], $sobrescrituras);
    }

    /**
     * @param  array<string, mixed>  $sobrescrituras
     * @return array<string, mixed>
     */
    private function datosLocalSinArchivo(array $sobrescrituras = []): array
    {
        return array_merge([
            'tipo' => TipoRecursoEjercicioEnum::DOCUMENTO,
            'origen' => OrigenRecursoEjercicioEnum::LOCAL,
            'titulo' => 'Guía del ejercicio',
            'orden' => 1,
            'activo' => true,
        ], $sobrescrituras);
    }

    /**
     * @param  array<string, mixed>  $sobrescrituras
     * @return array<string, mixed>
     */
    private function datosExterno(array $sobrescrituras = []): array
    {
        return array_merge([
            'tipo' => TipoRecursoEjercicioEnum::VIDEO,
            'origen' => OrigenRecursoEjercicioEnum::EXTERNO,
            'titulo' => 'Video del ejercicio',
            'orden' => 1,
            'activo' => true,
            'url_externa' => 'https://example.com/video',
        ], $sobrescrituras);
    }

    /**
     * @param  array<string, mixed>  $sobrescrituras
     */
    private function recursoLocal(
        string $ruta,
        array $sobrescrituras = []
    ): RecursoEjercicio {
        return new RecursoEjercicio(array_merge([
            'tipo' => TipoRecursoEjercicioEnum::DOCUMENTO,
            'origen' => OrigenRecursoEjercicioEnum::LOCAL,
            'ruta_storage' => $ruta,
            'url_externa' => null,
            'titulo' => 'Guía existente',
            'orden' => 1,
            'activo' => true,
        ], $sobrescrituras));
    }

    private function recursoExterno(string $url): RecursoEjercicio
    {
        return new RecursoEjercicio([
            'tipo' => TipoRecursoEjercicioEnum::VIDEO,
            'origen' => OrigenRecursoEjercicioEnum::EXTERNO,
            'ruta_storage' => null,
            'url_externa' => $url,
            'titulo' => 'Video existente',
            'orden' => 1,
            'activo' => true,
        ]);
    }
}
