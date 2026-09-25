<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CambiarEstadoTipoEquipoTerapeuticoAction;
use App\Models\Auditoria;
use App\Models\CategoriaClinica;
use App\Models\TipoEquipoTerapeutico;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CambiarEstadoTipoEquipoTerapeuticoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactivates_and_audits_real_change(): void
    {
        $tipoEquipo = $this->tipoEquipo($this->categoria(), true);

        $actualizado = app(CambiarEstadoTipoEquipoTerapeuticoAction::class)
            ->ejecutar($tipoEquipo, false);

        $this->assertFalse($actualizado->activo);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('tipo_equipo_terapeutico_inactivado', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame(['activo' => true], $auditoria->valores_anteriores);
        $this->assertSame(['activo' => false], $auditoria->valores_nuevos);
    }

    public function test_reactivates_with_active_equipment_category_and_audits(): void
    {
        $tipoEquipo = $this->tipoEquipo($this->categoria(), false);

        $actualizado = app(CambiarEstadoTipoEquipoTerapeuticoAction::class)
            ->ejecutar($tipoEquipo, true);

        $this->assertTrue($actualizado->activo);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('tipo_equipo_terapeutico_reactivado', $auditoria->evento);
        $this->assertSame(['activo' => false], $auditoria->valores_anteriores);
        $this->assertSame(['activo' => true], $auditoria->valores_nuevos);
    }

    public function test_reactivation_rejects_inactive_and_each_wrong_type_category(): void
    {
        $categorias = [
            $this->categoria('EQUIPAMIENTO', false),
            $this->categoria('ANATOMICA', true),
            $this->categoria('FUNCIONAL', true),
            $this->categoria('TERAPEUTICA', true),
        ];

        foreach ($categorias as $indice => $categoria) {
            $tipoEquipo = $this->tipoEquipo($categoria, false, 'EQ-'.$indice);

            try {
                app(CambiarEstadoTipoEquipoTerapeuticoAction::class)
                    ->ejecutar($tipoEquipo, true);
                $this->fail('Se esperaba el rechazo de la reactivación.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('activo', $exception->errors());
            }

            $this->assertDatabaseHas('tipos_equipo_terapeutico', [
                'id' => $tipoEquipo->id, 'activo' => false,
            ]);
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_inactive_target_is_idempotent_before_validating_invalid_historical_category(): void
    {
        $tipoEquipo = $this->tipoEquipo($this->categoria('EQUIPAMIENTO', false), false);

        $actualizado = app(CambiarEstadoTipoEquipoTerapeuticoAction::class)
            ->ejecutar($tipoEquipo, false);

        $this->assertFalse($actualizado->activo);
        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_active_target_is_idempotent_without_audit(): void
    {
        $tipoEquipo = $this->tipoEquipo($this->categoria('ANATOMICA', true), true);

        $actualizado = app(CambiarEstadoTipoEquipoTerapeuticoAction::class)
            ->ejecutar($tipoEquipo, true);

        $this->assertTrue($actualizado->activo);
        $this->assertFalse($actualizado->wasChanged());
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
        $tipoEquipo = $this->tipoEquipo($this->categoria(), $estadoInicial);
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(CambiarEstadoTipoEquipoTerapeuticoAction::class)
                ->ejecutar($tipoEquipo, $objetivo);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseHas('tipos_equipo_terapeutico', [
                'id' => $tipoEquipo->id, 'activo' => $estadoInicial,
            ]);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function categoria(
        string $tipo = 'EQUIPAMIENTO',
        bool $activo = true
    ): CategoriaClinica {
        static $secuencia = 0;
        $secuencia++;

        return CategoriaClinica::create([
            'codigo' => 'CAT-'.$secuencia,
            'nombre' => 'Categoría '.$secuencia,
            'tipo' => $tipo,
            'activo' => $activo,
        ]);
    }

    private function tipoEquipo(
        CategoriaClinica $categoria,
        bool $activo,
        string $codigo = 'EQ-US'
    ): TipoEquipoTerapeutico {
        return TipoEquipoTerapeutico::create([
            'codigo' => $codigo,
            'nombre' => $codigo,
            'categoria_clinica_id' => $categoria->id,
            'activo' => $activo,
        ]);
    }
}
