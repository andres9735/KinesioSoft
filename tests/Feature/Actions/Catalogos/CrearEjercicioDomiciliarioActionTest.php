<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CrearEjercicioDomiciliarioAction;
use App\Models\Auditoria;
use App\Models\CategoriaClinica;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CrearEjercicioDomiciliarioActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_complete_active_aggregate_with_categories_resources_and_audit(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $funcional = $this->categoria('FUNCIONAL');
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $contenidoArchivo = 'CONTENIDO_BINARIO_CREACION_NO_AUDITAR';

        $ejercicio = app(CrearEjercicioDomiciliarioAction::class)->ejecutar(
            $this->datos([
                'activo' => false,
                'categorias' => [$funcional->id, $anatomica->id],
                'recursos' => [
                    $this->recursoLocal(UploadedFile::fake()->createWithContent(
                        'guia.jpg',
                        $contenidoArchivo
                    )),
                    $this->recursoExterno(),
                ],
            ])
        );

        $this->assertTrue($ejercicio->exists);
        $this->assertTrue($ejercicio->activo);
        $this->assertDatabaseHas('ejercicios_domiciliarios', [
            'id' => $ejercicio->id,
            'codigo' => 'EJ-001',
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
            'activo' => true,
        ]);
        $this->assertEqualsCanonicalizing(
            [$anatomica->id, $funcional->id],
            $ejercicio->categorias()->pluck('categorias_clinicas.id')->all()
        );
        $this->assertDatabaseCount('recursos_ejercicio', 2);

        $local = $ejercicio->recursos()->where('origen', 'LOCAL')->sole();
        $this->assertNull($local->url_externa);
        $this->assertStringStartsWith('ejercicios/recursos/', $local->ruta_storage);
        Storage::disk('public')->assertExists($local->ruta_storage);

        $externo = $ejercicio->recursos()->where('origen', 'EXTERNO')->sole();
        $this->assertNull($externo->ruta_storage);
        $this->assertSame('https://example.com/video', $externo->url_externa);
        $this->assertCount(1, Storage::disk('public')->allFiles('ejercicios/recursos'));

        $auditoria = Auditoria::query()->sole();
        $this->assertSame('ejercicio_domiciliario_creado', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertSame($ejercicio->id, $auditoria->auditable_id);
        $this->assertNull($auditoria->valores_anteriores);
        $this->assertTrue($auditoria->valores_nuevos['activo']);
        $this->assertEqualsCanonicalizing(
            [$anatomica->id, $funcional->id],
            $auditoria->valores_nuevos['categorias']
        );
        $this->assertCount(2, $auditoria->valores_nuevos['recursos']);

        $recursoLocalAuditado = collect($auditoria->valores_nuevos['recursos'])
            ->firstWhere('id', $local->id);
        $this->assertIsArray($recursoLocalAuditado);
        $this->assertSame([
            'id',
            'tipo',
            'origen',
            'ruta_storage',
            'url_externa',
            'titulo',
            'orden',
            'activo',
        ], array_keys($recursoLocalAuditado));
        $this->assertSame($local->ruta_storage, $recursoLocalAuditado['ruta_storage']);
        $this->assertDoesNotMatchRegularExpression(
            '/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/',
            $recursoLocalAuditado['ruta_storage']
        );

        $snapshotAuditado = $auditoria->valores_nuevos;
        array_walk_recursive(
            $snapshotAuditado,
            fn ($valor) => $this->assertNotInstanceOf(UploadedFile::class, $valor)
        );
        $payloadAuditado = json_encode($auditoria->valores_nuevos, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($contenidoArchivo, $payloadAuditado);
        $this->assertStringNotContainsString('base64,', strtolower($payloadAuditado));
        $this->assertStringNotContainsString(
            str_replace('\\', '/', sys_get_temp_dir()),
            str_replace('\\', '/', $payloadAuditado)
        );
    }

    public function test_direct_action_rejects_invalid_category_sets_without_partial_aggregate(): void
    {
        Storage::fake('public');
        $funcional = $this->categoria('FUNCIONAL');
        $anatomicaInactiva = $this->categoria('ANATOMICA', false);
        $equipamiento = $this->categoria('EQUIPAMIENTO');
        $anatomica = $this->categoria('ANATOMICA');
        $casos = [
            [$funcional->id],
            [$anatomicaInactiva->id],
            [$anatomica->id, $equipamiento->id],
            [$anatomica->id, 999999],
            [$anatomica->id, $anatomica->id],
        ];

        foreach ($casos as $indice => $categorias) {
            try {
                app(CrearEjercicioDomiciliarioAction::class)->ejecutar(
                    $this->datos([
                        'codigo' => 'INVALIDO-'.$indice,
                        'categorias' => $categorias,
                        'recursos' => [
                            $this->recursoLocal(UploadedFile::fake()->create('invalido.jpg', 10, 'image/jpeg')),
                        ],
                    ])
                );
                $this->fail('Se esperaba el rechazo de las categorías inválidas.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('categorias', $exception->errors());
            }
        }

        $this->assertDatabaseCount('ejercicios_domiciliarios', 0);
        $this->assertDatabaseCount('ejercicio_categoria', 0);
        $this->assertDatabaseCount('recursos_ejercicio', 0);
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_rolls_back_database_and_cleans_new_local_file_when_audit_fails(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(CrearEjercicioDomiciliarioAction::class)->ejecutar(
                $this->datos([
                    'categorias' => [$anatomica->id],
                    'recursos' => [
                        $this->recursoLocal(UploadedFile::fake()->create('rollback.jpg', 10, 'image/jpeg')),
                    ],
                ])
            );
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseCount('ejercicios_domiciliarios', 0);
            $this->assertDatabaseCount('ejercicio_categoria', 0);
            $this->assertDatabaseCount('recursos_ejercicio', 0);
            $this->assertDatabaseCount('auditorias', 0);
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    private function datos(array $cambios = []): array
    {
        return array_replace([
            'codigo' => 'EJ-001',
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
            'categorias' => [],
            'recursos' => [],
        ], $cambios);
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

    private function recursoLocal(UploadedFile $archivo): array
    {
        return [
            'tipo' => 'IMAGEN',
            'origen' => 'LOCAL',
            'titulo' => 'Guía local',
            'orden' => 1,
            'activo' => true,
            'archivo' => $archivo,
            'url_externa' => null,
        ];
    }

    private function recursoExterno(): array
    {
        return [
            'tipo' => 'VIDEO',
            'origen' => 'EXTERNO',
            'titulo' => 'Video externo',
            'orden' => 2,
            'activo' => true,
            'url_externa' => 'https://example.com/video',
        ];
    }
}
