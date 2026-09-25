<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CrearTecnicaTerapeuticaAction;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrearTecnicaTerapeuticaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_active_tecnica_and_audits_persisted_values(): void
    {
        $actor = User::factory()->create([
            'name' => 'Ana', 'apellido' => 'Administradora',
            'email' => 'ana.admin@example.com',
        ]);
        $this->actingAs($actor);

        $tecnica = app(CrearTecnicaTerapeuticaAction::class)->ejecutar([
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEC-001',
            'nombre' => 'Movilización articular', 'descripcion' => 'Técnica manual',
            'activo' => false,
        ]);

        $this->assertTrue($tecnica->exists);
        $this->assertTrue($tecnica->activo);
        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'id' => $tecnica->id, 'sistema_origen' => 'INTERNO',
            'codigo' => 'TEC-001', 'nombre' => 'Movilización articular',
            'descripcion' => 'Técnica manual', 'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();
        $this->assertSame('tecnica_terapeutica_creada', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($tecnica->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($tecnica->id, $auditoria->auditable_id);
        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertNull($auditoria->valores_anteriores);
        $this->assertSame([
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEC-001',
            'nombre' => 'Movilización articular', 'descripcion' => 'Técnica manual',
            'activo' => true,
        ], $auditoria->valores_nuevos);
    }

    public function test_rolls_back_creation_when_audit_persistence_fails(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(CrearTecnicaTerapeuticaAction::class)->ejecutar([
                'sistema_origen' => 'INTERNO', 'codigo' => 'R-01',
                'nombre' => 'Técnica revertida',
            ]);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('tecnicas_terapeuticas', [
                'sistema_origen' => 'INTERNO', 'codigo' => 'R-01',
            ]);
            $this->assertDatabaseCount('tecnicas_terapeuticas', 0);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }
}
