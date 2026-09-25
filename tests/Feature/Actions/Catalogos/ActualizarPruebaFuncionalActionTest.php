<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\ActualizarPruebaFuncionalAction;
use App\Models\Auditoria;
use App\Models\PruebaFuncional;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActualizarPruebaFuncionalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_only_changed_fields_audits_them_and_preserves_active_state(): void
    {
        $prueba = $this->prueba(true);
        $this->actingAs(User::factory()->create());

        $actualizado = app(ActualizarPruebaFuncionalAction::class)->ejecutar($prueba, [
            'sistema_origen' => 'SNOMED', 'codigo' => 'S-100',
            'nombre' => 'Test funcional revisado', 'descripcion' => 'Cambio de descripción',
            'unidad_medida' => 'grados',
            'activo' => false,
        ]);

        $this->assertTrue($actualizado->activo);
        $this->assertDatabaseHas('pruebas_funcionales', [
            'id' => $prueba->id, 'sistema_origen' => 'SNOMED',
            'codigo' => 'S-100', 'nombre' => 'Test funcional revisado',
            'descripcion' => 'Cambio de descripción', 'unidad_medida' => 'grados', 'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();
        $this->assertSame('prueba_funcional_actualizada', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($prueba->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($prueba->id, $auditoria->auditable_id);
        $this->assertSame([
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEST-001',
            'nombre' => 'Test funcional', 'descripcion' => 'Medición funcional',
            'unidad_medida' => 'cm',
        ], $auditoria->valores_anteriores);
        $this->assertSame([
            'sistema_origen' => 'SNOMED', 'codigo' => 'S-100',
            'nombre' => 'Test funcional revisado', 'descripcion' => 'Cambio de descripción',
            'unidad_medida' => 'grados',
        ], $auditoria->valores_nuevos);
    }

    public function test_edits_inactive_record_without_reactivating_it(): void
    {
        $prueba = $this->prueba(false);

        app(ActualizarPruebaFuncionalAction::class)->ejecutar($prueba, [
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEST-001',
            'nombre' => 'Test funcional histórico', 'descripcion' => 'Medición funcional',
            'unidad_medida' => null,
            'activo' => true,
        ]);

        $this->assertDatabaseHas('pruebas_funcionales', [
            'id' => $prueba->id, 'nombre' => 'Test funcional histórico',
            'unidad_medida' => null,
            'activo' => false,
        ]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('prueba_funcional_actualizada', $auditoria->evento);
        $this->assertSame(['nombre' => 'Test funcional', 'unidad_medida' => 'cm'], $auditoria->valores_anteriores);
        $this->assertSame(['nombre' => 'Test funcional histórico', 'unidad_medida' => null], $auditoria->valores_nuevos);
    }

    public function test_unchanged_update_is_noop_without_audit(): void
    {
        $prueba = $this->prueba(true);

        $actualizado = app(ActualizarPruebaFuncionalAction::class)->ejecutar($prueba, [
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEST-001',
            'nombre' => 'Test funcional', 'descripcion' => 'Medición funcional',
            'unidad_medida' => 'cm',
            'activo' => false,
        ]);

        $this->assertSame($prueba->id, $actualizado->id);
        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseHas('pruebas_funcionales', [
            'id' => $prueba->id, 'nombre' => 'Test funcional', 'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_changing_only_unit_audits_only_unit(): void
    {
        $prueba = $this->prueba(true);

        app(ActualizarPruebaFuncionalAction::class)->ejecutar($prueba, [
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEST-001',
            'nombre' => 'Test funcional', 'descripcion' => 'Medición funcional',
            'unidad_medida' => null,
        ]);

        $this->assertDatabaseHas('pruebas_funcionales', [
            'id' => $prueba->id, 'unidad_medida' => null, 'activo' => true,
        ]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame(['unidad_medida' => 'cm'], $auditoria->valores_anteriores);
        $this->assertSame(['unidad_medida' => null], $auditoria->valores_nuevos);
    }

    public function test_rolls_back_changed_values_when_audit_persistence_fails(): void
    {
        $prueba = $this->prueba(true);
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(ActualizarPruebaFuncionalAction::class)->ejecutar($prueba, [
                'sistema_origen' => 'LOINC', 'codigo' => 'C-02',
                'nombre' => 'Cambio revertido', 'descripcion' => 'Nueva descripción',
                'unidad_medida' => 'grados',
            ]);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseHas('pruebas_funcionales', [
                'id' => $prueba->id, 'sistema_origen' => 'INTERNO',
                'codigo' => 'TEST-001', 'nombre' => 'Test funcional',
                'descripcion' => 'Medición funcional', 'unidad_medida' => 'cm', 'activo' => true,
            ]);
            $this->assertDatabaseMissing('pruebas_funcionales', [
                'id' => $prueba->id, 'nombre' => 'Cambio revertido',
            ]);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function prueba(bool $activo): PruebaFuncional
    {
        return PruebaFuncional::create([
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEST-001',
            'nombre' => 'Test funcional', 'descripcion' => 'Medición funcional',
            'unidad_medida' => 'cm',
            'activo' => $activo,
        ]);
    }
}
