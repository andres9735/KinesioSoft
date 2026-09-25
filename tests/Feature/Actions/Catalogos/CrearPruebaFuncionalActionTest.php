<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CrearPruebaFuncionalAction;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrearPruebaFuncionalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_active_prueba_and_audits_persisted_values(): void
    {
        $actor = User::factory()->create([
            'name' => 'Ana', 'apellido' => 'Administradora',
            'email' => 'ana.admin@example.com',
        ]);
        $this->actingAs($actor);

        $prueba = app(CrearPruebaFuncionalAction::class)->ejecutar([
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEST-001',
            'nombre' => 'Test funcional', 'descripcion' => 'Medición funcional',
            'unidad_medida' => 'cm',
            'activo' => false,
        ]);

        $this->assertTrue($prueba->exists);
        $this->assertTrue($prueba->activo);
        $this->assertDatabaseHas('pruebas_funcionales', [
            'id' => $prueba->id, 'sistema_origen' => 'INTERNO',
            'codigo' => 'TEST-001', 'nombre' => 'Test funcional',
            'descripcion' => 'Medición funcional', 'unidad_medida' => 'cm', 'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();
        $this->assertSame('prueba_funcional_creada', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($prueba->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($prueba->id, $auditoria->auditable_id);
        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertNull($auditoria->valores_anteriores);
        $this->assertSame([
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEST-001',
            'nombre' => 'Test funcional', 'descripcion' => 'Medición funcional',
            'unidad_medida' => 'cm',
            'activo' => true,
        ], $auditoria->valores_nuevos);
    }

    public function test_rolls_back_creation_when_audit_persistence_fails(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(CrearPruebaFuncionalAction::class)->ejecutar([
                'sistema_origen' => 'INTERNO', 'codigo' => 'R-01',
                'nombre' => 'Prueba revertida',
            ]);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('pruebas_funcionales', [
                'sistema_origen' => 'INTERNO', 'codigo' => 'R-01',
            ]);
            $this->assertDatabaseCount('pruebas_funcionales', 0);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public function test_creates_prueba_without_unit_for_qualitative_result(): void
    {
        $prueba = app(CrearPruebaFuncionalAction::class)->ejecutar([
            'sistema_origen' => 'LOINC', 'codigo' => 'BOOL-01',
            'nombre' => 'Prueba cualitativa',
        ]);

        $this->assertDatabaseHas('pruebas_funcionales', [
            'id' => $prueba->id, 'unidad_medida' => null, 'activo' => true,
        ]);
        $this->assertSame('prueba_funcional_creada', Auditoria::query()->sole()->evento);
        $this->assertNull(Auditoria::query()->sole()->valores_nuevos['unidad_medida']);
    }
}
