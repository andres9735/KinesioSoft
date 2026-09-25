<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\ActualizarDiagnosticoClinicoAction;
use App\Models\Auditoria;
use App\Models\DiagnosticoClinico;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActualizarDiagnosticoClinicoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_only_changed_fields_audits_them_and_preserves_active_state(): void
    {
        $diagnostico = $this->diagnostico(true);
        $this->actingAs(User::factory()->create());

        $actualizado = app(ActualizarDiagnosticoClinicoAction::class)->ejecutar($diagnostico, [
            'sistema_origen' => 'SNOMED', 'codigo' => 'S-100',
            'nombre' => 'Lumbalgia revisada', 'descripcion' => 'Dolor actualizado',
            'activo' => false,
        ]);

        $this->assertTrue($actualizado->activo);
        $this->assertDatabaseHas('diagnosticos_clinicos', [
            'id' => $diagnostico->id, 'sistema_origen' => 'SNOMED',
            'codigo' => 'S-100', 'nombre' => 'Lumbalgia revisada',
            'descripcion' => 'Dolor actualizado', 'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();
        $this->assertSame('diagnostico_clinico_actualizado', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($diagnostico->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($diagnostico->id, $auditoria->auditable_id);
        $this->assertSame([
            'sistema_origen' => 'CIE-10', 'codigo' => 'M54.5',
            'nombre' => 'Lumbalgia', 'descripcion' => 'Dolor lumbar',
        ], $auditoria->valores_anteriores);
        $this->assertSame([
            'sistema_origen' => 'SNOMED', 'codigo' => 'S-100',
            'nombre' => 'Lumbalgia revisada', 'descripcion' => 'Dolor actualizado',
        ], $auditoria->valores_nuevos);
    }

    public function test_edits_inactive_record_without_reactivating_it(): void
    {
        $diagnostico = $this->diagnostico(false);

        app(ActualizarDiagnosticoClinicoAction::class)->ejecutar($diagnostico, [
            'sistema_origen' => 'CIE-10', 'codigo' => 'M54.5',
            'nombre' => 'Lumbalgia histórica', 'descripcion' => 'Dolor lumbar',
            'activo' => true,
        ]);

        $this->assertDatabaseHas('diagnosticos_clinicos', [
            'id' => $diagnostico->id, 'nombre' => 'Lumbalgia histórica',
            'activo' => false,
        ]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('diagnostico_clinico_actualizado', $auditoria->evento);
        $this->assertSame(['nombre' => 'Lumbalgia'], $auditoria->valores_anteriores);
        $this->assertSame(['nombre' => 'Lumbalgia histórica'], $auditoria->valores_nuevos);
    }

    public function test_unchanged_update_is_noop_without_audit(): void
    {
        $diagnostico = $this->diagnostico(true);

        $actualizado = app(ActualizarDiagnosticoClinicoAction::class)->ejecutar($diagnostico, [
            'sistema_origen' => 'CIE-10', 'codigo' => 'M54.5',
            'nombre' => 'Lumbalgia', 'descripcion' => 'Dolor lumbar',
            'activo' => false,
        ]);

        $this->assertSame($diagnostico->id, $actualizado->id);
        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseHas('diagnosticos_clinicos', [
            'id' => $diagnostico->id, 'nombre' => 'Lumbalgia', 'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_rolls_back_changed_values_when_audit_persistence_fails(): void
    {
        $diagnostico = $this->diagnostico(true);
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(ActualizarDiagnosticoClinicoAction::class)->ejecutar($diagnostico, [
                'sistema_origen' => 'CIF', 'codigo' => 'C-02',
                'nombre' => 'Cambio revertido', 'descripcion' => 'Nueva descripción',
            ]);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseHas('diagnosticos_clinicos', [
                'id' => $diagnostico->id, 'sistema_origen' => 'CIE-10',
                'codigo' => 'M54.5', 'nombre' => 'Lumbalgia',
                'descripcion' => 'Dolor lumbar', 'activo' => true,
            ]);
            $this->assertDatabaseMissing('diagnosticos_clinicos', [
                'id' => $diagnostico->id, 'nombre' => 'Cambio revertido',
            ]);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function diagnostico(bool $activo): DiagnosticoClinico
    {
        return DiagnosticoClinico::create([
            'sistema_origen' => 'CIE-10', 'codigo' => 'M54.5',
            'nombre' => 'Lumbalgia', 'descripcion' => 'Dolor lumbar',
            'activo' => $activo,
        ]);
    }
}
