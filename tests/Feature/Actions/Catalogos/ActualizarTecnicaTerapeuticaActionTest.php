<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\ActualizarTecnicaTerapeuticaAction;
use App\Models\Auditoria;
use App\Models\TecnicaTerapeutica;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActualizarTecnicaTerapeuticaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_only_changed_fields_audits_them_and_preserves_active_state(): void
    {
        $tecnica = $this->tecnica(true);
        $this->actingAs(User::factory()->create());

        $actualizado = app(ActualizarTecnicaTerapeuticaAction::class)->ejecutar($tecnica, [
            'sistema_origen' => 'SNOMED', 'codigo' => 'S-100',
            'nombre' => 'Movilización articular revisada', 'descripcion' => 'Dolor actualizado',
            'activo' => false,
        ]);

        $this->assertTrue($actualizado->activo);
        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'id' => $tecnica->id, 'sistema_origen' => 'SNOMED',
            'codigo' => 'S-100', 'nombre' => 'Movilización articular revisada',
            'descripcion' => 'Dolor actualizado', 'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();
        $this->assertSame('tecnica_terapeutica_actualizada', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($tecnica->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($tecnica->id, $auditoria->auditable_id);
        $this->assertSame([
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEC-001',
            'nombre' => 'Movilización articular', 'descripcion' => 'Técnica manual',
        ], $auditoria->valores_anteriores);
        $this->assertSame([
            'sistema_origen' => 'SNOMED', 'codigo' => 'S-100',
            'nombre' => 'Movilización articular revisada', 'descripcion' => 'Dolor actualizado',
        ], $auditoria->valores_nuevos);
    }

    public function test_edits_inactive_record_without_reactivating_it(): void
    {
        $tecnica = $this->tecnica(false);

        app(ActualizarTecnicaTerapeuticaAction::class)->ejecutar($tecnica, [
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEC-001',
            'nombre' => 'Movilización articular histórica', 'descripcion' => 'Técnica manual',
            'activo' => true,
        ]);

        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'id' => $tecnica->id, 'nombre' => 'Movilización articular histórica',
            'activo' => false,
        ]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('tecnica_terapeutica_actualizada', $auditoria->evento);
        $this->assertSame(['nombre' => 'Movilización articular'], $auditoria->valores_anteriores);
        $this->assertSame(['nombre' => 'Movilización articular histórica'], $auditoria->valores_nuevos);
    }

    public function test_unchanged_update_is_noop_without_audit(): void
    {
        $tecnica = $this->tecnica(true);

        $actualizado = app(ActualizarTecnicaTerapeuticaAction::class)->ejecutar($tecnica, [
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEC-001',
            'nombre' => 'Movilización articular', 'descripcion' => 'Técnica manual',
            'activo' => false,
        ]);

        $this->assertSame($tecnica->id, $actualizado->id);
        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'id' => $tecnica->id, 'nombre' => 'Movilización articular', 'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_rolls_back_changed_values_when_audit_persistence_fails(): void
    {
        $tecnica = $this->tecnica(true);
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(ActualizarTecnicaTerapeuticaAction::class)->ejecutar($tecnica, [
                'sistema_origen' => 'SNOMED', 'codigo' => 'C-02',
                'nombre' => 'Cambio revertido', 'descripcion' => 'Nueva descripción',
            ]);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseHas('tecnicas_terapeuticas', [
                'id' => $tecnica->id, 'sistema_origen' => 'INTERNO',
                'codigo' => 'TEC-001', 'nombre' => 'Movilización articular',
                'descripcion' => 'Técnica manual', 'activo' => true,
            ]);
            $this->assertDatabaseMissing('tecnicas_terapeuticas', [
                'id' => $tecnica->id, 'nombre' => 'Cambio revertido',
            ]);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function tecnica(bool $activo): TecnicaTerapeutica
    {
        return TecnicaTerapeutica::create([
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEC-001',
            'nombre' => 'Movilización articular', 'descripcion' => 'Técnica manual',
            'activo' => $activo,
        ]);
    }
}
