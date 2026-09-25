<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\ActualizarCategoriaClinicaAction;
use App\Actions\Catalogos\ActualizarTipoEquipoTerapeuticoAction;
use App\Actions\Catalogos\CambiarEstadoCategoriaClinicaAction;
use App\Models\Auditoria;
use App\Models\CategoriaClinica;
use App\Models\TipoEquipoTerapeutico;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ActualizarTipoEquipoTerapeuticoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_only_changed_fields_audits_differences_and_preserves_active_state(): void
    {
        $categoria = $this->categoria();
        $tipoEquipo = $this->tipoEquipo($categoria, true);

        $actualizado = app(ActualizarTipoEquipoTerapeuticoAction::class)->ejecutar($tipoEquipo, [
            'codigo' => 'EQ-US-2',
            'nombre' => 'Ultrasonido revisado',
            'descripcion' => 'Descripción nueva',
            'categoria_clinica_id' => $categoria->id,
            'activo' => false,
        ]);

        $this->assertTrue($actualizado->activo);
        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $tipoEquipo->id,
            'codigo' => 'EQ-US-2',
            'nombre' => 'Ultrasonido revisado',
            'descripcion' => 'Descripción nueva',
            'categoria_clinica_id' => $categoria->id,
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();
        $this->assertSame('tipo_equipo_terapeutico_actualizado', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame([
            'codigo' => 'EQ-US',
            'nombre' => 'Ultrasonido',
            'descripcion' => 'Original',
        ], $auditoria->valores_anteriores);
        $this->assertSame([
            'codigo' => 'EQ-US-2',
            'nombre' => 'Ultrasonido revisado',
            'descripcion' => 'Descripción nueva',
        ], $auditoria->valores_nuevos);
    }

    public function test_updates_inactive_record_without_reactivating_it(): void
    {
        $categoria = $this->categoria();
        $tipoEquipo = $this->tipoEquipo($categoria, false);

        app(ActualizarTipoEquipoTerapeuticoAction::class)->ejecutar($tipoEquipo, [
            'codigo' => 'EQ-US',
            'nombre' => 'Histórico revisado',
            'descripcion' => 'Original',
            'categoria_clinica_id' => $categoria->id,
            'activo' => true,
        ]);

        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $tipoEquipo->id, 'nombre' => 'Histórico revisado', 'activo' => false,
        ]);
    }

    public function test_changes_to_another_active_equipment_category(): void
    {
        $actual = $this->categoria('ACTUAL');
        $nueva = $this->categoria('NUEVA');
        $tipoEquipo = $this->tipoEquipo($actual, true);

        $actualizado = $this->actualizar($tipoEquipo, ['categoria_clinica_id' => $nueva->id]);

        $this->assertSame($nueva->id, $actualizado->categoria_clinica_id);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame(['categoria_clinica_id' => $actual->id], $auditoria->valores_anteriores);
        $this->assertSame(['categoria_clinica_id' => $nueva->id], $auditoria->valores_nuevos);
    }

    public function test_rejects_new_inactive_or_wrong_type_categories_without_change_or_audit(): void
    {
        $actual = $this->categoria('ACTUAL');
        $tipoEquipo = $this->tipoEquipo($actual, false);
        $invalidas = [
            $this->categoria('INACTIVA', 'EQUIPAMIENTO', false),
            $this->categoria('ANAT', 'ANATOMICA'),
            $this->categoria('FUNC', 'FUNCIONAL'),
            $this->categoria('TER', 'TERAPEUTICA'),
        ];

        foreach ($invalidas as $categoria) {
            try {
                $this->actualizar($tipoEquipo, ['categoria_clinica_id' => $categoria->id]);
                $this->fail('Se esperaba el rechazo de la nueva categoría inválida.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('categoria_clinica_id', $exception->errors());
            }
        }

        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $tipoEquipo->id,
            'categoria_clinica_id' => $actual->id,
            'activo' => false,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_keeps_same_historical_inactive_category_while_editing_other_fields(): void
    {
        $categoria = $this->categoria('HIST');
        $tipoEquipo = $this->tipoEquipo($categoria, false);
        app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, false);
        Auditoria::query()->delete();

        $actualizado = $this->actualizar($tipoEquipo, [
            'nombre' => 'Histórico con categoría inactiva',
        ]);

        $this->assertFalse($actualizado->activo);
        $this->assertSame($categoria->id, $actualizado->categoria_clinica_id);
        $this->assertFalse($actualizado->categoriaClinica->activo);
        $this->assertSame('tipo_equipo_terapeutico_actualizado', Auditoria::query()->sole()->evento);
    }

    public function test_keeps_same_historical_category_that_is_no_longer_equipment(): void
    {
        $categoria = $this->categoria('HIST');
        $tipoEquipo = $this->tipoEquipo($categoria, false);
        app(ActualizarCategoriaClinicaAction::class)->ejecutar($categoria, [
            'codigo' => 'HIST', 'nombre' => 'HIST', 'tipo' => 'FUNCIONAL',
            'categoria_padre_id' => null,
        ]);
        Auditoria::query()->delete();

        $actualizado = $this->actualizar($tipoEquipo, ['codigo' => 'EQ-HIST']);

        $this->assertSame('EQ-HIST', $actualizado->codigo);
        $this->assertFalse($actualizado->activo);
        $this->assertSame($categoria->id, $actualizado->categoria_clinica_id);
        $this->assertSame('FUNCIONAL', $actualizado->categoriaClinica->tipo->value);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_unchanged_update_is_noop_without_save_or_audit(): void
    {
        $categoria = $this->categoria();
        $tipoEquipo = $this->tipoEquipo($categoria, true);

        $actualizado = $this->actualizar($tipoEquipo);

        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_rolls_back_changed_values_when_audit_persistence_fails(): void
    {
        $categoria = $this->categoria();
        $tipoEquipo = $this->tipoEquipo($categoria, true);
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            $this->actualizar($tipoEquipo, ['nombre' => 'Cambio revertido']);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseHas('tipos_equipo_terapeutico', [
                'id' => $tipoEquipo->id,
                'codigo' => 'EQ-US',
                'nombre' => 'Ultrasonido',
                'descripcion' => 'Original',
                'categoria_clinica_id' => $categoria->id,
                'activo' => true,
            ]);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function actualizar(
        TipoEquipoTerapeutico $tipoEquipo,
        array $cambios = []
    ): TipoEquipoTerapeutico {
        return app(ActualizarTipoEquipoTerapeuticoAction::class)->ejecutar(
            $tipoEquipo,
            array_replace([
                'codigo' => $tipoEquipo->getRawOriginal('codigo'),
                'nombre' => $tipoEquipo->getRawOriginal('nombre'),
                'descripcion' => $tipoEquipo->getRawOriginal('descripcion'),
                'categoria_clinica_id' => $tipoEquipo->categoria_clinica_id,
            ], $cambios)
        );
    }

    private function categoria(
        string $codigo = 'CAT-EQ',
        string $tipo = 'EQUIPAMIENTO',
        bool $activo = true
    ): CategoriaClinica {
        return CategoriaClinica::create([
            'codigo' => $codigo,
            'nombre' => $codigo,
            'tipo' => $tipo,
            'activo' => $activo,
        ]);
    }

    private function tipoEquipo(
        CategoriaClinica $categoria,
        bool $activo
    ): TipoEquipoTerapeutico {
        return TipoEquipoTerapeutico::create([
            'codigo' => 'EQ-US',
            'nombre' => 'Ultrasonido',
            'descripcion' => 'Original',
            'categoria_clinica_id' => $categoria->id,
            'activo' => $activo,
        ]);
    }
}
