<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CambiarEstadoDiagnosticoClinicoAction;
use App\Models\Auditoria;
use App\Models\DiagnosticoClinico;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CambiarEstadoDiagnosticoClinicoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactivates_and_audits_real_change(): void
    {
        $diagnostico = $this->diagnostico(true);

        $actualizado = app(CambiarEstadoDiagnosticoClinicoAction::class)
            ->ejecutar($diagnostico, false);

        $this->assertFalse($actualizado->activo);
        $this->assertDatabaseHas('diagnosticos_clinicos', ['id' => $diagnostico->id, 'activo' => false]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('diagnostico_clinico_inactivado', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($diagnostico->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($diagnostico->id, $auditoria->auditable_id);
        $this->assertSame(['activo' => true], $auditoria->valores_anteriores);
        $this->assertSame(['activo' => false], $auditoria->valores_nuevos);
    }

    public function test_reactivates_and_audits_real_change(): void
    {
        $diagnostico = $this->diagnostico(false);

        $actualizado = app(CambiarEstadoDiagnosticoClinicoAction::class)
            ->ejecutar($diagnostico, true);

        $this->assertTrue($actualizado->activo);
        $this->assertDatabaseHas('diagnosticos_clinicos', ['id' => $diagnostico->id, 'activo' => true]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('diagnostico_clinico_reactivado', $auditoria->evento);
        $this->assertSame(['activo' => false], $auditoria->valores_anteriores);
        $this->assertSame(['activo' => true], $auditoria->valores_nuevos);
    }

    public function test_inactivating_already_inactive_is_idempotent(): void
    {
        $diagnostico = $this->diagnostico(false);

        $actualizado = app(CambiarEstadoDiagnosticoClinicoAction::class)
            ->ejecutar($diagnostico, false);

        $this->assertFalse($actualizado->activo);
        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseHas('diagnosticos_clinicos', ['id' => $diagnostico->id, 'activo' => false]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_reactivating_already_active_is_idempotent(): void
    {
        $diagnostico = $this->diagnostico(true);

        $actualizado = app(CambiarEstadoDiagnosticoClinicoAction::class)
            ->ejecutar($diagnostico, true);

        $this->assertTrue($actualizado->activo);
        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseHas('diagnosticos_clinicos', ['id' => $diagnostico->id, 'activo' => true]);
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
        $diagnostico = $this->diagnostico($estadoInicial);
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(CambiarEstadoDiagnosticoClinicoAction::class)
                ->ejecutar($diagnostico, $objetivo);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseHas('diagnosticos_clinicos', [
                'id' => $diagnostico->id, 'activo' => $estadoInicial,
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
