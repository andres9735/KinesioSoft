<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CambiarEstadoPruebaFuncionalAction;
use App\Models\Auditoria;
use App\Models\PruebaFuncional;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CambiarEstadoPruebaFuncionalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactivates_and_audits_real_change(): void
    {
        $prueba = $this->prueba(true);

        $actualizado = app(CambiarEstadoPruebaFuncionalAction::class)
            ->ejecutar($prueba, false);

        $this->assertFalse($actualizado->activo);
        $this->assertDatabaseHas('pruebas_funcionales', ['id' => $prueba->id, 'activo' => false]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('prueba_funcional_inactivada', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($prueba->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($prueba->id, $auditoria->auditable_id);
        $this->assertSame(['activo' => true], $auditoria->valores_anteriores);
        $this->assertSame(['activo' => false], $auditoria->valores_nuevos);
    }

    public function test_reactivates_and_audits_real_change(): void
    {
        $prueba = $this->prueba(false);

        $actualizado = app(CambiarEstadoPruebaFuncionalAction::class)
            ->ejecutar($prueba, true);

        $this->assertTrue($actualizado->activo);
        $this->assertDatabaseHas('pruebas_funcionales', ['id' => $prueba->id, 'activo' => true]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('prueba_funcional_reactivada', $auditoria->evento);
        $this->assertSame(['activo' => false], $auditoria->valores_anteriores);
        $this->assertSame(['activo' => true], $auditoria->valores_nuevos);
    }

    public function test_inactivating_already_inactive_is_idempotent(): void
    {
        $prueba = $this->prueba(false);

        $actualizado = app(CambiarEstadoPruebaFuncionalAction::class)
            ->ejecutar($prueba, false);

        $this->assertFalse($actualizado->activo);
        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseHas('pruebas_funcionales', ['id' => $prueba->id, 'activo' => false]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_reactivating_already_active_is_idempotent(): void
    {
        $prueba = $this->prueba(true);

        $actualizado = app(CambiarEstadoPruebaFuncionalAction::class)
            ->ejecutar($prueba, true);

        $this->assertTrue($actualizado->activo);
        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseHas('pruebas_funcionales', ['id' => $prueba->id, 'activo' => true]);
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
        $prueba = $this->prueba($estadoInicial);
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(CambiarEstadoPruebaFuncionalAction::class)
                ->ejecutar($prueba, $objetivo);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseHas('pruebas_funcionales', [
                'id' => $prueba->id, 'activo' => $estadoInicial,
            ]);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function prueba(bool $activo): PruebaFuncional
    {
        return PruebaFuncional::create([
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEST-001',
            'nombre' => 'Test funcional', 'descripcion' => 'Medición funcional',
            'activo' => $activo,
        ]);
    }
}
