<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\ActualizarCategoriaClinicaAction;
use App\Enums\DificultadEjercicioEnum;
use App\Models\Auditoria;
use App\Models\CategoriaClinica;
use App\Models\EjercicioDomiciliario;
use App\Models\TipoEquipoTerapeutico;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ActualizarCategoriaClinicaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_base_fields_parent_and_type_auditing_only_differences(): void
    {
        $padre = $this->categoria('PADRE', 'Padre', 'FUNCIONAL');
        $categoria = $this->categoria('CAT-001', 'Original', 'ANATOMICA');
        $this->actingAs(User::factory()->create());

        $actualizada = app(ActualizarCategoriaClinicaAction::class)->ejecutar($categoria, [
            'codigo' => 'CAT-001', 'nombre' => 'Revisada', 'tipo' => 'FUNCIONAL',
            'categoria_padre_id' => $padre->id, 'activo' => false,
        ]);

        $this->assertTrue($actualizada->activo);
        $this->assertSame($padre->id, $actualizada->padre->id);
        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $categoria->id, 'nombre' => 'Revisada', 'tipo' => 'FUNCIONAL',
            'categoria_padre_id' => $padre->id, 'activo' => true,
        ]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('categoria_clinica_actualizada', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame([
            'nombre' => 'Original', 'tipo' => 'ANATOMICA', 'categoria_padre_id' => null,
        ], $auditoria->valores_anteriores);
        $this->assertSame([
            'nombre' => 'Revisada', 'tipo' => 'FUNCIONAL', 'categoria_padre_id' => $padre->id,
        ], $auditoria->valores_nuevos);
    }

    public function test_can_make_child_a_root_and_preserve_inactive_state(): void
    {
        $padre = $this->categoria('PADRE', 'Padre');
        $hija = $this->categoria('HIJA', 'Hija', 'ANATOMICA', false, $padre->id);

        app(ActualizarCategoriaClinicaAction::class)->ejecutar($hija, [
            'codigo' => 'HIJA', 'nombre' => 'Hija', 'tipo' => 'ANATOMICA',
            'categoria_padre_id' => null, 'activo' => true,
        ]);

        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $hija->id, 'categoria_padre_id' => null, 'activo' => false,
        ]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame(['categoria_padre_id' => $padre->id], $auditoria->valores_anteriores);
        $this->assertSame(['categoria_padre_id' => null], $auditoria->valores_nuevos);
    }

    public function test_unchanged_update_does_not_save_or_audit(): void
    {
        $padre = $this->categoria('PADRE', 'Padre');
        $hija = $this->categoria('HIJA', 'Hija', 'ANATOMICA', true, $padre->id);

        $actualizada = app(ActualizarCategoriaClinicaAction::class)->ejecutar($hija, [
            'codigo' => 'HIJA', 'nombre' => 'Hija', 'tipo' => 'ANATOMICA',
            'categoria_padre_id' => (string) $padre->id, 'activo' => false,
        ]);

        $this->assertFalse($actualizada->wasChanged());
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_active_child_blocks_incompatible_type_change_without_change_or_audit(): void
    {
        $categoria = $this->categoria('PADRE', 'Padre');
        $hija = $this->categoria('HIJA', 'Hija', 'ANATOMICA', true, $categoria->id);

        $this->assertDependencyRejected(
            fn () => $this->actualizar($categoria, ['tipo' => 'FUNCIONAL']),
            'hijas con un tipo distinto'
        );

        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $categoria->id, 'tipo' => 'ANATOMICA',
        ]);
        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $hija->id, 'tipo' => 'ANATOMICA', 'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_inactive_child_blocks_incompatible_parent_type_change_without_change_or_audit(): void
    {
        $categoria = $this->categoria('PADRE', 'Padre');
        $hija = $this->categoria('HIJA', 'Hija', 'ANATOMICA', false, $categoria->id);

        $this->assertDependencyRejected(
            fn () => $this->actualizar($categoria, ['tipo' => 'FUNCIONAL']),
            'hijas con un tipo distinto'
        );

        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $categoria->id, 'tipo' => 'ANATOMICA',
        ]);
        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $hija->id, 'tipo' => 'ANATOMICA', 'activo' => false,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_name_and_code_update_without_type_change_is_allowed_with_children(): void
    {
        $categoria = $this->categoria('PADRE', 'Padre');
        $this->categoria('HIJA-A', 'Hija activa', 'ANATOMICA', true, $categoria->id);
        $this->categoria('HIJA-I', 'Hija inactiva', 'ANATOMICA', false, $categoria->id);

        $actualizada = $this->actualizar($categoria, [
            'codigo' => 'PADRE-2', 'nombre' => 'Padre revisado',
        ]);

        $this->assertSame('PADRE-2', $actualizada->codigo);
        $this->assertSame('Padre revisado', $actualizada->nombre);
        $this->assertSame('ANATOMICA', $actualizada->tipo->value);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_only_active_anatomical_category_of_active_exercise_blocks_type_change(): void
    {
        $categoria = $this->categoria('ANAT-1', 'Anatómica');
        $ejercicio = $this->ejercicio(true);
        $ejercicio->categorias()->attach($categoria->id);

        $this->assertDependencyRejected(
            fn () => $this->actualizar($categoria, ['tipo' => 'FUNCIONAL']),
            'ejercicio activo'
        );

        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $categoria->id, 'tipo' => 'ANATOMICA',
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_other_active_anatomical_category_allows_type_change(): void
    {
        $categoria = $this->categoria('ANAT-1', 'Anatómica');
        $alternativa = $this->categoria('ANAT-2', 'Otra anatómica');
        $ejercicio = $this->ejercicio(true);
        $ejercicio->categorias()->attach([$categoria->id, $alternativa->id]);

        $actualizada = $this->actualizar($categoria, ['tipo' => 'FUNCIONAL']);

        $this->assertSame('FUNCIONAL', $actualizada->tipo->value);
        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $alternativa->id, 'tipo' => 'ANATOMICA', 'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_active_exercise_blocks_type_change_to_equipment(): void
    {
        $categoria = $this->categoria('FUNC-1', 'Funcional', 'FUNCIONAL');
        $anatomica = $this->categoria('ANAT-1', 'Anatómica');
        $ejercicio = $this->ejercicio(true);
        $ejercicio->categorias()->attach([$categoria->id, $anatomica->id]);

        $this->assertDependencyRejected(
            fn () => $this->actualizar($categoria, ['tipo' => 'EQUIPAMIENTO']),
            'EQUIPAMIENTO'
        );

        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $categoria->id, 'tipo' => 'FUNCIONAL',
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_active_exercise_allows_functional_to_therapeutic_change(): void
    {
        $categoria = $this->categoria('FUNC-1', 'Funcional', 'FUNCIONAL');
        $anatomica = $this->categoria('ANAT-1', 'Anatómica');
        $ejercicio = $this->ejercicio(true);
        $ejercicio->categorias()->attach([$categoria->id, $anatomica->id]);

        $actualizada = $this->actualizar($categoria, ['tipo' => 'TERAPEUTICA']);

        $this->assertSame('TERAPEUTICA', $actualizada->tipo->value);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_active_equipment_type_blocks_change_away_from_equipment(): void
    {
        $categoria = $this->categoria('EQUIP-1', 'Equipamiento', 'EQUIPAMIENTO');
        $this->tipoEquipo($categoria, true);

        $this->assertDependencyRejected(
            fn () => $this->actualizar($categoria, ['tipo' => 'FUNCIONAL']),
            'tipos de equipo terapéutico activos'
        );

        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $categoria->id, 'tipo' => 'EQUIPAMIENTO',
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_inactive_equipment_type_does_not_block_change_away_from_equipment(): void
    {
        $categoria = $this->categoria('EQUIP-1', 'Equipamiento', 'EQUIPAMIENTO');
        $this->tipoEquipo($categoria, false);

        $actualizada = $this->actualizar($categoria, ['tipo' => 'FUNCIONAL']);

        $this->assertSame('FUNCIONAL', $actualizada->tipo->value);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_type_change_incompatible_with_parent_remains_rejected_by_hierarchy_service(): void
    {
        $padre = $this->categoria('PADRE', 'Padre');
        $hija = $this->categoria('HIJA', 'Hija', 'ANATOMICA', true, $padre->id);

        try {
            $this->actualizar($hija, ['tipo' => 'FUNCIONAL']);
            $this->fail('Se esperaba el rechazo por incompatibilidad con la categoría padre.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('categoria_padre_id', $exception->errors());
        }

        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $hija->id, 'tipo' => 'ANATOMICA', 'categoria_padre_id' => $padre->id,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_rolls_back_changed_values_when_audit_persistence_fails(): void
    {
        $categoria = $this->categoria('CAT-001', 'Original');
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(ActualizarCategoriaClinicaAction::class)->ejecutar($categoria, [
                'codigo' => 'CAT-002', 'nombre' => 'Revertida', 'tipo' => 'FUNCIONAL',
                'categoria_padre_id' => null,
            ]);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseHas('categorias_clinicas', [
                'id' => $categoria->id, 'codigo' => 'CAT-001', 'nombre' => 'Original',
                'tipo' => 'ANATOMICA', 'categoria_padre_id' => null, 'activo' => true,
            ]);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function categoria(
        string $codigo,
        string $nombre,
        string $tipo = 'ANATOMICA',
        bool $activo = true,
        ?int $padreId = null
    ): CategoriaClinica {
        return CategoriaClinica::create([
            'codigo' => $codigo, 'nombre' => $nombre, 'tipo' => $tipo,
            'categoria_padre_id' => $padreId, 'activo' => $activo,
        ]);
    }

    private function actualizar(CategoriaClinica $categoria, array $cambios): CategoriaClinica
    {
        return app(ActualizarCategoriaClinicaAction::class)->ejecutar($categoria, array_replace([
            'codigo' => $categoria->getRawOriginal('codigo'),
            'nombre' => $categoria->getRawOriginal('nombre'),
            'tipo' => $categoria->getRawOriginal('tipo'),
            'categoria_padre_id' => $categoria->categoria_padre_id,
        ], $cambios));
    }

    private function ejercicio(bool $activo): EjercicioDomiciliario
    {
        return EjercicioDomiciliario::create([
            'codigo' => 'EJ-001',
            'nombre' => 'Ejercicio de prueba',
            'descripcion' => 'Descripción de prueba',
            'dificultad_base' => DificultadEjercicioEnum::BAJA,
            'activo' => $activo,
        ]);
    }

    private function tipoEquipo(
        CategoriaClinica $categoriaClinica,
        bool $activo
    ): TipoEquipoTerapeutico {
        return TipoEquipoTerapeutico::create([
            'codigo' => 'EQ-001',
            'nombre' => 'Equipo de prueba',
            'categoria_clinica_id' => $categoriaClinica->id,
            'activo' => $activo,
        ]);
    }

    private function assertDependencyRejected(callable $operation, string $messageFragment): void
    {
        try {
            $operation();
            $this->fail('Se esperaba el rechazo por una dependencia de la categoría clínica.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                $messageFragment,
                implode(' ', $exception->errors()['tipo'] ?? [])
            );
        }
    }
}
