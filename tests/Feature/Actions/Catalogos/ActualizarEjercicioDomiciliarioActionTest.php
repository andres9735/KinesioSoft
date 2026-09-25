<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\ActualizarEjercicioDomiciliarioAction;
use App\Models\Auditoria;
use App\Models\CategoriaClinica;
use App\Models\EjercicioDomiciliario;
use App\Models\RecursoEjercicio;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ActualizarEjercicioDomiciliarioActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_only_changed_scalars_and_preserves_active_state(): void
    {
        $anatomica = $this->categoria('ANATOMICA');
        $activo = $this->ejercicio(true, [$anatomica]);

        $actualizado = $this->actualizar($activo, [
            'nombre' => 'Puente unilateral',
            'series_recomendadas' => 4,
            'activo' => false,
        ]);

        $this->assertTrue($actualizado->activo);
        $this->assertSame('Puente unilateral', $actualizado->nombre);
        $this->assertSame(4, $actualizado->series_recomendadas);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('ejercicio_domiciliario_actualizado', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame([
            'nombre' => 'Puente de glúteos',
            'series_recomendadas' => 3,
        ], $auditoria->valores_anteriores);
        $this->assertSame([
            'nombre' => 'Puente unilateral',
            'series_recomendadas' => 4,
        ], $auditoria->valores_nuevos);

        Auditoria::query()->delete();
        $inactivo = $this->ejercicio(false, [$anatomica], 'EJ-002');
        $editado = $this->actualizar($inactivo, [
            'nombre' => 'Histórico editado',
            'activo' => true,
        ]);

        $this->assertFalse($editado->activo);
        $this->assertSame('Histórico editado', $editado->nombre);
    }

    public function test_handles_new_and_historical_categories_and_audits_set_change(): void
    {
        $anatomica = $this->categoria('ANATOMICA');
        $funcionalHistorica = $this->categoria('FUNCIONAL');
        $terapeuticaNueva = $this->categoria('TERAPEUTICA');
        $ejercicio = $this->ejercicio(true, [$anatomica, $funcionalHistorica]);
        $funcionalHistorica->update(['activo' => false]);

        $actualizado = $this->actualizar($ejercicio, [
            'categorias' => [
                $terapeuticaNueva->id,
                $funcionalHistorica->id,
                $anatomica->id,
            ],
        ]);

        $this->assertTrue($actualizado->activo);
        $this->assertEqualsCanonicalizing(
            [$anatomica->id, $funcionalHistorica->id, $terapeuticaNueva->id],
            $actualizado->categorias()->pluck('categorias_clinicas.id')->all()
        );
        $auditoria = Auditoria::query()->sole();
        $this->assertEqualsCanonicalizing(
            [$anatomica->id, $funcionalHistorica->id],
            $auditoria->valores_anteriores['categorias']
        );
        $this->assertEqualsCanonicalizing(
            [$anatomica->id, $funcionalHistorica->id, $terapeuticaNueva->id],
            $auditoria->valores_nuevos['categorias']
        );
    }

    public function test_rejects_new_inactive_and_equipment_categories_without_changes(): void
    {
        $anatomica = $this->categoria('ANATOMICA');
        $inactiva = $this->categoria('FUNCIONAL', false);
        $equipamiento = $this->categoria('EQUIPAMIENTO');
        $ejercicio = $this->ejercicio(false, [$anatomica]);

        foreach ([$inactiva, $equipamiento] as $categoria) {
            try {
                $this->actualizar($ejercicio, [
                    'categorias' => [$anatomica->id, $categoria->id],
                ]);
                $this->fail('Se esperaba el rechazo de la asociación nueva inválida.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('categorias', $exception->errors());
            }
        }

        $this->assertEquals(
            [$anatomica->id],
            $ejercicio->categorias()->pluck('categorias_clinicas.id')->all()
        );
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_active_result_requires_active_anatomical_and_rejects_historical_equipment(): void
    {
        $anatomicaInactiva = $this->categoria('ANATOMICA');
        $funcional = $this->categoria('FUNCIONAL');
        $ejercicio = $this->ejercicio(true, [$anatomicaInactiva, $funcional]);
        $anatomicaInactiva->update(['activo' => false]);

        $this->assertCategoryUpdateRejected($ejercicio, [
            $anatomicaInactiva->id,
            $funcional->id,
        ]);

        $anatomicaActiva = $this->categoria('ANATOMICA');
        $historica = $this->categoria('FUNCIONAL');
        $otro = $this->ejercicio(true, [$anatomicaActiva, $historica], 'EJ-002');
        $historica->update(['tipo' => 'EQUIPAMIENTO', 'activo' => false]);

        $this->assertCategoryUpdateRejected($otro, [
            $anatomicaActiva->id,
            $historica->id,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_inactive_exercise_can_have_no_categories_and_keep_historical_equipment(): void
    {
        $historica = $this->categoria('FUNCIONAL');
        $sinCategorias = $this->ejercicio(false, [$historica]);

        $actualizado = $this->actualizar($sinCategorias, ['categorias' => []]);

        $this->assertCount(0, $actualizado->categorias);

        Auditoria::query()->delete();
        $otraHistorica = $this->categoria('FUNCIONAL');
        $conHistorica = $this->ejercicio(false, [$otraHistorica], 'EJ-002');
        $otraHistorica->update(['tipo' => 'EQUIPAMIENTO', 'activo' => false]);

        $editado = $this->actualizar($conHistorica, [
            'nombre' => 'Inactivo con vínculo histórico',
            'categorias' => [$otraHistorica->id],
        ]);

        $this->assertFalse($editado->activo);
        $this->assertSame($otraHistorica->id, $editado->categorias()->sole()->id);
    }

    public function test_omitted_resources_remain_and_empty_or_missing_resource_payload_is_non_destructive(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $ejercicio = $this->ejercicio(true, [$anatomica]);
        $local = $this->recursoLocalPersistido($ejercicio, 'a.jpg');
        $externo = $this->recursoExternoPersistido($ejercicio);
        $rutaLocal = $local->ruta_storage;

        $this->actualizar($ejercicio, [
            'recursos' => [
                $this->payloadRecurso($local, ['titulo' => 'Local editado']),
            ],
        ]);

        $this->assertDatabaseHas('recursos_ejercicio', [
            'id' => $externo->id,
            'url_externa' => 'https://example.com/original',
            'activo' => true,
        ]);
        Storage::disk('public')->assertExists($rutaLocal);

        Auditoria::query()->delete();
        $this->actualizar($ejercicio, ['recursos' => []]);
        $this->actualizar($ejercicio);

        $this->assertDatabaseCount('recursos_ejercicio', 2);
        Storage::disk('public')->assertExists($rutaLocal);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_rejects_foreign_and_duplicate_resource_ids_before_mutating(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $ejercicioA = $this->ejercicio(true, [$anatomica]);
        $ejercicioB = $this->ejercicio(true, [$anatomica], 'EJ-002');
        $recursoA = $this->recursoExternoPersistido($ejercicioA, 'https://example.com/a');
        $recursoB = $this->recursoExternoPersistido($ejercicioB, 'https://example.com/b');

        foreach ([
            [$this->payloadRecurso($recursoB, ['titulo' => 'Ajeno'])],
            [
                $this->payloadRecurso($recursoA),
                $this->payloadRecurso($recursoA, ['titulo' => 'Duplicado']),
            ],
        ] as $recursos) {
            try {
                $this->actualizar($ejercicioA, ['recursos' => $recursos]);
                $this->fail('Se esperaba el rechazo del recurso inválido.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->assertDatabaseHas('recursos_ejercicio', [
            'id' => $recursoA->id,
            'titulo' => 'Externo',
            'url_externa' => 'https://example.com/a',
        ]);
        $this->assertDatabaseHas('recursos_ejercicio', [
            'id' => $recursoB->id,
            'titulo' => 'Externo',
            'url_externa' => 'https://example.com/b',
        ]);
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_local_resource_keeps_file_without_replacement_and_supports_inactivate_reactivate(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $ejercicio = $this->ejercicio(true, [$anatomica]);
        $local = $this->recursoLocalPersistido($ejercicio, 'original.jpg');
        $ruta = $local->ruta_storage;

        $this->actualizar($ejercicio, [
            'recursos' => [$this->payloadRecurso($local, [
                'titulo' => 'Metadatos editados',
                'orden' => 7,
                'activo' => false,
            ])],
        ]);

        $local->refresh();
        $this->assertSame($ruta, $local->ruta_storage);
        $this->assertFalse($local->activo);
        Storage::disk('public')->assertExists($ruta);
        $this->assertCount(1, Storage::disk('public')->allFiles());

        $this->actualizar($ejercicio, [
            'recursos' => [$this->payloadRecurso($local, ['activo' => true])],
        ]);

        $this->assertTrue($local->refresh()->activo);
        $this->assertSame($ruta, $local->ruta_storage);
        Storage::disk('public')->assertExists($ruta);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_replaces_local_file_and_deletes_old_only_after_successful_commit(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $ejercicio = $this->ejercicio(true, [$anatomica]);
        $local = $this->recursoLocalPersistido($ejercicio, 'anterior.jpg');
        $rutaAnterior = $local->ruta_storage;
        $contenidoArchivo = 'CONTENIDO_BINARIO_REEMPLAZO_NO_AUDITAR';

        $this->actualizar($ejercicio, [
            'recursos' => [$this->payloadRecurso($local, [
                'titulo' => 'Archivo reemplazado',
                'archivo' => UploadedFile::fake()->createWithContent(
                    'nuevo.jpg',
                    $contenidoArchivo
                ),
            ])],
        ]);

        $local->refresh();
        $this->assertNotSame($rutaAnterior, $local->ruta_storage);
        Storage::disk('public')->assertMissing($rutaAnterior);
        Storage::disk('public')->assertExists($local->ruta_storage);
        $auditoria = Auditoria::query()->sole();
        $this->assertArrayHasKey('recursos', $auditoria->valores_nuevos);

        $recursoAuditado = collect($auditoria->valores_nuevos['recursos'])
            ->firstWhere('id', $local->id);
        $this->assertIsArray($recursoAuditado);
        $this->assertSame([
            'id',
            'tipo',
            'origen',
            'ruta_storage',
            'url_externa',
            'titulo',
            'orden',
            'activo',
        ], array_keys($recursoAuditado));
        $this->assertSame($local->ruta_storage, $recursoAuditado['ruta_storage']);
        $this->assertDoesNotMatchRegularExpression(
            '/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/',
            $recursoAuditado['ruta_storage']
        );

        foreach ([$auditoria->valores_anteriores, $auditoria->valores_nuevos] as $snapshot) {
            array_walk_recursive(
                $snapshot,
                fn ($valor) => $this->assertNotInstanceOf(UploadedFile::class, $valor)
            );
        }

        $payloadAuditado = json_encode([
            'anteriores' => $auditoria->valores_anteriores,
            'nuevos' => $auditoria->valores_nuevos,
        ], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($contenidoArchivo, $payloadAuditado);
        $this->assertStringNotContainsString('base64,', strtolower($payloadAuditado));
        $this->assertStringNotContainsString(
            str_replace('\\', '/', sys_get_temp_dir()),
            str_replace('\\', '/', $payloadAuditado)
        );
    }

    public function test_supports_local_external_transitions_and_external_url_changes(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $ejercicio = $this->ejercicio(true, [$anatomica]);
        $local = $this->recursoLocalPersistido($ejercicio, 'local.jpg');
        $rutaAnterior = $local->ruta_storage;

        $this->actualizar($ejercicio, [
            'recursos' => [$this->payloadRecurso($local, [
                'origen' => 'EXTERNO',
                'url_externa' => 'https://example.com/convertido',
            ])],
        ]);

        $local->refresh();
        $this->assertSame('EXTERNO', $local->origen->value);
        $this->assertNull($local->ruta_storage);
        Storage::disk('public')->assertMissing($rutaAnterior);

        $this->actualizar($ejercicio, [
            'recursos' => [$this->payloadRecurso($local, [
                'url_externa' => null,
            ])],
        ]);
        $this->assertSame('https://example.com/convertido', $local->refresh()->url_externa);

        $this->actualizar($ejercicio, [
            'recursos' => [$this->payloadRecurso($local, [
                'url_externa' => 'https://example.com/cambiado',
            ])],
        ]);
        $this->assertSame('https://example.com/cambiado', $local->refresh()->url_externa);

        $this->actualizar($ejercicio, [
            'recursos' => [$this->payloadRecurso($local, [
                'origen' => 'LOCAL',
                'url_externa' => null,
                'archivo' => UploadedFile::fake()->create('vuelta-local.jpg', 10, 'image/jpeg'),
            ])],
        ]);

        $local->refresh();
        $this->assertSame('LOCAL', $local->origen->value);
        $this->assertNull($local->url_externa);
        Storage::disk('public')->assertExists($local->ruta_storage);
    }

    public function test_rolls_back_local_to_external_and_preserves_old_file_when_audit_fails(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $ejercicio = $this->ejercicio(true, [$anatomica]);
        $local = $this->recursoLocalPersistido($ejercicio, 'local-original.jpg');
        $rutaAnterior = $local->ruta_storage;
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            $this->actualizar($ejercicio, [
                'recursos' => [$this->payloadRecurso($local, [
                    'origen' => 'EXTERNO',
                    'url_externa' => 'https://example.com/no-persistir',
                ])],
            ]);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $local->refresh();
            $this->assertSame('LOCAL', $local->origen->value);
            $this->assertSame($rutaAnterior, $local->ruta_storage);
            $this->assertNull($local->url_externa);
            Storage::disk('public')->assertExists($rutaAnterior);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public function test_rolls_back_external_to_local_and_cleans_new_file_when_audit_fails(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $ejercicio = $this->ejercicio(true, [$anatomica]);
        $externo = $this->recursoExternoPersistido($ejercicio);
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            $this->actualizar($ejercicio, [
                'recursos' => [$this->payloadRecurso($externo, [
                    'origen' => 'LOCAL',
                    'url_externa' => null,
                    'archivo' => UploadedFile::fake()->create(
                        'no-persistir.jpg',
                        10,
                        'image/jpeg'
                    ),
                ])],
            ]);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $externo->refresh();
            $this->assertSame('EXTERNO', $externo->origen->value);
            $this->assertNull($externo->ruta_storage);
            $this->assertSame('https://example.com/original', $externo->url_externa);
            $this->assertSame([], Storage::disk('public')->allFiles());
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public function test_adds_new_local_and_external_resources_without_touching_existing_omitted_resource(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $ejercicio = $this->ejercicio(true, [$anatomica]);
        $existente = $this->recursoExternoPersistido($ejercicio);

        $this->actualizar($ejercicio, [
            'recursos' => [
                $this->nuevoLocal(UploadedFile::fake()->create('agregado.jpg', 10, 'image/jpeg')),
                $this->nuevoExterno('https://example.com/nuevo'),
            ],
        ]);

        $this->assertDatabaseCount('recursos_ejercicio', 3);
        $this->assertDatabaseHas('recursos_ejercicio', [
            'id' => $existente->id,
            'url_externa' => 'https://example.com/original',
        ]);
        $nuevoLocal = $ejercicio->recursos()->where('origen', 'LOCAL')->sole();
        Storage::disk('public')->assertExists($nuevoLocal->ruta_storage);
        $this->assertDatabaseHas('recursos_ejercicio', [
            'ejercicio_id' => $ejercicio->id,
            'url_externa' => 'https://example.com/nuevo',
        ]);
    }

    public function test_semantically_identical_payload_is_noop_despite_category_order(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $funcional = $this->categoria('FUNCIONAL');
        $ejercicio = $this->ejercicio(true, [$anatomica, $funcional]);
        $local = $this->recursoLocalPersistido($ejercicio, 'estable.jpg');
        $ruta = $local->ruta_storage;

        $actualizado = $this->actualizar($ejercicio, [
            'categorias' => [$funcional->id, $anatomica->id],
            'recursos' => [$this->payloadRecurso($local)],
        ]);

        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertCount(1, Storage::disk('public')->allFiles());
        Storage::disk('public')->assertExists($ruta);
    }

    public function test_rolls_back_local_replacement_and_preserves_old_file_when_audit_fails(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $ejercicio = $this->ejercicio(true, [$anatomica]);
        $local = $this->recursoLocalPersistido($ejercicio, 'anterior.jpg');
        $rutaAnterior = $local->ruta_storage;
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            $this->actualizar($ejercicio, [
                'recursos' => [$this->payloadRecurso($local, [
                    'titulo' => 'No persistir',
                    'archivo' => UploadedFile::fake()->create('rollback.jpg', 10, 'image/jpeg'),
                ])],
            ]);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $local->refresh();
            $this->assertSame('Local', $local->titulo);
            $this->assertSame($rutaAnterior, $local->ruta_storage);
            Storage::disk('public')->assertExists($rutaAnterior);
            $this->assertSame([$rutaAnterior], Storage::disk('public')->allFiles());
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public function test_rolls_back_complete_aggregate_and_all_new_files_when_audit_fails(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $funcional = $this->categoria('FUNCIONAL');
        $terapeutica = $this->categoria('TERAPEUTICA');
        $ejercicio = $this->ejercicio(true, [$anatomica, $funcional]);
        $local = $this->recursoLocalPersistido($ejercicio, 'original.jpg');
        $externo = $this->recursoExternoPersistido($ejercicio);
        $rutaAnterior = $local->ruta_storage;
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            $this->actualizar($ejercicio, [
                'nombre' => 'Cambio global revertido',
                'categorias' => [$anatomica->id, $terapeutica->id],
                'recursos' => [
                    $this->payloadRecurso($local, [
                        'titulo' => 'Local cambiado',
                        'archivo' => UploadedFile::fake()->create('reemplazo.jpg', 10, 'image/jpeg'),
                    ]),
                    $this->payloadRecurso($externo, [
                        'url_externa' => 'https://example.com/cambio',
                    ]),
                    $this->nuevoLocal(UploadedFile::fake()->create('adicional.jpg', 10, 'image/jpeg')),
                ],
            ]);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $ejercicio->refresh();
            $local->refresh();
            $externo->refresh();
            $this->assertSame('Puente de glúteos', $ejercicio->nombre);
            $this->assertEqualsCanonicalizing(
                [$anatomica->id, $funcional->id],
                $ejercicio->categorias()->pluck('categorias_clinicas.id')->all()
            );
            $this->assertSame('Local', $local->titulo);
            $this->assertSame($rutaAnterior, $local->ruta_storage);
            $this->assertSame('https://example.com/original', $externo->url_externa);
            $this->assertDatabaseCount('recursos_ejercicio', 2);
            Storage::disk('public')->assertExists($rutaAnterior);
            $this->assertSame([$rutaAnterior], Storage::disk('public')->allFiles());
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function actualizar(
        EjercicioDomiciliario $ejercicio,
        array $cambios = []
    ): EjercicioDomiciliario {
        return app(ActualizarEjercicioDomiciliarioAction::class)->ejecutar(
            $ejercicio,
            array_replace($this->datosActuales($ejercicio), $cambios)
        );
    }

    private function datosActuales(EjercicioDomiciliario $ejercicio): array
    {
        $datos = [];

        foreach ([
            'codigo',
            'nombre',
            'descripcion',
            'series_recomendadas',
            'repeticiones_recomendadas',
            'duracion_segundos_recomendada',
            'frecuencia_cantidad_base',
            'frecuencia_periodo_base',
            'descanso_segundos_base',
            'carga_resistencia_sugerida',
            'dificultad_base',
            'contraindicaciones',
            'notas_clinicas',
        ] as $campo) {
            $valor = $ejercicio->{$campo};
            $datos[$campo] = $valor instanceof \BackedEnum ? $valor->value : $valor;
        }

        $datos['categorias'] = $ejercicio->categorias()
            ->pluck('categorias_clinicas.id')
            ->all();

        return $datos;
    }

    private function categoria(string $tipo, bool $activo = true): CategoriaClinica
    {
        static $secuencia = 0;
        $secuencia++;

        return CategoriaClinica::create([
            'codigo' => 'CAT-'.$secuencia,
            'nombre' => 'Categoría '.$secuencia,
            'tipo' => $tipo,
            'activo' => $activo,
        ]);
    }

    /**
     * @param  list<CategoriaClinica>  $categorias
     */
    private function ejercicio(
        bool $activo,
        array $categorias,
        string $codigo = 'EJ-001'
    ): EjercicioDomiciliario {
        $ejercicio = EjercicioDomiciliario::create([
            'codigo' => $codigo,
            'nombre' => 'Puente de glúteos',
            'descripcion' => 'Elevación controlada de pelvis.',
            'series_recomendadas' => 3,
            'repeticiones_recomendadas' => 12,
            'duracion_segundos_recomendada' => 45,
            'frecuencia_cantidad_base' => 2,
            'frecuencia_periodo_base' => 'DIARIA',
            'descanso_segundos_base' => 30,
            'carga_resistencia_sugerida' => 'Banda liviana',
            'dificultad_base' => 'MEDIA',
            'contraindicaciones' => 'Dolor agudo',
            'notas_clinicas' => 'Control lumbar',
            'activo' => $activo,
        ]);
        $ejercicio->categorias()->sync(array_map(
            fn (CategoriaClinica $categoria): int => $categoria->id,
            $categorias
        ));

        return $ejercicio;
    }

    private function recursoLocalPersistido(
        EjercicioDomiciliario $ejercicio,
        string $nombre
    ): RecursoEjercicio {
        $ruta = UploadedFile::fake()->create($nombre, 10, 'image/jpeg')
            ->store('ejercicios/recursos', 'public');

        return $ejercicio->recursos()->create([
            'tipo' => 'IMAGEN',
            'origen' => 'LOCAL',
            'ruta_storage' => $ruta,
            'url_externa' => null,
            'titulo' => 'Local',
            'orden' => 1,
            'activo' => true,
        ]);
    }

    private function recursoExternoPersistido(
        EjercicioDomiciliario $ejercicio,
        string $url = 'https://example.com/original'
    ): RecursoEjercicio {
        return $ejercicio->recursos()->create([
            'tipo' => 'VIDEO',
            'origen' => 'EXTERNO',
            'ruta_storage' => null,
            'url_externa' => $url,
            'titulo' => 'Externo',
            'orden' => 2,
            'activo' => true,
        ]);
    }

    private function payloadRecurso(RecursoEjercicio $recurso, array $cambios = []): array
    {
        return array_replace([
            'id' => $recurso->id,
            'tipo' => $recurso->tipo->value,
            'origen' => $recurso->origen->value,
            'titulo' => $recurso->titulo,
            'orden' => $recurso->orden,
            'activo' => $recurso->activo,
            'url_externa' => $recurso->url_externa,
        ], $cambios);
    }

    private function nuevoLocal(UploadedFile $archivo): array
    {
        return [
            'tipo' => 'IMAGEN',
            'origen' => 'LOCAL',
            'titulo' => 'Nuevo local',
            'orden' => 10,
            'activo' => true,
            'archivo' => $archivo,
            'url_externa' => null,
        ];
    }

    private function nuevoExterno(string $url): array
    {
        return [
            'tipo' => 'VIDEO',
            'origen' => 'EXTERNO',
            'titulo' => 'Nuevo externo',
            'orden' => 11,
            'activo' => true,
            'url_externa' => $url,
        ];
    }

    private function assertCategoryUpdateRejected(
        EjercicioDomiciliario $ejercicio,
        array $categorias
    ): void {
        try {
            $this->actualizar($ejercicio, ['categorias' => $categorias]);
            $this->fail('Se esperaba el rechazo del conjunto de categorías.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('categorias', $exception->errors());
        }
    }
}
