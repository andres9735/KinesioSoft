<?php

namespace Tests\Feature\Requests;

use App\Enums\DificultadEjercicioEnum;
use App\Enums\OrigenRecursoEjercicioEnum;
use App\Enums\TipoCategoriaClinicaEnum;
use App\Enums\TipoRecursoEjercicioEnum;
use App\Http\Requests\StoreEjercicioDomiciliarioRequest;
use App\Http\Requests\UpdateEjercicioDomiciliarioRequest;
use App\Models\CategoriaClinica;
use App\Models\EjercicioDomiciliario;
use App\Models\RecursoEjercicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EjercicioDomiciliarioRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post(
            '/_tests/requests/ejercicios-domiciliarios',
            fn (StoreEjercicioDomiciliarioRequest $request) => response()->json([
                'codigo' => $request->validated('codigo'),
            ])
        )->name('tests.requests.ejercicios.store');

        Route::middleware('web')->put(
            '/_tests/requests/ejercicios-domiciliarios/{ejercicioDomiciliario}',
            fn (
                UpdateEjercicioDomiciliarioRequest $request,
                EjercicioDomiciliario $ejercicioDomiciliario
            ) => response()->json([
                'ejercicio_id' => $ejercicioDomiciliario->id,
                'codigo' => $request->validated('codigo'),
            ])
        )->name('tests.requests.ejercicios.update');
    }

    public function test_store_accepts_minimum_payload_and_nullable_optional_base_fields(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);

        $this->store($this->storePayload($anatomica, [
            'series_recomendadas' => null,
            'repeticiones_recomendadas' => null,
            'duracion_segundos_recomendada' => null,
            'frecuencia_cantidad_base' => null,
            'frecuencia_periodo_base' => null,
            'descanso_segundos_base' => null,
            'carga_resistencia_sugerida' => null,
            'contraindicaciones' => null,
            'notas_clinicas' => null,
        ]))->assertOk()->assertJsonPath('codigo', 'EJ-001');
    }

    public function test_store_requires_a_non_null_string_description(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);

        $this->store($this->storePayload($anatomica, [
            'descripcion' => 'Descripción válida sin límite artificial de varchar.',
        ]))->assertOk();

        $sinDescripcion = $this->storePayload($anatomica);
        unset($sinDescripcion['descripcion']);

        $this->store($sinDescripcion)->assertSessionHasErrors('descripcion');
        $this->store($this->storePayload($anatomica, ['descripcion' => null]))
            ->assertSessionHasErrors('descripcion');
    }

    public function test_store_requires_code_name_and_difficulty_and_validates_difficulty_enum(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);

        foreach (['codigo', 'nombre', 'dificultad_base'] as $campo) {
            $payload = $this->storePayload($anatomica);
            unset($payload[$campo]);

            $this->store($payload)->assertSessionHasErrors($campo);
        }

        $this->store($this->storePayload($anatomica, ['dificultad_base' => 'EXTREMA']))
            ->assertSessionHasErrors('dificultad_base');

        foreach (DificultadEjercicioEnum::cases() as $dificultad) {
            $this->store($this->storePayload($anatomica, [
                'codigo' => 'EJ-'.$dificultad->value,
                'dificultad_base' => $dificultad->value,
            ]))->assertOk();
        }
    }

    public function test_store_rejects_duplicate_code_and_prohibited_main_fields(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $this->ejercicio('EJ-001', true, [$anatomica]);

        $this->store($this->storePayload($anatomica))
            ->assertSessionHasErrors('codigo');
        $this->store($this->storePayload($anatomica, ['codigo' => 'EJ-002', 'activo' => false]))
            ->assertSessionHasErrors('activo');
        $this->store($this->storePayload($anatomica, [
            'codigo' => 'EJ-003',
            'grupo_muscular_zona' => 'Hombro',
        ]))->assertSessionHasErrors('grupo_muscular_zona');
    }

    public function test_store_validates_numeric_schema_bounds_without_clinical_maxima(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);

        $this->store($this->storePayload($anatomica, [
            'series_recomendadas' => 1,
            'repeticiones_recomendadas' => 1,
            'duracion_segundos_recomendada' => 1,
            'frecuencia_cantidad_base' => 1,
            'frecuencia_periodo_base' => 'DIARIA',
            'descanso_segundos_base' => 0,
        ]))->assertOk();

        foreach ([
            'series_recomendadas',
            'repeticiones_recomendadas',
            'duracion_segundos_recomendada',
            'frecuencia_cantidad_base',
        ] as $campo) {
            $cambios = [$campo => 0];

            if ($campo === 'frecuencia_cantidad_base') {
                $cambios['frecuencia_periodo_base'] = 'SEMANAL';
            }

            $this->store($this->storePayload($anatomica, $cambios))
                ->assertSessionHasErrors($campo);
        }

        $this->store($this->storePayload($anatomica, ['descanso_segundos_base' => -1]))
            ->assertSessionHasErrors('descanso_segundos_base');
        $this->store($this->storePayload($anatomica, ['series_recomendadas' => 4294967296]))
            ->assertSessionHasErrors('series_recomendadas');
    }

    public function test_store_enforces_frequency_pair_and_period_enum(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);

        $this->store($this->storePayload($anatomica, [
            'frecuencia_cantidad_base' => null,
            'frecuencia_periodo_base' => null,
        ]))->assertOk();
        $this->store($this->storePayload($anatomica, [
            'frecuencia_cantidad_base' => 3,
            'frecuencia_periodo_base' => 'SEMANAL',
        ]))->assertOk();
        $this->store($this->storePayload($anatomica, [
            'frecuencia_cantidad_base' => 2,
            'frecuencia_periodo_base' => null,
        ]))->assertSessionHasErrors('frecuencia_periodo_base');
        $this->store($this->storePayload($anatomica, [
            'frecuencia_cantidad_base' => null,
            'frecuencia_periodo_base' => 'DIARIA',
        ]))->assertSessionHasErrors('frecuencia_cantidad_base');
        $this->store($this->storePayload($anatomica, [
            'frecuencia_cantidad_base' => 1,
            'frecuencia_periodo_base' => 'MENSUAL',
        ]))->assertSessionHasErrors('frecuencia_periodo_base');
    }

    public function test_store_accepts_only_allowed_active_category_combinations(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $funcional = $this->categoria('FUNC-01', TipoCategoriaClinicaEnum::FUNCIONAL);
        $terapeutica = $this->categoria('TER-01', TipoCategoriaClinicaEnum::TERAPEUTICA);

        foreach ([
            [$anatomica->id],
            [$anatomica->id, $funcional->id],
            [$anatomica->id, $terapeutica->id],
        ] as $indice => $categorias) {
            $this->store($this->storePayload($anatomica, [
                'codigo' => 'EJ-CAT-'.$indice,
                'categorias' => $categorias,
            ]))->assertOk();
        }
    }

    public function test_store_rejects_missing_anatomical_inactive_equipment_nonexistent_and_duplicate_categories(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $anatomicaInactiva = $this->categoria('ANAT-I', TipoCategoriaClinicaEnum::ANATOMICA, false);
        $funcional = $this->categoria('FUNC-01', TipoCategoriaClinicaEnum::FUNCIONAL);
        $terapeutica = $this->categoria('TER-01', TipoCategoriaClinicaEnum::TERAPEUTICA);
        $equipamiento = $this->categoria('EQUIP-01', TipoCategoriaClinicaEnum::EQUIPAMIENTO);

        $this->store($this->storePayload($anatomica, ['categorias' => [$funcional->id, $terapeutica->id]]))
            ->assertSessionHasErrors('categorias');
        $this->store($this->storePayload($anatomica, ['categorias' => [$anatomicaInactiva->id]]))
            ->assertSessionHasErrors(['categorias.0', 'categorias']);
        $this->store($this->storePayload($anatomica, ['categorias' => [$anatomica->id, $equipamiento->id]]))
            ->assertSessionHasErrors('categorias.1');
        $this->store($this->storePayload($anatomica, ['categorias' => [$anatomica->id, 999999]]))
            ->assertSessionHasErrors('categorias.1');
        $this->store($this->storePayload($anatomica, ['categorias' => [$anatomica->id, $anatomica->id]]))
            ->assertSessionHasErrors('categorias.1');
    }

    public function test_store_allows_resources_to_be_absent_null_or_empty(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $sinRecursos = $this->storePayload($anatomica);
        unset($sinRecursos['recursos']);

        $this->store($sinRecursos)->assertOk();
        $this->store($this->storePayload($anatomica, ['codigo' => 'EJ-002', 'recursos' => null]))->assertOk();
        $this->store($this->storePayload($anatomica, ['codigo' => 'EJ-003', 'recursos' => []]))->assertOk();
    }

    public function test_store_validates_new_local_resources_without_storage_side_effects(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);

        $this->store($this->storePayload($anatomica, [
            'recursos' => [$this->localResource(['archivo' => UploadedFile::fake()->create('guia.bin', 1)])],
        ]))->assertOk();
        $this->store($this->storePayload($anatomica, [
            'recursos' => [$this->localResource()],
        ]))->assertSessionHasErrors('recursos.0.archivo');
        $this->store($this->storePayload($anatomica, [
            'recursos' => [$this->localResource([
                'archivo' => UploadedFile::fake()->create('guia.bin', 1),
                'url_externa' => 'https://example.com/guia',
            ])],
        ]))->assertSessionHasErrors('recursos.0.url_externa');
    }

    public function test_store_validates_new_external_resources_and_resource_enums(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);

        $this->store($this->storePayload($anatomica, [
            'recursos' => [$this->externalResource()],
        ]))->assertOk();
        $this->store($this->storePayload($anatomica, [
            'recursos' => [$this->externalResource(['url_externa' => null])],
        ]))->assertSessionHasErrors('recursos.0.url_externa');
        $this->store($this->storePayload($anatomica, [
            'recursos' => [$this->externalResource(['url_externa' => 'no-es-url'])],
        ]))->assertSessionHasErrors('recursos.0.url_externa');
        $this->store($this->storePayload($anatomica, [
            'recursos' => [$this->externalResource(['archivo' => UploadedFile::fake()->create('video.bin', 1)])],
        ]))->assertSessionHasErrors('recursos.0.archivo');
        $this->store($this->storePayload($anatomica, [
            'recursos' => [$this->externalResource(['tipo' => 'AUDIO'])],
        ]))->assertSessionHasErrors('recursos.0.tipo');
        $this->store($this->storePayload($anatomica, [
            'recursos' => [$this->externalResource(['origen' => 'REMOTO'])],
        ]))->assertSessionHasErrors('recursos.0.origen');
    }

    public function test_store_rejects_client_storage_path_and_persisted_resource_id(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $ejercicio = $this->ejercicio('EJ-PERSISTIDO', true, [$anatomica]);
        $recurso = $this->recursoLocal($ejercicio, 'persistido.bin');

        $this->store($this->storePayload($anatomica, [
            'recursos' => [$this->externalResource(['ruta_storage' => '../../../../archivo'])],
        ]))->assertSessionHasErrors('recursos.0.ruta_storage');
        $this->store($this->storePayload($anatomica, [
            'recursos' => [$this->externalResource(['id' => $recurso->id])],
        ]))->assertSessionHasErrors('recursos.0.id');
    }

    public function test_update_uses_bound_model_for_unique_code_and_keeps_main_contract(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $propio = $this->ejercicio('EJ-PROPIO', true, [$anatomica]);
        $otro = $this->ejercicio('EJ-OTRO', true, [$anatomica]);

        $this->update($propio, $this->updatePayload($propio, $anatomica))
            ->assertOk()->assertJsonPath('ejercicio_id', $propio->id);
        $this->update($propio, $this->updatePayload($propio, $anatomica, ['codigo' => $otro->codigo]))
            ->assertSessionHasErrors('codigo');
        $this->update($propio, $this->updatePayload($propio, $anatomica, ['activo' => false]))
            ->assertSessionHasErrors('activo');
        $this->update($propio, $this->updatePayload($propio, $anatomica, ['grupo_muscular_zona' => 'Rodilla']))
            ->assertSessionHasErrors('grupo_muscular_zona');
    }

    public function test_update_requires_a_non_null_string_description(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $ejercicio = $this->ejercicio('EJ-001', true, [$anatomica]);

        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'descripcion' => 'Descripción actualizada válida.',
        ]))->assertOk();

        $sinDescripcion = $this->updatePayload($ejercicio, $anatomica);
        unset($sinDescripcion['descripcion']);

        $this->update($ejercicio, $sinDescripcion)->assertSessionHasErrors('descripcion');
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, ['descripcion' => null]))
            ->assertSessionHasErrors('descripcion');
    }

    public function test_update_requires_categories_to_be_present_but_allows_omitting_resources(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $ejercicio = $this->ejercicio('EJ-001', true, [$anatomica]);
        $payload = $this->updatePayload($ejercicio, $anatomica);
        unset($payload['categorias'], $payload['recursos']);

        $this->update($ejercicio, $payload)->assertSessionHasErrors('categorias');

        $payload['categorias'] = [$anatomica->id];
        $this->update($ejercicio, $payload)->assertOk();
    }

    public function test_update_active_exercise_can_preserve_inactive_historical_category(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $historica = $this->categoria('FUNC-H', TipoCategoriaClinicaEnum::FUNCIONAL, false);
        $ejercicio = $this->ejercicio('EJ-001', true, [$anatomica, $historica]);

        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'categorias' => [$anatomica->id, $historica->id],
        ]))->assertOk();
    }

    public function test_update_accepts_new_active_allowed_categories(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $ejercicio = $this->ejercicio('EJ-001', true, [$anatomica]);

        foreach ([TipoCategoriaClinicaEnum::ANATOMICA, TipoCategoriaClinicaEnum::FUNCIONAL, TipoCategoriaClinicaEnum::TERAPEUTICA] as $indice => $tipo) {
            $nueva = $this->categoria('NUEVA-'.$indice, $tipo);

            $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
                'categorias' => [$anatomica->id, $nueva->id],
            ]))->assertOk();
        }
    }

    public function test_update_rejects_new_inactive_equipment_nonexistent_and_duplicate_categories(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $inactiva = $this->categoria('FUNC-I', TipoCategoriaClinicaEnum::FUNCIONAL, false);
        $equipamiento = $this->categoria('EQUIP-01', TipoCategoriaClinicaEnum::EQUIPAMIENTO);
        $ejercicio = $this->ejercicio('EJ-001', true, [$anatomica]);

        foreach ([$inactiva->id, $equipamiento->id, 999999] as $categoriaId) {
            $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
                'categorias' => [$anatomica->id, $categoriaId],
            ]))->assertSessionHasErrors('categorias.1');
        }

        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'categorias' => [$anatomica->id, $anatomica->id],
        ]))->assertSessionHasErrors('categorias.1');
    }

    public function test_update_active_exercise_requires_active_anatomical_and_zero_equipment(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $anatomicaInactiva = $this->categoria('ANAT-I', TipoCategoriaClinicaEnum::ANATOMICA, false);
        $funcional = $this->categoria('FUNC-01', TipoCategoriaClinicaEnum::FUNCIONAL);
        $equipamientoHistorico = $this->categoria('EQUIP-H', TipoCategoriaClinicaEnum::EQUIPAMIENTO);
        $ejercicio = $this->ejercicio('EJ-001', true, [$anatomica, $anatomicaInactiva, $equipamientoHistorico]);

        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'categorias' => [$funcional->id],
        ]))->assertSessionHasErrors('categorias');
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'categorias' => [$anatomicaInactiva->id, $funcional->id],
        ]))->assertSessionHasErrors('categorias');
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'categorias' => [$anatomica->id, $equipamientoHistorico->id],
        ]))->assertSessionHasErrors('categorias');
    }

    public function test_update_inactive_exercise_can_have_empty_categories_and_preserve_historical_equipment(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $historica = $this->categoria('EQUIP-H', TipoCategoriaClinicaEnum::EQUIPAMIENTO, false);
        $nuevaEquipamiento = $this->categoria('EQUIP-N', TipoCategoriaClinicaEnum::EQUIPAMIENTO);
        $ejercicio = $this->ejercicio('EJ-001', false, [$historica]);

        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, ['categorias' => []]))
            ->assertOk();
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, ['categorias' => [$historica->id]]))
            ->assertOk();
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, ['categorias' => [$nuevaEquipamiento->id]]))
            ->assertSessionHasErrors('categorias.0');
    }

    public function test_update_validates_resource_ownership_and_allows_new_resource_ids_to_be_absent_or_null(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $ejercicioA = $this->ejercicio('EJ-A', true, [$anatomica]);
        $ejercicioB = $this->ejercicio('EJ-B', true, [$anatomica]);
        $recursoA = $this->recursoLocal($ejercicioA, 'a.bin');
        $recursoB = $this->recursoLocal($ejercicioB, 'b.bin');

        $this->update($ejercicioA, $this->updatePayload($ejercicioA, $anatomica, [
            'recursos' => [$this->localResource(['id' => $recursoA->id])],
        ]))->assertOk();
        $this->update($ejercicioA, $this->updatePayload($ejercicioA, $anatomica, [
            'recursos' => [$this->localResource(['id' => $recursoB->id])],
        ]))->assertSessionHasErrors('recursos.0.id');
        $this->update($ejercicioA, $this->updatePayload($ejercicioA, $anatomica, [
            'recursos' => [$this->localResource(['id' => 999999])],
        ]))->assertSessionHasErrors('recursos.0.id');
        $this->update($ejercicioA, $this->updatePayload($ejercicioA, $anatomica, [
            'recursos' => [$this->externalResource(['id' => null])],
        ]))->assertOk();
        $this->update($ejercicioA, $this->updatePayload($ejercicioA, $anatomica, [
            'recursos' => [$this->externalResource()],
        ]))->assertOk();
    }

    public function test_update_rejects_duplicate_persisted_resource_ids(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $ejercicio = $this->ejercicio('EJ-001', true, [$anatomica]);
        $recurso = $this->recursoLocal($ejercicio, 'local.bin');

        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [
                $this->localResource(['id' => $recurso->id]),
                $this->localResource(['id' => $recurso->id, 'orden' => 2]),
            ],
        ]))->assertSessionHasErrors('recursos.1.id');
    }

    public function test_update_validates_existing_local_and_external_to_local_transitions(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $ejercicio = $this->ejercicio('EJ-001', true, [$anatomica]);
        $local = $this->recursoLocal($ejercicio, 'local.bin');
        $externo = $this->recursoExterno($ejercicio, 'https://example.com/existente');

        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [$this->localResource(['id' => $local->id])],
        ]))->assertOk();
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [$this->localResource([
                'id' => $local->id,
                'archivo' => UploadedFile::fake()->create('reemplazo.bin', 1),
            ])],
        ]))->assertOk();
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [$this->localResource(['id' => $externo->id])],
        ]))->assertSessionHasErrors('recursos.0.archivo');
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [$this->localResource([
                'id' => $externo->id,
                'archivo' => UploadedFile::fake()->create('nuevo-local.bin', 1),
            ])],
        ]))->assertOk();
    }

    public function test_update_validates_existing_external_and_local_to_external_transitions(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $ejercicio = $this->ejercicio('EJ-001', true, [$anatomica]);
        $externo = $this->recursoExterno($ejercicio, 'https://example.com/existente');
        $local = $this->recursoLocal($ejercicio, 'local.bin');

        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [$this->externalResource(['id' => $externo->id])],
        ]))->assertOk();
        $externoSinUrl = $this->externalResource(['id' => $externo->id]);
        unset($externoSinUrl['url_externa']);
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [$externoSinUrl],
        ]))->assertOk();
        $localAExternoSinUrl = $this->externalResource(['id' => $local->id]);
        unset($localAExternoSinUrl['url_externa']);
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [$localAExternoSinUrl],
        ]))->assertSessionHasErrors('recursos.0.url_externa');
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [$this->externalResource(['id' => $local->id])],
        ]))->assertOk();
    }

    public function test_update_rejects_cross_origin_references_and_client_storage_path(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $ejercicio = $this->ejercicio('EJ-001', true, [$anatomica]);
        $local = $this->recursoLocal($ejercicio, 'local.bin');
        $externo = $this->recursoExterno($ejercicio, 'https://example.com/externo');

        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [$this->externalResource([
                'id' => $externo->id,
                'archivo' => UploadedFile::fake()->create('indebido.bin', 1),
            ])],
        ]))->assertSessionHasErrors('recursos.0.archivo');
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [$this->localResource([
                'id' => $local->id,
                'url_externa' => 'https://example.com/indebida',
            ])],
        ]))->assertSessionHasErrors('recursos.0.url_externa');
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [$this->localResource([
                'id' => $local->id,
                'ruta_storage' => 'ejercicios/fake.pdf',
            ])],
        ]))->assertSessionHasErrors('recursos.0.ruta_storage');
    }

    public function test_update_does_not_require_all_persisted_resources_in_payload(): void
    {
        $anatomica = $this->categoria('ANAT-01', TipoCategoriaClinicaEnum::ANATOMICA);
        $ejercicio = $this->ejercicio('EJ-001', true, [$anatomica]);
        $recursoA = $this->recursoLocal($ejercicio, 'a.bin');
        $this->recursoLocal($ejercicio, 'b.bin', 2);

        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [$this->localResource(['id' => $recursoA->id])],
        ]))->assertOk();
        $this->update($ejercicio, $this->updatePayload($ejercicio, $anatomica, [
            'recursos' => [],
        ]))->assertOk();

        $sinRecursos = $this->updatePayload($ejercicio, $anatomica);
        unset($sinRecursos['recursos']);
        $this->update($ejercicio, $sinRecursos)->assertOk();
    }

    private function store(array $payload): TestResponse
    {
        return $this->from('/_tests/formulario-ejercicio')
            ->post('/_tests/requests/ejercicios-domiciliarios', $payload);
    }

    private function update(EjercicioDomiciliario $ejercicio, array $payload): TestResponse
    {
        return $this->from('/_tests/formulario-ejercicio/'.$ejercicio->id)
            ->put('/_tests/requests/ejercicios-domiciliarios/'.$ejercicio->id, $payload);
    }

    private function storePayload(CategoriaClinica $anatomica, array $cambios = []): array
    {
        return array_replace([
            'codigo' => 'EJ-001',
            'nombre' => 'Movilidad de hombro',
            'descripcion' => 'Movilización activa asistida.',
            'dificultad_base' => DificultadEjercicioEnum::BAJA->value,
            'categorias' => [$anatomica->id],
            'recursos' => [],
        ], $cambios);
    }

    private function updatePayload(
        EjercicioDomiciliario $ejercicio,
        CategoriaClinica $anatomica,
        array $cambios = []
    ): array {
        return array_replace([
            'codigo' => $ejercicio->codigo,
            'nombre' => $ejercicio->nombre,
            'descripcion' => $ejercicio->descripcion,
            'dificultad_base' => $ejercicio->dificultad_base->value,
            'categorias' => [$anatomica->id],
            'recursos' => [],
        ], $cambios);
    }

    private function localResource(array $cambios = []): array
    {
        return array_replace([
            'tipo' => TipoRecursoEjercicioEnum::DOCUMENTO->value,
            'origen' => OrigenRecursoEjercicioEnum::LOCAL->value,
            'titulo' => 'Guía local',
            'orden' => 0,
            'activo' => true,
        ], $cambios);
    }

    private function externalResource(array $cambios = []): array
    {
        return array_replace([
            'tipo' => TipoRecursoEjercicioEnum::VIDEO->value,
            'origen' => OrigenRecursoEjercicioEnum::EXTERNO->value,
            'titulo' => 'Demostración externa',
            'orden' => 1,
            'activo' => true,
            'url_externa' => 'https://example.com/demostracion',
        ], $cambios);
    }

    /**
     * @param  array<int, CategoriaClinica>  $categorias
     */
    private function ejercicio(string $codigo, bool $activo, array $categorias): EjercicioDomiciliario
    {
        $ejercicio = EjercicioDomiciliario::create([
            'codigo' => $codigo,
            'nombre' => 'Ejercicio '.$codigo,
            'descripcion' => 'Descripción persistida.',
            'dificultad_base' => DificultadEjercicioEnum::MEDIA,
            'activo' => $activo,
        ]);
        $ejercicio->categorias()->attach(collect($categorias)->pluck('id'));

        return $ejercicio;
    }

    private function categoria(
        string $codigo,
        TipoCategoriaClinicaEnum $tipo,
        bool $activo = true
    ): CategoriaClinica {
        return CategoriaClinica::create([
            'categoria_padre_id' => null,
            'codigo' => $codigo,
            'nombre' => $codigo,
            'tipo' => $tipo,
            'activo' => $activo,
        ]);
    }

    private function recursoLocal(
        EjercicioDomiciliario $ejercicio,
        string $ruta,
        int $orden = 1
    ): RecursoEjercicio {
        return $ejercicio->recursos()->create([
            'tipo' => TipoRecursoEjercicioEnum::DOCUMENTO,
            'origen' => OrigenRecursoEjercicioEnum::LOCAL,
            'ruta_storage' => 'ejercicios/'.$ruta,
            'url_externa' => null,
            'titulo' => 'Recurso local',
            'orden' => $orden,
            'activo' => true,
        ]);
    }

    private function recursoExterno(EjercicioDomiciliario $ejercicio, string $url): RecursoEjercicio
    {
        return $ejercicio->recursos()->create([
            'tipo' => TipoRecursoEjercicioEnum::VIDEO,
            'origen' => OrigenRecursoEjercicioEnum::EXTERNO,
            'ruta_storage' => null,
            'url_externa' => $url,
            'titulo' => 'Recurso externo',
            'orden' => 1,
            'activo' => true,
        ]);
    }
}
