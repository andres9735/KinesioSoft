<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CrearDiagnosticoClinicoAction;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrearDiagnosticoClinicoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_active_diagnostico_and_audits_persisted_values(): void
    {
        $actor = User::factory()->create([
            'name' => 'Ana', 'apellido' => 'Administradora',
            'email' => 'ana.admin@example.com',
        ]);
        $this->actingAs($actor);

        $diagnostico = app(CrearDiagnosticoClinicoAction::class)->ejecutar([
            'sistema_origen' => 'CIE-10', 'codigo' => 'M54.5',
            'nombre' => 'Lumbalgia', 'descripcion' => 'Dolor lumbar',
            'activo' => false,
        ]);

        $this->assertTrue($diagnostico->exists);
        $this->assertTrue($diagnostico->activo);
        $this->assertDatabaseHas('diagnosticos_clinicos', [
            'id' => $diagnostico->id, 'sistema_origen' => 'CIE-10',
            'codigo' => 'M54.5', 'nombre' => 'Lumbalgia',
            'descripcion' => 'Dolor lumbar', 'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();
        $this->assertSame('diagnostico_clinico_creado', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($diagnostico->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($diagnostico->id, $auditoria->auditable_id);
        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertNull($auditoria->valores_anteriores);
        $this->assertSame([
            'sistema_origen' => 'CIE-10', 'codigo' => 'M54.5',
            'nombre' => 'Lumbalgia', 'descripcion' => 'Dolor lumbar',
            'activo' => true,
        ], $auditoria->valores_nuevos);
    }

    public function test_rolls_back_creation_when_audit_persistence_fails(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(CrearDiagnosticoClinicoAction::class)->ejecutar([
                'sistema_origen' => 'INTERNO', 'codigo' => 'R-01',
                'nombre' => 'Diagnóstico revertido',
            ]);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('diagnosticos_clinicos', [
                'sistema_origen' => 'INTERNO', 'codigo' => 'R-01',
            ]);
            $this->assertDatabaseCount('diagnosticos_clinicos', 0);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }
}
