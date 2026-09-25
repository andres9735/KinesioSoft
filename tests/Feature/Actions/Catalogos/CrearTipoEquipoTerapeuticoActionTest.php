<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CrearTipoEquipoTerapeuticoAction;
use App\Models\Auditoria;
use App\Models\CategoriaClinica;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CrearTipoEquipoTerapeuticoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_active_record_with_valid_category_and_audits_persisted_values(): void
    {
        $categoria = $this->categoria('EQUIPAMIENTO', true);
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $tipoEquipo = app(CrearTipoEquipoTerapeuticoAction::class)->ejecutar([
            'codigo' => 'EQ-US',
            'nombre' => 'Ultrasonido terapéutico',
            'descripcion' => 'Equipo por ultrasonido',
            'categoria_clinica_id' => $categoria->id,
            'activo' => false,
        ]);

        $this->assertTrue($tipoEquipo->exists);
        $this->assertTrue($tipoEquipo->activo);
        $this->assertSame($categoria->id, $tipoEquipo->categoriaClinica->id);
        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $tipoEquipo->id,
            'codigo' => 'EQ-US',
            'nombre' => 'Ultrasonido terapéutico',
            'descripcion' => 'Equipo por ultrasonido',
            'categoria_clinica_id' => $categoria->id,
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();
        $this->assertSame('tipo_equipo_terapeutico_creado', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($tipoEquipo->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($tipoEquipo->id, $auditoria->auditable_id);
        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertNull($auditoria->valores_anteriores);
        $this->assertSame([
            'codigo' => 'EQ-US',
            'nombre' => 'Ultrasonido terapéutico',
            'descripcion' => 'Equipo por ultrasonido',
            'categoria_clinica_id' => $categoria->id,
            'activo' => true,
        ], $auditoria->valores_nuevos);
    }

    public function test_direct_action_rejects_each_invalid_category_without_persistence_or_audit(): void
    {
        $categorias = [
            $this->categoria('ANATOMICA', true),
            $this->categoria('FUNCIONAL', true),
            $this->categoria('TERAPEUTICA', true),
            $this->categoria('EQUIPAMIENTO', false),
        ];

        foreach ($categorias as $indice => $categoria) {
            try {
                app(CrearTipoEquipoTerapeuticoAction::class)->ejecutar([
                    'codigo' => 'INVALIDO-'.$indice,
                    'nombre' => 'Inválido',
                    'categoria_clinica_id' => $categoria->id,
                ]);
                $this->fail('Se esperaba el rechazo de la categoría inválida.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('categoria_clinica_id', $exception->errors());
            }
        }

        $this->assertDatabaseCount('tipos_equipo_terapeutico', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_direct_action_rejects_nonexistent_category(): void
    {
        $this->expectException(ValidationException::class);

        try {
            app(CrearTipoEquipoTerapeuticoAction::class)->ejecutar([
                'codigo' => 'EQ-X',
                'nombre' => 'Inexistente',
                'categoria_clinica_id' => 999,
            ]);
        } finally {
            $this->assertDatabaseCount('tipos_equipo_terapeutico', 0);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public function test_rolls_back_creation_when_audit_persistence_fails(): void
    {
        $categoria = $this->categoria('EQUIPAMIENTO', true);
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(CrearTipoEquipoTerapeuticoAction::class)->ejecutar([
                'codigo' => 'EQ-ROLLBACK',
                'nombre' => 'Revertido',
                'categoria_clinica_id' => $categoria->id,
            ]);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseCount('tipos_equipo_terapeutico', 0);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function categoria(string $tipo, bool $activo): CategoriaClinica
    {
        static $secuencia = 0;
        $secuencia++;

        return CategoriaClinica::create([
            'codigo' => 'CAT-'.$secuencia,
            'nombre' => 'Categoría '.$secuencia,
            'tipo' => $tipo,
            'activo' => $activo,
        ]);
    }
}
