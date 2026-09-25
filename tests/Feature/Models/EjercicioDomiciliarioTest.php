<?php

namespace Tests\Feature\Models;

use App\Enums\DificultadEjercicioEnum;
use App\Enums\FrecuenciaPeriodoEjercicioEnum;
use App\Enums\OrigenRecursoEjercicioEnum;
use App\Enums\TipoCategoriaClinicaEnum;
use App\Enums\TipoRecursoEjercicioEnum;
use App\Models\CategoriaClinica;
use App\Models\EjercicioDomiciliario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EjercicioDomiciliarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_base_fields_and_applies_exercise_casts(): void
    {
        $ejercicio = EjercicioDomiciliario::create([
            'codigo' => 'EJ-MOV-001',
            'nombre' => 'Movilidad de hombro',
            'descripcion' => 'Movilización activa asistida del hombro.',
            'series_recomendadas' => 3,
            'repeticiones_recomendadas' => 12,
            'duracion_segundos_recomendada' => 45,
            'frecuencia_cantidad_base' => 2,
            'frecuencia_periodo_base' => FrecuenciaPeriodoEjercicioEnum::DIARIA,
            'descanso_segundos_base' => 30,
            'carga_resistencia_sugerida' => 'Banda elástica liviana',
            'dificultad_base' => DificultadEjercicioEnum::MEDIA,
            'contraindicaciones' => 'Dolor agudo durante el movimiento.',
            'notas_clinicas' => 'Priorizar amplitud sin compensaciones.',
            'activo' => true,
        ])->fresh();

        $this->assertSame('EJ-MOV-001', $ejercicio->codigo);
        $this->assertSame('Movilidad de hombro', $ejercicio->nombre);
        $this->assertSame(
            'Movilización activa asistida del hombro.',
            $ejercicio->descripcion
        );
        $this->assertSame(3, $ejercicio->series_recomendadas);
        $this->assertSame(12, $ejercicio->repeticiones_recomendadas);
        $this->assertSame(45, $ejercicio->duracion_segundos_recomendada);
        $this->assertSame(2, $ejercicio->frecuencia_cantidad_base);
        $this->assertSame(
            FrecuenciaPeriodoEjercicioEnum::DIARIA,
            $ejercicio->frecuencia_periodo_base
        );
        $this->assertSame(30, $ejercicio->descanso_segundos_base);
        $this->assertSame(
            'Banda elástica liviana',
            $ejercicio->carga_resistencia_sugerida
        );
        $this->assertSame(
            DificultadEjercicioEnum::MEDIA,
            $ejercicio->dificultad_base
        );
        $this->assertSame(
            'Dolor agudo durante el movimiento.',
            $ejercicio->contraindicaciones
        );
        $this->assertSame(
            'Priorizar amplitud sin compensaciones.',
            $ejercicio->notas_clinicas
        );
        $this->assertTrue($ejercicio->activo);
        $this->assertNotContains(
            'grupo_muscular_zona',
            $ejercicio->getFillable()
        );
        $this->assertArrayNotHasKey(
            'grupo_muscular_zona',
            $ejercicio->getCasts()
        );
    }

    public function test_null_frequency_fields_remain_null(): void
    {
        $ejercicio = $this->ejercicio([
            'codigo' => 'EJ-SIN-FRECUENCIA',
            'frecuencia_cantidad_base' => null,
            'frecuencia_periodo_base' => null,
        ])->fresh();

        $this->assertNull($ejercicio->frecuencia_cantidad_base);
        $this->assertNull($ejercicio->frecuencia_periodo_base);
    }

    public function test_categories_relation_uses_pure_pivot_and_has_inverse(): void
    {
        $ejercicio = $this->ejercicio();
        $categoriaAnatomica = $this->categoria(
            'CAT-ANAT-001',
            TipoCategoriaClinicaEnum::ANATOMICA
        );
        $categoriaFuncional = $this->categoria(
            'CAT-FUNC-001',
            TipoCategoriaClinicaEnum::FUNCIONAL
        );

        $ejercicio->categorias()->attach([
            $categoriaAnatomica->id,
            $categoriaFuncional->id,
        ]);

        $this->assertSame(
            [$categoriaAnatomica->id, $categoriaFuncional->id],
            $ejercicio->fresh()
                ->categorias
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );
        $this->assertTrue(
            $categoriaAnatomica->fresh()->ejercicios->contains($ejercicio)
        );
        $this->assertDatabaseCount('ejercicio_categoria', 2);
        $this->assertFalse(
            Schema::hasColumn('ejercicio_categoria', 'created_at')
        );
        $this->assertFalse(
            Schema::hasColumn('ejercicio_categoria', 'updated_at')
        );
    }

    public function test_resources_relation_has_inverse_and_applies_resource_casts(): void
    {
        $ejercicio = $this->ejercicio();

        $imagen = $ejercicio->recursos()->create([
            'tipo' => TipoRecursoEjercicioEnum::IMAGEN,
            'origen' => OrigenRecursoEjercicioEnum::LOCAL,
            'ruta_storage' => 'ejercicios/imagen-movilidad.jpg',
            'url_externa' => null,
            'titulo' => 'Posición inicial',
            'orden' => 1,
            'activo' => true,
        ])->fresh();
        $video = $ejercicio->recursos()->create([
            'tipo' => TipoRecursoEjercicioEnum::VIDEO,
            'origen' => OrigenRecursoEjercicioEnum::EXTERNO,
            'ruta_storage' => null,
            'url_externa' => 'https://example.com/movilidad-hombro',
            'titulo' => 'Demostración',
            'orden' => 2,
            'activo' => false,
        ])->fresh();

        $this->assertSame(
            [$imagen->id, $video->id],
            $ejercicio->fresh()
                ->recursos
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );
        $this->assertTrue($imagen->ejercicio->is($ejercicio));
        $this->assertTrue($video->ejercicio->is($ejercicio));
        $this->assertSame(TipoRecursoEjercicioEnum::IMAGEN, $imagen->tipo);
        $this->assertSame(OrigenRecursoEjercicioEnum::LOCAL, $imagen->origen);
        $this->assertSame(1, $imagen->orden);
        $this->assertTrue($imagen->activo);
        $this->assertSame(TipoRecursoEjercicioEnum::VIDEO, $video->tipo);
        $this->assertSame(OrigenRecursoEjercicioEnum::EXTERNO, $video->origen);
        $this->assertSame(2, $video->orden);
        $this->assertFalse($video->activo);
    }

    private function ejercicio(array $attributes = []): EjercicioDomiciliario
    {
        return EjercicioDomiciliario::create(array_merge([
            'codigo' => 'EJ-BASE-001',
            'nombre' => 'Ejercicio base',
            'descripcion' => 'Descripción del ejercicio base.',
            'dificultad_base' => DificultadEjercicioEnum::BAJA,
            'activo' => true,
        ], $attributes));
    }

    private function categoria(
        string $codigo,
        TipoCategoriaClinicaEnum $tipo
    ): CategoriaClinica {
        return CategoriaClinica::create([
            'categoria_padre_id' => null,
            'codigo' => $codigo,
            'nombre' => $codigo,
            'tipo' => $tipo,
            'activo' => true,
        ]);
    }
}
