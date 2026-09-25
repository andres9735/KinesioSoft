<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Auditoria;
use App\Models\CategoriaClinica;
use App\Models\EjercicioDomiciliario;
use App\Models\RecursoEjercicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EjercicioDomiciliarioControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    public function test_admin_and_superadmin_can_access_exercise_catalog(): void
    {
        foreach ([RoleEnum::ADMINISTRADOR, RoleEnum::SUPERADMIN] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('catalogos.ejercicios.index'))
                ->assertOk()
                ->assertViewIs('catalogos.ejercicios-domiciliarios.index');
        }
    }

    public function test_kinesiologo_and_patient_are_blocked_by_existing_middleware(): void
    {
        foreach ([RoleEnum::KINESIOLOGO, RoleEnum::PACIENTE] as $role) {
            $response = $this->actingAs($this->userWithRole($role))
                ->get(route('catalogos.ejercicios.index'));

            $this->assertTrue($response->isRedirect());
        }
    }

    public function test_exactly_eight_logical_routes_use_required_model_parameter(): void
    {
        $names = [
            'index', 'create', 'store', 'show', 'edit', 'update', 'inactivar', 'reactivar',
        ];

        foreach ($names as $name) {
            $route = Route::getRoutes()->getByName('catalogos.ejercicios.'.$name);
            $this->assertNotNull($route);
            $this->assertStringStartsWith('catalogos/ejercicios-domiciliarios', $route->uri());

            if (in_array($name, ['show', 'edit', 'update', 'inactivar', 'reactivar'], true)) {
                $this->assertContains('ejercicioDomiciliario', $route->parameterNames());
            }
        }

        $this->assertNull(Route::getRoutes()->getByName('catalogos.ejercicios.destroy'));
        $this->assertNull(Route::getRoutes()->getByName('catalogos.ejercicios.toggle'));
    }

    public function test_hub_and_catalog_navigation_enable_all_six_clinical_catalogs(): void
    {
        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.index'));

        $response->assertOk()->assertDontSee('Aún no disponible');

        foreach ([
            'catalogos.diagnosticos.index',
            'catalogos.pruebas.index',
            'catalogos.tecnicas.index',
            'catalogos.categorias.index',
            'catalogos.ejercicios.index',
            'catalogos.tipos-equipo.index',
        ] as $routeName) {
            $response->assertSee('href="'.route($routeName).'"', false);
        }
    }

    public function test_index_renders_empty_state_and_frozen_columns(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.ejercicios.index'))
            ->assertOk()
            ->assertSee('No hay ejercicios domiciliarios registrados')
            ->assertSee('hidden overflow-x-auto md:block')
            ->assertSee('md:hidden')
            ->assertSee('Limpiar filtros')
            ->assertSeeInOrder([
                'Código', 'Nombre', 'Dificultad', 'Categorías', 'Recursos', 'Estado', 'Acciones',
            ]);
    }

    public function test_index_searches_only_code_or_name_and_normalizes_state_filter(): void
    {
        $this->ejercicio('EJ-CODIGO', 'Movilidad', true, descripcion: 'Sin coincidencia');
        $this->ejercicio('OTRO', 'Nombre buscable', false, descripcion: 'Texto secreto');
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $porCodigo = $this->get(route('catalogos.ejercicios.index', ['q' => 'CODIGO']));
        $this->assertSame(['EJ-CODIGO'], $porCodigo->viewData('ejercicios')->pluck('codigo')->all());

        $porNombre = $this->get(route('catalogos.ejercicios.index', ['q' => 'buscable']));
        $this->assertSame(['OTRO'], $porNombre->viewData('ejercicios')->pluck('codigo')->all());

        $porDescripcion = $this->get(route('catalogos.ejercicios.index', ['q' => 'secreto']));
        $this->assertSame(0, $porDescripcion->viewData('ejercicios')->total());

        $this->assertSame(1, $this->get(route('catalogos.ejercicios.index', ['estado' => 'activos']))->viewData('ejercicios')->total());
        $this->assertSame(1, $this->get(route('catalogos.ejercicios.index', ['estado' => 'inactivos']))->viewData('ejercicios')->total());
        $this->assertSame(2, $this->get(route('catalogos.ejercicios.index', ['estado' => 'desconocido']))->viewData('ejercicios')->total());
    }

    public function test_index_exposes_category_total_resource_total_active_count_and_mobile_summary(): void
    {
        $anatomica = $this->categoria('ANAT', 'ANATOMICA');
        $funcional = $this->categoria('FUNC', 'FUNCIONAL');
        $ejercicio = $this->ejercicio('EJ-001', 'Puente', true, [$anatomica, $funcional]);
        $this->recursoExterno($ejercicio, true, 'Activo');
        $this->recursoExterno($ejercicio, false, 'Inactivo');

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.ejercicios.index'));
        $listado = $response->viewData('ejercicios')->first();

        $this->assertSame(2, $listado->categorias_count);
        $this->assertSame(2, $listado->recursos_count);
        $this->assertSame(1, $listado->recursos_activos_count);
        $response->assertSee('2 total · 1 activos')->assertSee('MEDIA · 2 cat. · 1 rec. activos');
    }

    public function test_index_paginates_ten_and_preserves_query_string(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->ejercicio('EJ-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'Ejercicio '.$i);
        }

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.ejercicios.index', ['q' => 'Ejercicio', 'estado' => 'activos']));
        $paginador = $response->viewData('ejercicios');

        $this->assertCount(10, $paginador->items());
        $this->assertSame(11, $paginador->total());
        $this->assertStringContainsString('q=Ejercicio', $paginador->url(2));
        $this->assertStringContainsString('estado=activos', $paginador->url(2));
    }

    public function test_create_offers_only_active_allowed_categories_and_complete_multipart_form(): void
    {
        $anatomica = $this->categoria('ANAT-A', 'ANATOMICA');
        $funcional = $this->categoria('FUNC-A', 'FUNCIONAL');
        $terapeutica = $this->categoria('TER-A', 'TERAPEUTICA');
        $anatomicaInactiva = $this->categoria('ANAT-I', 'ANATOMICA', false);
        $equipamiento = $this->categoria('EQ-A', 'EQUIPAMIENTO');

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.ejercicios.create'));

        $response->assertOk()
            ->assertSee($anatomica->codigo)
            ->assertSee($funcional->codigo)
            ->assertSee($terapeutica->codigo)
            ->assertDontSee($anatomicaInactiva->codigo)
            ->assertDontSee($equipamiento->codigo)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSeeInOrder([
                '1. Datos generales',
                '2. Parámetros recomendados',
                '3. Clasificación clínica',
                '4. Contraindicaciones / notas',
                '5. Recursos multimedia',
            ])
            ->assertDontSee('name="activo"', false)
            ->assertDontSee('grupo_muscular_zona')
            ->assertDontSee('[ruta_storage]');
    }

    public function test_store_persists_complete_aggregate_with_local_external_and_audit(): void
    {
        $anatomica = $this->categoria('ANAT-A', 'ANATOMICA');
        $funcional = $this->categoria('FUNC-A', 'FUNCIONAL');

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->post(route('catalogos.ejercicios.store'), $this->payload([
                'categorias' => [$anatomica->id, $funcional->id],
                'recursos' => [
                    $this->nuevoRecursoLocal(
                        UploadedFile::fake()->create('guia.pdf', 20, 'application/pdf'),
                        0
                    ),
                    $this->nuevoRecursoExterno('https://example.com/video', 1),
                ],
            ]));

        $response->assertRedirect(route('catalogos.ejercicios.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
        $this->assertDatabaseHas('ejercicios_domiciliarios', [
            'codigo' => 'EJ-HTTP', 'nombre' => 'Ejercicio HTTP', 'activo' => true,
        ]);
        $ejercicio = EjercicioDomiciliario::query()->where('codigo', 'EJ-HTTP')->sole();
        $this->assertTrue($ejercicio->activo);
        $this->assertEqualsCanonicalizing(
            [$anatomica->id, $funcional->id],
            $ejercicio->categorias()->pluck('categorias_clinicas.id')->all()
        );
        $local = $ejercicio->recursos()->where('origen', 'LOCAL')->sole();
        $this->assertNull($local->url_externa);
        $this->assertStringStartsWith('ejercicios/recursos/', $local->ruta_storage);
        $this->assertFalse(str_starts_with($local->ruta_storage, '/'));
        Storage::disk('public')->assertExists($local->ruta_storage);
        $this->assertDatabaseHas('recursos_ejercicio', [
            'ejercicio_id' => $ejercicio->id,
            'origen' => 'EXTERNO',
            'ruta_storage' => null,
            'url_externa' => 'https://example.com/video',
        ]);
        $this->assertCount(1, Storage::disk('public')->allFiles('ejercicios/recursos'));
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('ejercicio_domiciliario_creado', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($ejercicio->id, $auditoria->auditable_id);
        $this->assertCount(2, $auditoria->valores_nuevos['recursos']);
    }

    public function test_store_validation_returns_errors_and_preserves_textual_old_input(): void
    {
        $anatomica = $this->categoria('ANAT-A', 'ANATOMICA');
        $payload = $this->payload([
            'descripcion' => null,
            'categorias' => [$anatomica->id],
            'recursos' => [[
                'tipo' => 'IMAGEN', 'origen' => 'LOCAL', 'titulo' => 'Archivo pendiente',
                'orden' => 0, 'activo' => true,
            ]],
        ]);

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.ejercicios.create'))
            ->post(route('catalogos.ejercicios.store'), $payload)
            ->assertRedirect(route('catalogos.ejercicios.create'))
            ->assertSessionHasErrors(['descripcion', 'recursos.0.archivo'])
            ->assertSessionHasInput('categorias', [$anatomica->id])
            ->assertSessionHasInput('recursos.0.titulo', 'Archivo pendiente');

        $this->assertDatabaseCount('ejercicios_domiciliarios', 0);
    }

    public function test_show_is_full_readonly_and_displays_historical_relations_without_audit(): void
    {
        $categoria = $this->categoria('ANAT-H', 'ANATOMICA', false);
        $ejercicio = $this->ejercicio('EJ-SHOW', 'Ejercicio detalle', false, [$categoria]);
        $activo = $this->recursoExterno($ejercicio, true, 'Video activo');
        $inactivo = $this->recursoLocal($ejercicio, false, 'guia-historica.pdf', 'Guía inactiva');

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.ejercicios.show', $ejercicio));

        $response->assertOk()
            ->assertViewIs('catalogos.ejercicios-domiciliarios.show')
            ->assertSee($ejercicio->codigo)
            ->assertSee($ejercicio->descripcion)
            ->assertSee($categoria->codigo)
            ->assertSee('Inactivo')
            ->assertSee($activo->titulo)
            ->assertSee($activo->url_externa)
            ->assertSee($inactivo->titulo)
            ->assertSee('guia-historica.pdf')
            ->assertDontSee('name="codigo"', false)
            ->assertDontSee('name="recursos', false);

        $this->get(route('catalogos.ejercicios.index'))->assertOk();
        $this->get(route('catalogos.ejercicios.create'))->assertOk();
        $this->get(route('catalogos.ejercicios.edit', $ejercicio))->assertOk();

        $this->assertFalse($ejercicio->fresh()->activo);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_edit_keeps_historical_categories_and_all_persisted_resources_visible(): void
    {
        $historicaInactiva = $this->categoria('ANAT-H', 'ANATOMICA', false);
        $historicaEquipamiento = $this->categoria('EQ-H', 'EQUIPAMIENTO', false);
        $nuevaFuncional = $this->categoria('FUNC-A', 'FUNCIONAL');
        $equipamientoAjeno = $this->categoria('EQ-AJENO', 'EQUIPAMIENTO');
        $ejercicio = $this->ejercicio(
            'EJ-EDIT',
            'Ejercicio histórico',
            false,
            [$historicaInactiva, $historicaEquipamiento]
        );
        $local = $this->recursoLocal($ejercicio, true, 'actual.pdf', 'Local actual');
        $externoInactivo = $this->recursoExterno($ejercicio, false, 'Externo inactivo');

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.ejercicios.edit', $ejercicio));

        $response->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee($historicaInactiva->codigo)
            ->assertSee($historicaEquipamiento->codigo)
            ->assertSee('Histórica / no disponible para nuevas asociaciones')
            ->assertSee($nuevaFuncional->codigo)
            ->assertDontSee($equipamientoAjeno->codigo)
            ->assertSee($local->titulo)
            ->assertSee($externoInactivo->titulo)
            ->assertSee('Si no seleccionás otro archivo, se conservará el actual.')
            ->assertSee('deben seleccionarse nuevamente después de un error de validación')
            ->assertDontSee('[ruta_storage]')
            ->assertDontSee('grupo_muscular_zona');
    }

    public function test_update_changes_scalars_categories_and_resource_but_preserves_state_and_omitted_resource(): void
    {
        $anatomica = $this->categoria('ANAT-A', 'ANATOMICA');
        $funcional = $this->categoria('FUNC-A', 'FUNCIONAL');
        $ejercicio = $this->ejercicio('EJ-UP', 'Antes', true, [$anatomica]);
        $editado = $this->recursoExterno($ejercicio, true, 'Editar');
        $omitido = $this->recursoLocal($ejercicio, false, 'omitido.pdf', 'Omitido');
        $rutaOmitida = $omitido->ruta_storage;

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.ejercicios.update', $ejercicio), $this->payload([
                'codigo' => $ejercicio->codigo,
                'nombre' => 'Después',
                'categorias' => [$anatomica->id, $funcional->id],
                'recursos' => [[
                    'id' => $editado->id,
                    'tipo' => 'VIDEO',
                    'origen' => 'EXTERNO',
                    'titulo' => 'Editado',
                    'orden' => 0,
                    'activo' => false,
                    'url_externa' => 'https://example.com/editado',
                ]],
            ]));

        $response->assertRedirect(route('catalogos.ejercicios.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
        $this->assertDatabaseHas('ejercicios_domiciliarios', [
            'id' => $ejercicio->id, 'nombre' => 'Después', 'activo' => true,
        ]);
        $this->assertEqualsCanonicalizing(
            [$anatomica->id, $funcional->id],
            $ejercicio->categorias()->pluck('categorias_clinicas.id')->all()
        );
        $this->assertDatabaseHas('recursos_ejercicio', [
            'id' => $editado->id, 'titulo' => 'Editado', 'activo' => false,
        ]);
        $this->assertDatabaseHas('recursos_ejercicio', [
            'id' => $omitido->id,
            'titulo' => 'Omitido',
            'ruta_storage' => $rutaOmitida,
            'activo' => false,
        ]);
        $this->assertDatabaseCount('recursos_ejercicio', 2);
        Storage::disk('public')->assertExists($rutaOmitida);
        $this->assertDatabaseHas('auditorias', ['evento' => 'ejercicio_domiciliario_actualizado']);

        $this->put(
            route('catalogos.ejercicios.update', $ejercicio),
            $this->payloadActual($ejercicio->fresh(), ['recursos' => []])
        )->assertRedirect(route('catalogos.ejercicios.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('recursos_ejercicio', 2);
        $this->assertDatabaseHas('recursos_ejercicio', [
            'id' => $omitido->id,
            'ruta_storage' => $rutaOmitida,
        ]);
        Storage::disk('public')->assertExists($rutaOmitida);
        $this->assertSame(
            1,
            Auditoria::query()->where('evento', 'ejercicio_domiciliario_actualizado')->count()
        );
    }

    public function test_update_replaces_local_file_through_http_and_audits(): void
    {
        $anatomica = $this->categoria('ANAT-A', 'ANATOMICA');
        $ejercicio = $this->ejercicio('EJ-REPLACE', 'Reemplazo', true, [$anatomica]);
        $local = $this->recursoLocal($ejercicio, true, 'anterior.pdf', 'Guía anterior');
        $rutaAnterior = $local->ruta_storage;

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.ejercicios.update', $ejercicio), $this->payloadActual($ejercicio, [
                'recursos' => [$this->payloadRecurso($local, [
                    'titulo' => 'Guía reemplazada',
                    'archivo' => UploadedFile::fake()->create('nueva.pdf', 25, 'application/pdf'),
                ])],
            ]));

        $response->assertRedirect(route('catalogos.ejercicios.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
        $local->refresh();
        $this->assertSame('LOCAL', $local->origen->value);
        $this->assertNull($local->url_externa);
        $this->assertNotSame($rutaAnterior, $local->ruta_storage);
        Storage::disk('public')->assertExists($local->ruta_storage);
        Storage::disk('public')->assertMissing($rutaAnterior);
        $this->assertDatabaseHas('auditorias', ['evento' => 'ejercicio_domiciliario_actualizado']);
    }

    public function test_update_transitions_local_external_and_external_local_through_http(): void
    {
        $anatomica = $this->categoria('ANAT-A', 'ANATOMICA');
        $localEjercicio = $this->ejercicio('EJ-L2E', 'Local a externo', true, [$anatomica]);
        $local = $this->recursoLocal($localEjercicio, true, 'local.pdf', 'Local');
        $rutaAnterior = $local->ruta_storage;
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->put(route('catalogos.ejercicios.update', $localEjercicio), $this->payloadActual($localEjercicio, [
            'recursos' => [$this->payloadRecurso($local, [
                'origen' => 'EXTERNO',
                'url_externa' => 'https://example.com/convertido',
            ])],
        ]))->assertRedirect(route('catalogos.ejercicios.index'))->assertSessionHasNoErrors();

        $local->refresh();
        $this->assertSame('EXTERNO', $local->origen->value);
        $this->assertNull($local->ruta_storage);
        $this->assertSame('https://example.com/convertido', $local->url_externa);
        Storage::disk('public')->assertMissing($rutaAnterior);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $externoEjercicio = $this->ejercicio('EJ-E2L', 'Externo a local', true, [$anatomica]);
        $externo = $this->recursoExterno($externoEjercicio, true, 'Externo');
        $this->put(route('catalogos.ejercicios.update', $externoEjercicio), $this->payloadActual($externoEjercicio, [
            'recursos' => [$this->payloadRecurso($externo, [
                'origen' => 'LOCAL',
                'url_externa' => null,
                'archivo' => UploadedFile::fake()->create('convertido.pdf', 25, 'application/pdf'),
            ])],
        ]))->assertRedirect(route('catalogos.ejercicios.index'))->assertSessionHasNoErrors();

        $externo->refresh();
        $this->assertSame('LOCAL', $externo->origen->value);
        $this->assertNull($externo->url_externa);
        $this->assertNotNull($externo->ruta_storage);
        Storage::disk('public')->assertExists($externo->ruta_storage);
        $this->assertSame(2, Auditoria::query()->where('evento', 'ejercicio_domiciliario_actualizado')->count());
    }

    public function test_update_reorders_existing_resources_without_recreating_or_deleting_them(): void
    {
        $anatomica = $this->categoria('ANAT-A', 'ANATOMICA');
        $ejercicio = $this->ejercicio('EJ-ORDER', 'Orden', true, [$anatomica]);
        $primero = $this->recursoLocal($ejercicio, true, 'primero.pdf', 'Primero', 0);
        $segundo = $this->recursoExterno($ejercicio, true, 'Segundo', 1);
        $tercero = $this->recursoExterno($ejercicio, true, 'Tercero', 2);
        $rutaPrimero = $primero->ruta_storage;
        $ids = [$primero->id, $segundo->id, $tercero->id];

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.ejercicios.update', $ejercicio), $this->payloadActual($ejercicio, [
                'recursos' => [
                    $this->payloadRecurso($tercero, ['orden' => 0]),
                    $this->payloadRecurso($primero, ['orden' => 1]),
                    $this->payloadRecurso($segundo, ['orden' => 2]),
                ],
            ]))->assertRedirect(route('catalogos.ejercicios.index'))->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing($ids, $ejercicio->recursos()->pluck('id')->all());
        $this->assertDatabaseCount('recursos_ejercicio', 3);
        $this->assertSame(0, $tercero->fresh()->orden);
        $this->assertSame(1, $primero->fresh()->orden);
        $this->assertSame(2, $segundo->fresh()->orden);
        $this->assertSame($rutaPrimero, $primero->fresh()->ruta_storage);
        Storage::disk('public')->assertExists($rutaPrimero);
        $this->assertDatabaseHas('auditorias', ['evento' => 'ejercicio_domiciliario_actualizado']);
    }

    public function test_update_inactivates_and_reactivates_local_resource_without_reupload_or_file_deletion(): void
    {
        $anatomica = $this->categoria('ANAT-A', 'ANATOMICA');
        $ejercicio = $this->ejercicio('EJ-RESOURCE-STATE', 'Estado recurso', true, [$anatomica]);
        $local = $this->recursoLocal($ejercicio, true, 'estable.pdf', 'Estable');
        $ruta = $local->ruta_storage;
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->put(route('catalogos.ejercicios.update', $ejercicio), $this->payloadActual($ejercicio, [
            'recursos' => [$this->payloadRecurso($local, ['activo' => false])],
        ]))->assertRedirect(route('catalogos.ejercicios.index'))->assertSessionHasNoErrors();

        $this->assertFalse($local->fresh()->activo);
        $this->assertSame($ruta, $local->fresh()->ruta_storage);
        Storage::disk('public')->assertExists($ruta);
        $this->assertCount(1, Storage::disk('public')->allFiles());

        $this->put(route('catalogos.ejercicios.update', $ejercicio), $this->payloadActual($ejercicio->fresh(), [
            'recursos' => [$this->payloadRecurso($local->fresh(), ['activo' => true])],
        ]))->assertRedirect(route('catalogos.ejercicios.index'))->assertSessionHasNoErrors();

        $this->assertTrue($local->fresh()->activo);
        $this->assertSame($ruta, $local->fresh()->ruta_storage);
        Storage::disk('public')->assertExists($ruta);
        $this->assertCount(1, Storage::disk('public')->allFiles());
        $this->assertSame(2, Auditoria::query()->where('evento', 'ejercicio_domiciliario_actualizado')->count());
    }

    public function test_active_exercise_keeps_inactive_historical_category_visible_selected_and_editable(): void
    {
        $anatomica = $this->categoria('ANAT-A', 'ANATOMICA');
        $funcional = $this->categoria('FUNC-H', 'FUNCIONAL');
        $ejercicio = $this->ejercicio('EJ-HIST', 'Histórico activo', true, [$anatomica, $funcional]);
        $funcional->update(['activo' => false]);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->get(route('catalogos.ejercicios.edit', $ejercicio))
            ->assertOk()
            ->assertSee($funcional->codigo)
            ->assertSee('Histórica / no disponible para nuevas asociaciones')
            ->assertSee('value="'.$funcional->id.'" checked', false);

        $this->put(route('catalogos.ejercicios.update', $ejercicio), $this->payloadActual($ejercicio, [
            'nombre' => 'Histórico activo editado',
            'categorias' => [$anatomica->id, $funcional->id],
        ]))->assertRedirect(route('catalogos.ejercicios.index'))->assertSessionHasNoErrors();

        $ejercicio->refresh();
        $this->assertTrue($ejercicio->activo);
        $this->assertSame('Histórico activo editado', $ejercicio->nombre);
        $this->assertEqualsCanonicalizing(
            [$anatomica->id, $funcional->id],
            $ejercicio->categorias()->pluck('categorias_clinicas.id')->all()
        );
    }

    public function test_inactive_exercise_keeps_historical_equipment_visible_and_editable_only_as_existing_association(): void
    {
        $historica = $this->categoria('FUNC-H', 'FUNCIONAL');
        $equipamientoAjeno = $this->categoria('EQ-AJENO', 'EQUIPAMIENTO');
        $ejercicio = $this->ejercicio('EJ-EQ-HIST', 'Histórico equipamiento', false, [$historica]);
        $historica->update(['tipo' => 'EQUIPAMIENTO', 'activo' => false]);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->get(route('catalogos.ejercicios.edit', $ejercicio))
            ->assertOk()
            ->assertSee($historica->codigo)
            ->assertSee('Histórica / no disponible para nuevas asociaciones')
            ->assertSee('value="'.$historica->id.'" checked', false)
            ->assertDontSee($equipamientoAjeno->codigo);

        $this->put(route('catalogos.ejercicios.update', $ejercicio), $this->payloadActual($ejercicio, [
            'nombre' => 'Histórico equipamiento editado',
            'categorias' => [$historica->id],
        ]))->assertRedirect(route('catalogos.ejercicios.index'))->assertSessionHasNoErrors();

        $ejercicio->refresh();
        $this->assertFalse($ejercicio->activo);
        $this->assertSame('Histórico equipamiento editado', $ejercicio->nombre);
        $this->assertSame([$historica->id], $ejercicio->categorias()->pluck('categorias_clinicas.id')->all());
    }

    public function test_inactivate_and_reactivate_use_explicit_state_actions_and_preserve_relations(): void
    {
        $anatomica = $this->categoria('ANAT-A', 'ANATOMICA');
        $funcionalHistorica = $this->categoria('FUNC-H', 'FUNCIONAL');
        $ejercicio = $this->ejercicio('EJ-STATE', 'Estado', true, [$anatomica, $funcionalHistorica]);
        $funcionalHistorica->update(['activo' => false]);
        $recurso = $this->recursoLocal($ejercicio, false, 'estado.pdf', 'Conservado');
        $ruta = $recurso->ruta_storage;
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->patch(route('catalogos.ejercicios.inactivar', $ejercicio))
            ->assertRedirect(route('catalogos.ejercicios.index'))
            ->assertSessionHas('success');
        $this->assertFalse($ejercicio->fresh()->activo);
        $this->assertDatabaseHas('ejercicio_categoria', [
            'ejercicio_id' => $ejercicio->id, 'categoria_id' => $anatomica->id,
        ]);
        $this->assertDatabaseHas('ejercicio_categoria', [
            'ejercicio_id' => $ejercicio->id, 'categoria_id' => $funcionalHistorica->id,
        ]);
        $this->assertDatabaseHas('recursos_ejercicio', ['id' => $recurso->id]);
        Storage::disk('public')->assertExists($ruta);

        $this->patch(route('catalogos.ejercicios.reactivar', $ejercicio))
            ->assertRedirect(route('catalogos.ejercicios.index'))
            ->assertSessionHas('success');
        $this->assertTrue($ejercicio->fresh()->activo);
        $this->assertEqualsCanonicalizing(
            [$anatomica->id, $funcionalHistorica->id],
            $ejercicio->categorias()->pluck('categorias_clinicas.id')->all()
        );
        $this->assertFalse($recurso->fresh()->activo);
        $this->assertSame($ruta, $recurso->fresh()->ruta_storage);
        Storage::disk('public')->assertExists($ruta);
        $this->assertDatabaseHas('auditorias', ['evento' => 'ejercicio_domiciliario_inactivado']);
        $this->assertDatabaseHas('auditorias', ['evento' => 'ejercicio_domiciliario_reactivado']);
    }

    public function test_reactivate_is_blocked_without_active_anatomical_category(): void
    {
        $funcional = $this->categoria('FUNC-A', 'FUNCIONAL');
        $ejercicio = $this->ejercicio('EJ-BLOCK', 'Bloqueado', false, [$funcional]);
        $recurso = $this->recursoLocal($ejercicio, true, 'bloqueado.pdf', 'Bloqueado');
        $ruta = $recurso->ruta_storage;

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.ejercicios.index'))
            ->patch(route('catalogos.ejercicios.reactivar', $ejercicio))
            ->assertRedirect(route('catalogos.ejercicios.index'))
            ->assertSessionHasErrors('categorias');

        $this->assertFalse($ejercicio->fresh()->activo);
        $this->assertSame([$funcional->id], $ejercicio->categorias()->pluck('categorias_clinicas.id')->all());
        $this->assertDatabaseHas('recursos_ejercicio', ['id' => $recurso->id, 'ruta_storage' => $ruta]);
        Storage::disk('public')->assertExists($ruta);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_reactivate_is_blocked_with_historical_equipment_category(): void
    {
        $anatomica = $this->categoria('ANAT-A', 'ANATOMICA');
        $equipamiento = $this->categoria('EQ-H', 'EQUIPAMIENTO', false);
        $ejercicio = $this->ejercicio('EJ-EQ', 'Con equipamiento', false, [$anatomica, $equipamiento]);
        $recurso = $this->recursoLocal($ejercicio, false, 'equipamiento.pdf', 'Equipamiento');
        $ruta = $recurso->ruta_storage;

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.ejercicios.index'))
            ->patch(route('catalogos.ejercicios.reactivar', $ejercicio))
            ->assertRedirect(route('catalogos.ejercicios.index'))
            ->assertSessionHasErrors('categorias');

        $this->assertFalse($ejercicio->fresh()->activo);
        $this->assertEqualsCanonicalizing(
            [$anatomica->id, $equipamiento->id],
            $ejercicio->categorias()->pluck('categorias_clinicas.id')->all()
        );
        $this->assertDatabaseHas('recursos_ejercicio', ['id' => $recurso->id, 'ruta_storage' => $ruta]);
        Storage::disk('public')->assertExists($ruta);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_update_rolls_back_complete_aggregate_and_files_when_audit_fails_through_http(): void
    {
        $anatomica = $this->categoria('ANAT-A', 'ANATOMICA');
        $funcional = $this->categoria('FUNC-A', 'FUNCIONAL');
        $terapeutica = $this->categoria('TER-A', 'TERAPEUTICA');
        $ejercicio = $this->ejercicio('EJ-ROLLBACK-UP', 'Nombre original', true, [$anatomica, $funcional]);
        $primero = $this->recursoLocal($ejercicio, true, 'old-1.pdf', 'Local original', 0);
        $segundo = $this->recursoLocal($ejercicio, true, 'old-2.pdf', 'Segundo original', 1);
        $rutaPrimero = $primero->ruta_storage;
        $rutaSegundo = $segundo->ruta_storage;
        $eventoAuditoria = 'eloquent.creating: '.Auditoria::class;
        Event::listen($eventoAuditoria, static function (): never {
            throw new RuntimeException('Fallo de auditoría forzado por la prueba integrada.');
        });
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        try {
            $this->withoutExceptionHandling()->put(
                route('catalogos.ejercicios.update', $ejercicio),
                $this->payloadActual($ejercicio, [
                    'nombre' => 'Nombre que debe revertirse',
                    'categorias' => [$anatomica->id, $terapeutica->id],
                    'recursos' => [
                        $this->payloadRecurso($primero, [
                            'titulo' => 'Local reemplazado',
                            'archivo' => UploadedFile::fake()->create('new-1.pdf', 20, 'application/pdf'),
                        ]),
                        $this->payloadRecurso($segundo, [
                            'titulo' => 'Metadata que debe revertirse',
                        ]),
                        $this->nuevoRecursoLocal(
                            UploadedFile::fake()->create('new-2.pdf', 20, 'application/pdf'),
                            2
                        ),
                    ],
                ])
            );
            $this->fail('Se esperaba el fallo forzado de AuditoriaService.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Fallo de auditoría forzado por la prueba integrada.',
                $exception->getMessage()
            );
        } finally {
            Event::forget($eventoAuditoria);
        }

        $ejercicio->refresh();
        $primero->refresh();
        $segundo->refresh();
        $this->assertSame('Nombre original', $ejercicio->nombre);
        $this->assertEqualsCanonicalizing(
            [$anatomica->id, $funcional->id],
            $ejercicio->categorias()->pluck('categorias_clinicas.id')->all()
        );
        $this->assertSame('Local original', $primero->titulo);
        $this->assertSame($rutaPrimero, $primero->ruta_storage);
        $this->assertSame('Segundo original', $segundo->titulo);
        $this->assertSame($rutaSegundo, $segundo->ruta_storage);
        $this->assertDatabaseCount('recursos_ejercicio', 2);
        $this->assertDatabaseCount('auditorias', 0);
        Storage::disk('public')->assertExists($rutaPrimero);
        Storage::disk('public')->assertExists($rutaSegundo);
        $this->assertEqualsCanonicalizing(
            [$rutaPrimero, $rutaSegundo],
            Storage::disk('public')->allFiles('ejercicios/recursos')
        );
    }

    public function test_store_rolls_back_database_and_new_file_when_audit_fails_through_http(): void
    {
        $anatomica = $this->categoria('ANAT-A', 'ANATOMICA');
        $eventoAuditoria = 'eloquent.creating: '.Auditoria::class;
        Event::listen($eventoAuditoria, static function (): never {
            throw new RuntimeException('Fallo de auditoría forzado en alta.');
        });
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        try {
            $this->withoutExceptionHandling()->post(
                route('catalogos.ejercicios.store'),
                $this->payload([
                    'codigo' => 'EJ-ROLLBACK-CREATE',
                    'categorias' => [$anatomica->id],
                    'recursos' => [$this->nuevoRecursoLocal(
                        UploadedFile::fake()->create('create-rollback.pdf', 20, 'application/pdf'),
                        0
                    )],
                ])
            );
            $this->fail('Se esperaba el fallo forzado de AuditoriaService.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría forzado en alta.', $exception->getMessage());
        } finally {
            Event::forget($eventoAuditoria);
        }

        $this->assertDatabaseCount('ejercicios_domiciliarios', 0);
        $this->assertDatabaseCount('ejercicio_categoria', 0);
        $this->assertDatabaseCount('recursos_ejercicio', 0);
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create(['activo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    /**
     * @param  array<int, CategoriaClinica>  $categorias
     */
    private function ejercicio(
        string $codigo,
        string $nombre,
        bool $activo = true,
        array $categorias = [],
        string $descripcion = 'Descripción del ejercicio.'
    ): EjercicioDomiciliario {
        $ejercicio = EjercicioDomiciliario::create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'series_recomendadas' => 3,
            'repeticiones_recomendadas' => 10,
            'duracion_segundos_recomendada' => 40,
            'frecuencia_cantidad_base' => 2,
            'frecuencia_periodo_base' => 'DIARIA',
            'descanso_segundos_base' => 20,
            'carga_resistencia_sugerida' => 'Banda liviana',
            'dificultad_base' => 'MEDIA',
            'contraindicaciones' => 'Dolor agudo',
            'notas_clinicas' => 'Control postural',
            'activo' => $activo,
        ]);
        $ejercicio->categorias()->attach(collect($categorias)->pluck('id'));

        return $ejercicio;
    }

    private function categoria(string $codigo, string $tipo, bool $activo = true): CategoriaClinica
    {
        return CategoriaClinica::create([
            'codigo' => $codigo,
            'nombre' => 'Categoría '.$codigo,
            'tipo' => $tipo,
            'activo' => $activo,
        ]);
    }

    private function recursoExterno(
        EjercicioDomiciliario $ejercicio,
        bool $activo,
        string $titulo,
        ?int $orden = null
    ): RecursoEjercicio {
        static $secuencia = 0;
        $secuencia++;

        return $ejercicio->recursos()->create([
            'tipo' => 'VIDEO',
            'origen' => 'EXTERNO',
            'ruta_storage' => null,
            'url_externa' => 'https://example.com/recurso-'.$secuencia,
            'titulo' => $titulo,
            'orden' => $orden ?? $secuencia,
            'activo' => $activo,
        ]);
    }

    private function recursoLocal(
        EjercicioDomiciliario $ejercicio,
        bool $activo,
        string $ruta,
        string $titulo,
        int $orden = 99
    ): RecursoEjercicio {
        $rutaStorage = 'ejercicios/recursos/'.$ruta;
        Storage::disk('public')->put($rutaStorage, 'contenido de prueba');

        return $ejercicio->recursos()->create([
            'tipo' => 'DOCUMENTO',
            'origen' => 'LOCAL',
            'ruta_storage' => $rutaStorage,
            'url_externa' => null,
            'titulo' => $titulo,
            'orden' => $orden,
            'activo' => $activo,
        ]);
    }

    private function payloadActual(
        EjercicioDomiciliario $ejercicio,
        array $cambios = []
    ): array {
        $ejercicio->refresh();
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
        $datos['recursos'] = [];

        return array_replace($datos, $cambios);
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

    private function nuevoRecursoLocal(UploadedFile $archivo, int $orden): array
    {
        return [
            'tipo' => 'DOCUMENTO',
            'origen' => 'LOCAL',
            'titulo' => 'Recurso local',
            'orden' => $orden,
            'activo' => true,
            'archivo' => $archivo,
            'url_externa' => null,
        ];
    }

    private function nuevoRecursoExterno(string $url, int $orden): array
    {
        return [
            'tipo' => 'VIDEO',
            'origen' => 'EXTERNO',
            'titulo' => 'Recurso externo',
            'orden' => $orden,
            'activo' => true,
            'url_externa' => $url,
        ];
    }

    private function payload(array $cambios = []): array
    {
        return array_replace([
            'codigo' => 'EJ-HTTP',
            'nombre' => 'Ejercicio HTTP',
            'descripcion' => 'Descripción enviada por HTTP.',
            'series_recomendadas' => 3,
            'repeticiones_recomendadas' => 12,
            'duracion_segundos_recomendada' => 45,
            'frecuencia_cantidad_base' => 2,
            'frecuencia_periodo_base' => 'DIARIA',
            'descanso_segundos_base' => 30,
            'carga_resistencia_sugerida' => 'Banda media',
            'dificultad_base' => 'MEDIA',
            'contraindicaciones' => 'Dolor agudo',
            'notas_clinicas' => 'Supervisar técnica',
            'categorias' => [],
            'recursos' => [],
        ], $cambios);
    }
}
