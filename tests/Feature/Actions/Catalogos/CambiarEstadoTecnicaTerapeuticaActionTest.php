<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CambiarEstadoTecnicaTerapeuticaAction;
use App\Models\Auditoria;
use App\Models\TecnicaTerapeutica;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CambiarEstadoTecnicaTerapeuticaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactivates_and_audits_real_change(): void
    {
        $tecnica = $this->tecnica(true);

        $actualizado = app(CambiarEstadoTecnicaTerapeuticaAction::class)
            ->ejecutar($tecnica, false);

        $this->assertFalse($actualizado->activo);
        $this->assertDatabaseHas('tecnicas_terapeuticas', ['id' => $tecnica->id, 'activo' => false]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('tecnica_terapeutica_inactivada', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($tecnica->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($tecnica->id, $auditoria->auditable_id);
        $this->assertSame(['activo' => true], $auditoria->valores_anteriores);
        $this->assertSame(['activo' => false], $auditoria->valores_nuevos);
    }

    public function test_reactivates_and_audits_real_change(): void
    {
        $tecnica = $this->tecnica(false);

        $actualizado = app(CambiarEstadoTecnicaTerapeuticaAction::class)
            ->ejecutar($tecnica, true);

        $this->assertTrue($actualizado->activo);
        $this->assertDatabaseHas('tecnicas_terapeuticas', ['id' => $tecnica->id, 'activo' => true]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('tecnica_terapeutica_reactivada', $auditoria->evento);
        $this->assertSame(['activo' => false], $auditoria->valores_anteriores);
        $this->assertSame(['activo' => true], $auditoria->valores_nuevos);
    }

    public function test_inactivating_already_inactive_is_idempotent(): void
    {
        $tecnica = $this->tecnica(false);

        $actualizado = app(CambiarEstadoTecnicaTerapeuticaAction::class)
            ->ejecutar($tecnica, false);

        $this->assertFalse($actualizado->activo);
        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseHas('tecnicas_terapeuticas', ['id' => $tecnica->id, 'activo' => false]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_reactivating_already_active_is_idempotent(): void
    {
        $tecnica = $this->tecnica(true);

        $actualizado = app(CambiarEstadoTecnicaTerapeuticaAction::class)
            ->ejecutar($tecnica, true);

        $this->assertTrue($actualizado->activo);
        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseHas('tecnicas_terapeuticas', ['id' => $tecnica->id, 'activo' => true]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_rolls_back_inactivation_when_audit_persistence_fails(): void
    {
        $this->assertRollback(true, false);
    }

    public function test_rolls_back_reactivation_when_audit_persistence_fails(): void
    {
        $this->assertRollback(false, true);
    }

    private function assertRollback(bool $estadoInicial, bool $objetivo): void
    {
        $tecnica = $this->tecnica($estadoInicial);
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(CambiarEstadoTecnicaTerapeuticaAction::class)
                ->ejecutar($tecnica, $objetivo);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseHas('tecnicas_terapeuticas', [
                'id' => $tecnica->id, 'activo' => $estadoInicial,
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
