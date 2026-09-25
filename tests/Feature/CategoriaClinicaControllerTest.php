<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\CategoriaClinica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CategoriaClinicaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    public function test_admin_sees_empty_index_and_create_form_without_state_field(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->get(route('catalogos.categorias.index'))
            ->assertOk()->assertViewIs('catalogos.categorias-clinicas.index')
            ->assertSee('No hay categorías clínicas registradas');

        $this->get(route('catalogos.categorias.create'))
            ->assertOk()->assertViewIs('catalogos.categorias-clinicas.create')
            ->assertSee('Nueva categoría clínica')
            ->assertSee('name="tipo"', false)
            ->assertSee('name="categoria_padre_id"', false)
            ->assertSee('Sin categoría padre')
            ->assertSee('ANATOMICA')->assertSee('FUNCIONAL')
            ->assertSee('TERAPEUTICA')->assertSee('EQUIPAMIENTO')
            ->assertDontSee('name="activo"', false);

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_superadmin_can_access_catalog_and_create_root(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::SUPERADMIN));

        $this->get(route('catalogos.categorias.index'))->assertOk();
        $this->get(route('catalogos.categorias.create'))->assertOk();
        $this->post(route('catalogos.categorias.store'), $this->payload())
            ->assertRedirect(route('catalogos.categorias.index'));

        $this->assertDatabaseHas('categorias_clinicas', [
            'codigo' => 'CAT-001', 'categoria_padre_id' => null, 'activo' => true,
        ]);
    }

    public function test_kinesiologo_and_paciente_cannot_access_or_write_catalog(): void
    {
        foreach ([RoleEnum::KINESIOLOGO, RoleEnum::PACIENTE] as $role) {
            $this->actingAs($this->userWithRole($role));
            $destino = $role === RoleEnum::KINESIOLOGO
                ? route('perfil.kinesiologa.completar')
                : route('perfil.completar');

            $this->get(route('catalogos.categorias.index'))->assertRedirect($destino);
            $this->get(route('catalogos.categorias.create'))->assertRedirect($destino);
            $this->post(route('catalogos.categorias.store'), $this->payload())->assertRedirect($destino);
        }

        $this->assertDatabaseCount('categorias_clinicas', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_lists_parent_relation_columns_and_actions_for_both_states(): void
    {
        $padre = $this->categoria('PADRE', 'Columna');
        $hija = $this->categoria('HIJA', 'Lumbar', 'ANATOMICA', false, $padre->id);

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.categorias.index'));

        $response->assertOk()->assertSee('Código')->assertSee('Nombre')
            ->assertSee('Tipo')->assertSee('Categoría padre')->assertSee('Estado')
            ->assertSee('Acciones')->assertSee('PADRE')->assertSee('Columna')
            ->assertSee('HIJA')->assertSee('Lumbar')->assertSee('Categoría raíz')
            ->assertSee('hidden overflow-x-auto md:block')->assertSee('md:hidden')
            ->assertSee('Limpiar filtros')->assertSee('ANATOMICA · PADRE · Columna')
            ->assertSee(route('catalogos.categorias.inactivar', $padre))
            ->assertSee(route('catalogos.categorias.reactivar', $hija));

        $this->assertTrue($response->viewData('categorias')->firstWhere('id', $hija->id)->relationLoaded('padre'));
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_searches_only_code_or_name_with_state_filter(): void
    {
        $padre = $this->categoria('PADRE', 'Columna');
        $this->categoria('HIJA', 'Lumbar', 'ANATOMICA', false, $padre->id);
        $this->categoria('OTRA', 'Pierna');
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->assertSame(['HIJA'], $this->get(route('catalogos.categorias.index', ['q' => 'HIJA']))
            ->viewData('categorias')->pluck('codigo')->all());
        $this->assertSame(['OTRA'], $this->get(route('catalogos.categorias.index', ['q' => 'Pierna']))
            ->viewData('categorias')->pluck('codigo')->all());
        $this->assertSame(0, $this->get(route('catalogos.categorias.index', ['q' => 'Columna', 'estado' => 'inactivos']))
            ->viewData('categorias')->total());
        $this->assertSame(['HIJA'], $this->get(route('catalogos.categorias.index', ['q' => 'Lumbar', 'estado' => 'inactivos']))
            ->viewData('categorias')->pluck('codigo')->all());
    }

    public function test_index_filters_todos_activos_inactivos_and_defaults_to_todos(): void
    {
        $this->categoria('A-01', 'Activa');
        $this->categoria('I-01', 'Inactiva', 'ANATOMICA', false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach ([
            'todos' => ['A-01', 'I-01'],
            'activos' => ['A-01'],
            'inactivos' => ['I-01'],
        ] as $estado => $codigos) {
            $response = $this->get(route('catalogos.categorias.index', ['estado' => $estado]));
            $response->assertOk();
            $this->assertSame($codigos, $response->viewData('categorias')->pluck('codigo')->sort()->values()->all());
        }

        $this->assertSame(2, $this->get(route('catalogos.categorias.index'))
            ->viewData('categorias')->total());
    }

    public function test_index_paginates_ten_and_keeps_query_parameters(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->categoria(sprintf('CAT-%02d', $i), sprintf('Categoría %02d', $i));
        }

        $categorias = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.categorias.index', ['q' => 'Categoría', 'estado' => 'activos']))
            ->viewData('categorias');

        $this->assertSame(10, $categorias->count());
        $this->assertSame(11, $categorias->total());
        $this->assertStringContainsString('q=Categor%C3%ADa', $categorias->nextPageUrl());
        $this->assertStringContainsString('estado=activos', $categorias->nextPageUrl());
    }

    public function test_store_root_is_active_and_ignores_submitted_state(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->post(route('catalogos.categorias.store'), $this->payload(['activo' => false]))
            ->assertRedirect(route('catalogos.categorias.index'))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $categoria = CategoriaClinica::query()->sole();
        $this->assertNull($categoria->categoria_padre_id);
        $this->assertNull($categoria->padre);
        $this->assertTrue($categoria->activo);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_store_child_with_active_parent_of_same_type_shows_relationship(): void
    {
        $padre = $this->categoria('PADRE', 'Columna');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->post(route('catalogos.categorias.store'), $this->payload([
                'codigo' => 'HIJA', 'nombre' => 'Lumbar', 'categoria_padre_id' => $padre->id,
            ]))->assertRedirect(route('catalogos.categorias.index'))
            ->assertSessionHasNoErrors();

        $hija = CategoriaClinica::query()->where('codigo', 'HIJA')->firstOrFail();
        $this->assertSame($padre->id, $hija->padre->id);
        $this->assertSame('Lumbar', $padre->hijas()->sole()->nombre);
        $this->get(route('catalogos.categorias.index'))->assertSee('PADRE · Columna');
        $this->get(route('catalogos.categorias.show', $hija))->assertSee('PADRE · Columna');
    }

    public function test_store_accepts_all_four_enum_values(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach (['ANATOMICA', 'FUNCIONAL', 'TERAPEUTICA', 'EQUIPAMIENTO'] as $indice => $tipo) {
            $this->post(route('catalogos.categorias.store'), $this->payload([
                'codigo' => 'CAT-'.$indice, 'tipo' => $tipo,
            ]))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('categorias_clinicas', ['codigo' => 'CAT-'.$indice, 'tipo' => $tipo]);
        }
    }

    public function test_store_rejects_invalid_type_and_nonexistent_parent(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->from(route('catalogos.categorias.create'))
            ->post(route('catalogos.categorias.store'), $this->payload(['tipo' => 'OTRA']))
            ->assertRedirect(route('catalogos.categorias.create'))->assertSessionHasErrors('tipo');
        $this->from(route('catalogos.categorias.create'))
            ->post(route('catalogos.categorias.store'), $this->payload(['categoria_padre_id' => 999]))
            ->assertRedirect(route('catalogos.categorias.create'))->assertSessionHasErrors('categoria_padre_id');

        $this->assertDatabaseCount('categorias_clinicas', 0);
    }

    public function test_store_rejects_duplicate_code_globally_even_with_another_type(): void
    {
        $this->categoria('CAT-001', 'Existente', 'FUNCIONAL');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.categorias.create'))
            ->post(route('catalogos.categorias.store'), $this->payload(['nombre' => 'Duplicada']))
            ->assertRedirect(route('catalogos.categorias.create'))->assertSessionHasErrors('codigo');

        $this->assertDatabaseCount('categorias_clinicas', 1);
    }

    public function test_show_root_child_and_direct_children_is_read_only(): void
    {
        $padre = $this->categoria('PADRE', 'Columna');
        $hija = $this->categoria('HIJA', 'Lumbar', 'ANATOMICA', false, $padre->id);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->get(route('catalogos.categorias.show', $padre))
            ->assertOk()->assertViewIs('catalogos.categorias-clinicas.show')
            ->assertSee('Sin categoría padre')->assertSee('HIJA · Lumbar')
            ->assertSee('Activo');
        $this->get(route('catalogos.categorias.show', $hija))
            ->assertOk()->assertSee('PADRE · Columna')->assertSee('Inactivo');
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_edit_active_and_inactive_without_state_field(): void
    {
        $activo = $this->categoria('A-01', 'Activa');
        $inactivo = $this->categoria('I-01', 'Inactiva', 'ANATOMICA', false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach ([$activo, $inactivo] as $categoria) {
            $this->get(route('catalogos.categorias.edit', $categoria))
                ->assertOk()->assertViewIs('catalogos.categorias-clinicas.edit')
                ->assertSee($categoria->codigo)->assertSee('name="categoria_padre_id"', false)
                ->assertDontSee('name="activo"', false);
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_update_changes_base_fields_and_preserves_active_state(): void
    {
        $padre = $this->categoria('PADRE', 'Columna');
        $categoria = $this->categoria('CAT-001', 'Original');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.categorias.update', $categoria), $this->payload([
                'nombre' => 'Revisada', 'categoria_padre_id' => $padre->id, 'activo' => false,
            ]))->assertRedirect(route('catalogos.categorias.index'))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $categoria->id, 'nombre' => 'Revisada',
            'categoria_padre_id' => $padre->id, 'activo' => true,
        ]);
    }

    public function test_update_inactive_child_can_become_root_without_reactivation(): void
    {
        $padre = $this->categoria('PADRE', 'Columna');
        $hija = $this->categoria('HIJA', 'Lumbar', 'ANATOMICA', false, $padre->id);

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.categorias.update', $hija), $this->payload([
                'codigo' => 'HIJA', 'nombre' => 'Lumbar', 'categoria_padre_id' => null,
                'activo' => true,
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $hija->id, 'categoria_padre_id' => null, 'activo' => false,
        ]);
    }

    public function test_update_allows_own_code_and_rejects_another_code(): void
    {
        $propia = $this->categoria('CAT-001', 'Propia');
        $otra = $this->categoria('CAT-002', 'Otra', 'FUNCIONAL');
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->put(route('catalogos.categorias.update', $propia), $this->payload([
            'nombre' => 'Propia revisada',
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $propia->id, 'codigo' => 'CAT-001', 'nombre' => 'Propia revisada',
        ]);

        $this->from(route('catalogos.categorias.edit', $propia))
            ->put(route('catalogos.categorias.update', $propia), $this->payload([
                'codigo' => $otra->codigo,
            ]))->assertRedirect(route('catalogos.categorias.edit', $propia))
            ->assertSessionHasErrors('codigo');
        $this->assertDatabaseHas('categorias_clinicas', ['id' => $propia->id, 'codigo' => 'CAT-001']);
    }

    public function test_unchanged_http_update_does_not_audit(): void
    {
        $categoria = $this->categoria('CAT-001', 'Columna');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.categorias.update', $categoria), $this->payload([
                'activo' => false,
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categorias_clinicas', ['id' => $categoria->id, 'activo' => true]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_inactivar_reactivar_and_idempotent_flashes_use_explicit_routes(): void
    {
        $categoria = $this->categoria('CAT-001', 'Columna');
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->patch(route('catalogos.categorias.inactivar', $categoria))
            ->assertRedirect(route('catalogos.categorias.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('categorias_clinicas', ['id' => $categoria->id, 'activo' => false]);

        $this->patch(route('catalogos.categorias.inactivar', $categoria))
            ->assertSessionHas('warning');

        $this->patch(route('catalogos.categorias.reactivar', $categoria))
            ->assertRedirect(route('catalogos.categorias.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('categorias_clinicas', ['id' => $categoria->id, 'activo' => true]);

        $this->patch(route('catalogos.categorias.reactivar', $categoria))
            ->assertSessionHas('warning');
        $this->assertDatabaseCount('auditorias', 2);
    }

    public function test_inactivar_parent_with_active_child_redirects_with_visible_error_without_changes_or_audit(): void
    {
        $padre = $this->categoria('PADRE', 'Columna');
        $hija = $this->categoria('HIJA', 'Lumbar', 'ANATOMICA', true, $padre->id);

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->patch(route('catalogos.categorias.inactivar', $padre));

        $response
            ->assertRedirect(route('catalogos.categorias.index'))
            ->assertSessionHas(
                'error',
                'No se puede inactivar la categoría clínica porque tiene categorías hijas activas.'
            )
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $padre->id,
            'activo' => true,
        ]);
        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $hija->id,
            'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create(['activo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function categoria(
        string $codigo,
        string $nombre,
        string $tipo = 'ANATOMICA',
        bool $activo = true,
        ?int $padreId = null
    ): CategoriaClinica {
        return CategoriaClinica::create([
            'codigo' => $codigo, 'nombre' => $nombre, 'tipo' => $tipo,
            'categoria_padre_id' => $padreId, 'activo' => $activo,
        ]);
    }

    private function payload(array $cambios = []): array
    {
        return array_replace([
            'codigo' => 'CAT-001', 'nombre' => 'Columna', 'tipo' => 'ANATOMICA',
            'categoria_padre_id' => null,
        ], $cambios);
    }
}
