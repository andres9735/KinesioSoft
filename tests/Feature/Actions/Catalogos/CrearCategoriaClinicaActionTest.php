<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CrearCategoriaClinicaAction;
use App\Models\Auditoria;
use App\Models\CategoriaClinica;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrearCategoriaClinicaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_active_root_and_audits_persisted_values(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $categoria = app(CrearCategoriaClinicaAction::class)->ejecutar([
            'codigo' => 'CAT-001', 'nombre' => 'Columna', 'tipo' => 'ANATOMICA',
            'categoria_padre_id' => null, 'activo' => false,
        ]);

        $this->assertTrue($categoria->exists);
        $this->assertTrue($categoria->activo);
        $this->assertNull($categoria->padre);
        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $categoria->id, 'codigo' => 'CAT-001', 'nombre' => 'Columna',
            'tipo' => 'ANATOMICA', 'categoria_padre_id' => null, 'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();
        $this->assertSame('categoria_clinica_creada', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($categoria->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($categoria->id, $auditoria->auditable_id);
        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertNull($auditoria->valores_anteriores);
        $this->assertSame([
            'codigo' => 'CAT-001', 'nombre' => 'Columna', 'tipo' => 'ANATOMICA',
            'categoria_padre_id' => null, 'activo' => true,
        ], $auditoria->valores_nuevos);
    }

    public function test_creates_child_with_existing_active_parent_of_same_type(): void
    {
        $padre = CategoriaClinica::create([
            'codigo' => 'CAT-001', 'nombre' => 'Columna', 'tipo' => 'ANATOMICA', 'activo' => true,
        ]);

        $hija = app(CrearCategoriaClinicaAction::class)->ejecutar([
            'codigo' => 'CAT-002', 'nombre' => 'Lumbar', 'tipo' => 'ANATOMICA',
            'categoria_padre_id' => $padre->id,
        ]);

        $this->assertSame($padre->id, $hija->categoria_padre_id);
        $this->assertSame($padre->id, $hija->padre->id);
        $this->assertTrue($hija->activo);
        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $hija->id, 'categoria_padre_id' => $padre->id,
        ]);
        $this->assertSame($padre->id, Auditoria::query()->sole()->valores_nuevos['categoria_padre_id']);
    }

    public function test_rolls_back_creation_when_audit_persistence_fails(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(CrearCategoriaClinicaAction::class)->ejecutar([
                'codigo' => 'CAT-ROLLBACK', 'nombre' => 'Revertida', 'tipo' => 'FUNCIONAL',
            ]);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseCount('categorias_clinicas', 0);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }
}
