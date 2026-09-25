<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CambiarEstadoCategoriaClinicaAction;
use App\Enums\DificultadEjercicioEnum;
use App\Models\Auditoria;
use App\Models\CategoriaClinica;
use App\Models\EjercicioDomiciliario;
use App\Models\TipoEquipoTerapeutico;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CambiarEstadoCategoriaClinicaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactivates_and_audits_real_change(): void
    {
        $categoria = $this->categoria(true);

        $actualizada = app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, false);

        $this->assertFalse($actualizada->activo);
        $this->assertDatabaseHas('categorias_clinicas', ['id' => $categoria->id, 'activo' => false]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('categoria_clinica_inactivada', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame($categoria->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($categoria->id, $auditoria->auditable_id);
        $this->assertSame(['activo' => true], $auditoria->valores_anteriores);
        $this->assertSame(['activo' => false], $auditoria->valores_nuevos);
    }

    public function test_reactivates_and_audits_real_change(): void
    {
        $categoria = $this->categoria(false);

        $actualizada = app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, true);

        $this->assertTrue($actualizada->activo);
        $this->assertDatabaseHas('categorias_clinicas', ['id' => $categoria->id, 'activo' => true]);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('categoria_clinica_reactivada', $auditoria->evento);
        $this->assertSame(['activo' => false], $auditoria->valores_anteriores);
        $this->assertSame(['activo' => true], $auditoria->valores_nuevos);
    }

    public function test_inactivating_already_inactive_is_idempotent(): void
    {
        $categoria = $this->categoria(false);
        $actualizada = app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, false);

        $this->assertFalse($actualizada->activo);
        $this->assertFalse($actualizada->wasChanged());
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_reactivating_already_active_is_idempotent(): void
    {
        $categoria = $this->categoria(true);
        $actualizada = app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, true);

        $this->assertTrue($actualizada->activo);
        $this->assertFalse($actualizada->wasChanged());
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_active_child_blocks_inactivation_without_change_or_audit(): void
    {
        $padre = $this->categoria(true, 'PADRE');
        $this->categoria(true, 'HIJA', 'ANATOMICA', $padre->id);

        $this->assertDependencyRejected(
            fn () => app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($padre, false),
            'hijas activas'
        );

        $this->assertDatabaseHas('categorias_clinicas', ['id' => $padre->id, 'activo' => true]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_inactive_child_does_not_block_inactivation(): void
    {
        $padre = $this->categoria(true, 'PADRE');
        $this->categoria(false, 'HIJA', 'ANATOMICA', $padre->id);

        $actualizada = app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($padre, false);

        $this->assertFalse($actualizada->activo);
        $this->assertSame('categoria_clinica_inactivada', Auditoria::query()->sole()->evento);
    }

    public function test_only_active_anatomical_category_of_active_exercise_blocks_inactivation(): void
    {
        $categoria = $this->categoria(true, 'ANAT-1');
        $ejercicio = $this->ejercicio(true);
        $ejercicio->categorias()->attach($categoria->id);

        $this->assertDependencyRejected(
            fn () => app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, false),
            'ejercicio activo'
        );

        $this->assertDatabaseHas('categorias_clinicas', ['id' => $categoria->id, 'activo' => true]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_other_active_anatomical_category_allows_inactivation(): void
    {
        $categoria = $this->categoria(true, 'ANAT-1');
        $alternativa = $this->categoria(true, 'ANAT-2');
        $ejercicio = $this->ejercicio(true);
        $ejercicio->categorias()->attach([$categoria->id, $alternativa->id]);

        $actualizada = app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, false);

        $this->assertFalse($actualizada->activo);
        $this->assertDatabaseHas('categorias_clinicas', ['id' => $alternativa->id, 'activo' => true]);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_inactive_anatomical_alternative_does_not_allow_inactivation(): void
    {
        $categoria = $this->categoria(true, 'ANAT-1');
        $alternativa = $this->categoria(false, 'ANAT-2');
        $ejercicio = $this->ejercicio(true);
        $ejercicio->categorias()->attach([$categoria->id, $alternativa->id]);

        $this->assertDependencyRejected(
            fn () => app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, false),
            'ejercicio activo'
        );

        $this->assertDatabaseHas('categorias_clinicas', ['id' => $categoria->id, 'activo' => true]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_inactive_exercise_does_not_block_anatomical_category_inactivation(): void
    {
        $categoria = $this->categoria(true, 'ANAT-1');
        $ejercicio = $this->ejercicio(false);
        $ejercicio->categorias()->attach($categoria->id);

        $actualizada = app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, false);

        $this->assertFalse($actualizada->activo);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_non_anatomical_category_does_not_apply_minimum_anatomical_rule(): void
    {
        $categoria = $this->categoria(true, 'FUNC-1', 'FUNCIONAL');
        $ejercicio = $this->ejercicio(true);
        $ejercicio->categorias()->attach($categoria->id);

        $actualizada = app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, false);

        $this->assertFalse($actualizada->activo);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_active_equipment_type_blocks_inactivation_without_change_or_audit(): void
    {
        $categoria = $this->categoria(true, 'EQUIP-1', 'EQUIPAMIENTO');
        $this->tipoEquipo($categoria, true);

        $this->assertDependencyRejected(
            fn () => app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, false),
            'tipos de equipo terapéutico activos'
        );

        $this->assertDatabaseHas('categorias_clinicas', ['id' => $categoria->id, 'activo' => true]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_inactive_equipment_type_does_not_block_inactivation(): void
    {
        $categoria = $this->categoria(true, 'EQUIP-1', 'EQUIPAMIENTO');
        $this->tipoEquipo($categoria, false);

        $actualizada = app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, false);

        $this->assertFalse($actualizada->activo);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_reactivates_child_with_active_parent_of_same_type(): void
    {
        $padre = $this->categoria(true, 'PADRE');
        $hija = $this->categoria(false, 'HIJA', 'ANATOMICA', $padre->id);

        $actualizada = app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($hija, true);

        $this->assertTrue($actualizada->activo);
        $this->assertSame('categoria_clinica_reactivada', Auditoria::query()->sole()->evento);
    }

    public function test_inactive_parent_blocks_child_reactivation_without_change_or_audit(): void
    {
        $padre = $this->categoria(false, 'PADRE');
        $hija = $this->categoria(false, 'HIJA', 'ANATOMICA', $padre->id);

        $this->assertDependencyRejected(
            fn () => app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($hija, true),
            'padre está inactiva'
        );

        $this->assertDatabaseHas('categorias_clinicas', ['id' => $hija->id, 'activo' => false]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_parent_with_different_type_blocks_child_reactivation(): void
    {
        $padre = $this->categoria(true, 'PADRE', 'FUNCIONAL');
        $hija = $this->categoria(false, 'HIJA', 'ANATOMICA', $padre->id);

        $this->assertDependencyRejected(
            fn () => app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($hija, true),
            'tipo distinto'
        );

        $this->assertDatabaseHas('categorias_clinicas', ['id' => $hija->id, 'activo' => false]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_already_inactive_remains_idempotent_even_with_active_dependencies(): void
    {
        $padre = $this->categoria(false, 'PADRE');
        $this->categoria(true, 'HIJA', 'ANATOMICA', $padre->id);

        $actualizada = app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($padre, false);

        $this->assertFalse($actualizada->activo);
        $this->assertFalse($actualizada->wasChanged());
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_already_active_remains_idempotent_even_with_invalid_parent(): void
    {
        $padre = $this->categoria(false, 'PADRE');
        $hija = $this->categoria(true, 'HIJA', 'ANATOMICA', $padre->id);

        $actualizada = app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($hija, true);

        $this->assertTrue($actualizada->activo);
        $this->assertFalse($actualizada->wasChanged());
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
        $categoria = $this->categoria($estadoInicial);
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, $objetivo);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseHas('categorias_clinicas', [
                'id' => $categoria->id, 'activo' => $estadoInicial,
            ]);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function categoria(
        bool $activo,
        string $codigo = 'CAT-001',
        string $tipo = 'ANATOMICA',
        ?int $padreId = null
    ): CategoriaClinica {
        return CategoriaClinica::create([
            'codigo' => $codigo, 'nombre' => $codigo, 'tipo' => $tipo,
            'categoria_padre_id' => $padreId, 'activo' => $activo,
        ]);
    }

    private function ejercicio(bool $activo): EjercicioDomiciliario
    {
        return EjercicioDomiciliario::create([
            'codigo' => 'EJ-001',
            'nombre' => 'Ejercicio de prueba',
            'descripcion' => 'Descripción de prueba',
            'dificultad_base' => DificultadEjercicioEnum::BAJA,
            'activo' => $activo,
        ]);
    }

    private function tipoEquipo(
        CategoriaClinica $categoriaClinica,
        bool $activo
    ): TipoEquipoTerapeutico {
        return TipoEquipoTerapeutico::create([
            'codigo' => 'EQ-001',
            'nombre' => 'Equipo de prueba',
            'categoria_clinica_id' => $categoriaClinica->id,
            'activo' => $activo,
        ]);
    }

    private function assertDependencyRejected(callable $operation, string $messageFragment): void
    {
        try {
            $operation();
            $this->fail('Se esperaba el rechazo por una dependencia de la categoría clínica.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                $messageFragment,
                implode(' ', $exception->errors()['activo'] ?? [])
            );
        }
    }
}
