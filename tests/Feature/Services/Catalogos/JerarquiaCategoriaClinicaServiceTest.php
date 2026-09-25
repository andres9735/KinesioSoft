<?php

namespace Tests\Feature\Services\Catalogos;

use App\Actions\Catalogos\ActualizarCategoriaClinicaAction;
use App\Actions\Catalogos\CrearCategoriaClinicaAction;
use App\Enums\RoleEnum;
use App\Models\CategoriaClinica;
use App\Models\User;
use App\Services\Catalogos\JerarquiaCategoriaClinicaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JerarquiaCategoriaClinicaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_a_root_category(): void
    {
        $this->service()->validarParaCrear('ANATOMICA', null);

        $this->addToAssertionCount(1);
    }

    public function test_accepts_an_active_parent_of_the_same_type(): void
    {
        $padre = $this->categoria('PADRE', 'Padre');

        $this->service()->validarParaCrear('ANATOMICA', $padre->id);

        $this->addToAssertionCount(1);
    }

    public function test_rejects_an_inactive_parent_as_a_new_parent(): void
    {
        $padre = $this->categoria('PADRE', 'Padre', 'ANATOMICA', false);

        $this->assertValidationError(
            fn () => $this->service()->validarParaCrear('ANATOMICA', $padre->id)
        );
    }

    public function test_rejects_a_parent_of_a_different_type(): void
    {
        $padre = $this->categoria('PADRE', 'Padre', 'FUNCIONAL');

        $this->assertValidationError(
            fn () => $this->service()->validarParaCrear('ANATOMICA', $padre->id)
        );
    }

    public function test_rejects_self_parenting(): void
    {
        $categoria = $this->categoria('CAT', 'Categoría');

        $this->assertValidationError(
            fn () => $this->service()->validarParaActualizar(
                $categoria,
                'ANATOMICA',
                $categoria->id
            )
        );
    }

    public function test_rejects_a_direct_cycle_a_b_a(): void
    {
        $a = $this->categoria('A', 'A');
        $b = $this->categoria('B', 'B', 'ANATOMICA', true, $a->id);

        $this->assertValidationError(
            fn () => $this->service()->validarParaActualizar($a, 'ANATOMICA', $b->id)
        );
    }

    public function test_rejects_an_indirect_cycle_a_b_c_a(): void
    {
        $a = $this->categoria('A', 'A');
        $b = $this->categoria('B', 'B', 'ANATOMICA', true, $a->id);
        $c = $this->categoria('C', 'C', 'ANATOMICA', true, $b->id);

        $this->assertValidationError(
            fn () => $this->service()->validarParaActualizar($a, 'ANATOMICA', $c->id)
        );
    }

    public function test_obtains_all_direct_and_indirect_descendants(): void
    {
        $a = $this->categoria('A', 'A');
        $b = $this->categoria('B', 'B', 'ANATOMICA', true, $a->id);
        $c = $this->categoria('C', 'C', 'ANATOMICA', true, $b->id);
        $d = $this->categoria('D', 'D', 'ANATOMICA', true, $a->id);
        $e = $this->categoria('E', 'E', 'ANATOMICA', true, $d->id);

        $ids = $this->service()->obtenerIdsDescendientes($a);
        sort($ids);

        $esperados = [$b->id, $c->id, $d->id, $e->id];
        sort($esperados);

        $this->assertSame($esperados, $ids);
    }

    public function test_edit_candidates_exclude_self_descendants_and_inactive_categories(): void
    {
        $a = $this->categoria('A', 'A');
        $b = $this->categoria('B', 'B', 'ANATOMICA', true, $a->id);
        $c = $this->categoria('C', 'C', 'ANATOMICA', true, $b->id);
        $inactiva = $this->categoria('I', 'Inactiva', 'ANATOMICA', false);
        $externa = $this->categoria('F', 'Externa');

        $ids = $this->service()->obtenerCandidatosParaEditar($a)->pluck('id');

        $this->assertFalse($ids->contains($a->id));
        $this->assertFalse($ids->contains($b->id));
        $this->assertFalse($ids->contains($c->id));
        $this->assertFalse($ids->contains($inactiva->id));
        $this->assertTrue($ids->contains($externa->id));
    }

    public function test_external_active_candidate_of_the_same_type_is_available(): void
    {
        $categoria = $this->categoria('CAT', 'Categoría');
        $externa = $this->categoria('EXT', 'Externa');
        $this->categoria('FUN', 'Funcional', 'FUNCIONAL');

        $ids = $this->service()
            ->obtenerCandidatosParaEditar($categoria, 'ANATOMICA')
            ->pluck('id')
            ->all();

        $this->assertSame([$externa->id], $ids);
    }

    public function test_can_preserve_its_current_inactive_historical_parent(): void
    {
        $padre = $this->categoria('PADRE', 'Padre', 'ANATOMICA', false);
        $hija = $this->categoria('HIJA', 'Hija', 'ANATOMICA', true, $padre->id);

        $this->service()->validarParaActualizar($hija, 'ANATOMICA', $padre->id);

        $this->addToAssertionCount(1);
    }

    public function test_inactive_historical_parent_is_not_a_new_general_candidate(): void
    {
        $padre = $this->categoria('PADRE', 'Padre', 'ANATOMICA', false);
        $hija = $this->categoria('HIJA', 'Hija', 'ANATOMICA', true, $padre->id);
        $otra = $this->categoria('OTRA', 'Otra');

        $this->assertFalse(
            $this->service()->obtenerCandidatosParaCrear()->contains('id', $padre->id)
        );
        $this->assertFalse(
            $this->service()->obtenerCandidatosParaEditar($otra)->contains('id', $padre->id)
        );
        $this->assertSame($padre->id, $hija->categoria_padre_id);
    }

    public function test_rejects_changing_to_another_inactive_parent(): void
    {
        $padreActual = $this->categoria('ACTUAL', 'Actual', 'ANATOMICA', false);
        $otroPadre = $this->categoria('OTRO', 'Otro', 'ANATOMICA', false);
        $hija = $this->categoria('HIJA', 'Hija', 'ANATOMICA', true, $padreActual->id);

        $this->assertValidationError(
            fn () => $this->service()->validarParaActualizar(
                $hija,
                'ANATOMICA',
                $otroPadre->id
            )
        );
    }

    public function test_rejects_changing_type_while_preserving_an_incompatible_parent(): void
    {
        $padre = $this->categoria('PADRE', 'Padre', 'ANATOMICA', false);
        $hija = $this->categoria('HIJA', 'Hija', 'ANATOMICA', true, $padre->id);

        $this->assertValidationError(
            fn () => $this->service()->validarParaActualizar(
                $hija,
                'FUNCIONAL',
                $padre->id
            )
        );
    }

    public function test_descendant_walk_stops_safely_if_historical_data_contains_a_cycle(): void
    {
        $a = $this->categoria('A', 'A');
        $b = $this->categoria('B', 'B', 'ANATOMICA', true, $a->id);
        $c = $this->categoria('C', 'C', 'ANATOMICA', true, $b->id);
        DB::table('categorias_clinicas')->where('id', $a->id)->update([
            'categoria_padre_id' => $c->id,
        ]);

        $ids = $this->service()->obtenerIdsDescendientes($a);
        sort($ids);

        $this->assertSame([$b->id, $c->id], $ids);
    }

    public function test_create_action_reuses_the_service_against_an_invalid_parent(): void
    {
        $padre = $this->categoria('PADRE', 'Padre', 'ANATOMICA', false);

        $this->assertValidationError(
            fn () => app(CrearCategoriaClinicaAction::class)->ejecutar([
                'codigo' => 'HIJA',
                'nombre' => 'Hija',
                'tipo' => 'ANATOMICA',
                'categoria_padre_id' => $padre->id,
            ])
        );
        $this->assertDatabaseCount('categorias_clinicas', 1);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_update_action_reuses_the_service_against_a_cycle(): void
    {
        $a = $this->categoria('A', 'A');
        $b = $this->categoria('B', 'B', 'ANATOMICA', true, $a->id);

        $this->assertValidationError(
            fn () => app(ActualizarCategoriaClinicaAction::class)->ejecutar($a, [
                'codigo' => 'A',
                'nombre' => 'A',
                'tipo' => 'ANATOMICA',
                'categoria_padre_id' => $b->id,
            ])
        );
        $this->assertNull($a->fresh()->categoria_padre_id);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_store_request_rejects_a_manipulated_inactive_parent(): void
    {
        $padre = $this->categoria('PADRE', 'Padre', 'ANATOMICA', false);
        $this->actingAs($this->admin());

        $this->from(route('catalogos.categorias.create'))
            ->post(route('catalogos.categorias.store'), [
                'codigo' => 'HIJA',
                'nombre' => 'Hija',
                'tipo' => 'ANATOMICA',
                'categoria_padre_id' => $padre->id,
            ])
            ->assertRedirect(route('catalogos.categorias.create'))
            ->assertSessionHasErrors('categoria_padre_id');

        $this->assertDatabaseCount('categorias_clinicas', 1);
    }

    public function test_update_request_rejects_a_manipulated_cycle(): void
    {
        $a = $this->categoria('A', 'A');
        $b = $this->categoria('B', 'B', 'ANATOMICA', true, $a->id);
        $this->actingAs($this->admin());

        $this->from(route('catalogos.categorias.edit', $a))
            ->put(route('catalogos.categorias.update', $a), [
                'codigo' => 'A',
                'nombre' => 'A',
                'tipo' => 'ANATOMICA',
                'categoria_padre_id' => $b->id,
            ])
            ->assertRedirect(route('catalogos.categorias.edit', $a))
            ->assertSessionHasErrors('categoria_padre_id');

        $this->assertNull($a->fresh()->categoria_padre_id);
    }

    public function test_edit_view_separates_the_current_inactive_parent_from_new_candidates(): void
    {
        $padre = $this->categoria('PADRE', 'Padre histórico', 'ANATOMICA', false);
        $hija = $this->categoria('HIJA', 'Hija', 'ANATOMICA', true, $padre->id);
        $descendiente = $this->categoria('DESC', 'Descendiente', 'ANATOMICA', true, $hija->id);
        $externa = $this->categoria('EXT', 'Externa');
        $this->actingAs($this->admin());

        $response = $this->get(route('catalogos.categorias.edit', $hija));

        $response->assertOk()
            ->assertSee('Padre actual — inactivo')
            ->assertViewHas('padreHistoricoInactivo', fn ($categoria) => $categoria->is($padre));

        $idsCandidatos = $response->viewData('categoriasPadre')->pluck('id');

        $this->assertFalse($idsCandidatos->contains($padre->id));
        $this->assertFalse($idsCandidatos->contains($hija->id));
        $this->assertFalse($idsCandidatos->contains($descendiente->id));
        $this->assertTrue($idsCandidatos->contains($externa->id));
    }

    private function service(): JerarquiaCategoriaClinicaService
    {
        return app(JerarquiaCategoriaClinicaService::class);
    }

    private function categoria(
        string $codigo,
        string $nombre,
        string $tipo = 'ANATOMICA',
        bool $activo = true,
        ?int $padreId = null
    ): CategoriaClinica {
        return CategoriaClinica::create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'categoria_padre_id' => $padreId,
            'activo' => $activo,
        ]);
    }

    private function admin(): User
    {
        Role::findOrCreate(RoleEnum::ADMINISTRADOR->value, 'web');

        $user = User::factory()->create(['activo' => true]);
        $user->assignRole(RoleEnum::ADMINISTRADOR->value);

        return $user;
    }

    private function assertValidationError(callable $operacion): void
    {
        try {
            $operacion();
            $this->fail('Se esperaba un error de validación jerárquica.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('categoria_padre_id', $exception->errors());
        }
    }
}
