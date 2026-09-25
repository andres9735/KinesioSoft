<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\TecnicaTerapeutica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TecnicaTerapeuticaControllerTest extends TestCase
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

    public function test_admin_sees_empty_index_and_create_form(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->get(route('catalogos.tecnicas.index'))
            ->assertOk()
            ->assertViewIs('catalogos.tecnicas-terapeuticas.index')
            ->assertSee('No hay técnicas terapéuticas registradas');

        $this->get(route('catalogos.tecnicas.create'))
            ->assertOk()
            ->assertViewIs('catalogos.tecnicas-terapeuticas.create')
            ->assertSee('Nueva técnica terapéutica')
            ->assertSee('name="sistema_origen"', false)
            ->assertDontSee('name="activo"', false);

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_superadmin_can_access_catalog_and_create_tecnica(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::SUPERADMIN));

        $this->get(route('catalogos.tecnicas.index'))->assertOk();
        $this->get(route('catalogos.tecnicas.create'))->assertOk();

        $this->post(route('catalogos.tecnicas.store'), $this->payload())
            ->assertRedirect(route('catalogos.tecnicas.index'));

        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEC-001', 'activo' => true,
        ]);
    }

    public function test_kinesiologo_and_paciente_cannot_access_or_write_catalog(): void
    {
        foreach ([RoleEnum::KINESIOLOGO, RoleEnum::PACIENTE] as $role) {
            $this->actingAs($this->userWithRole($role));
            $destino = $role === RoleEnum::KINESIOLOGO
                ? route('perfil.kinesiologa.completar')
                : route('perfil.completar');

            $this->get(route('catalogos.tecnicas.index'))
                ->assertRedirect($destino);
            $this->get(route('catalogos.tecnicas.create'))
                ->assertRedirect($destino);
            $this->post(route('catalogos.tecnicas.store'), $this->payload())
                ->assertRedirect($destino);
        }

        $this->assertDatabaseCount('tecnicas_terapeuticas', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_lists_records_and_columns_for_both_states(): void
    {
        $activo = $this->tecnica('TEC-001', 'Movilización articular', 'INTERNO', true);
        $inactivo = $this->tecnica('D-02', 'Técnica histórica', 'SNOMED', false);

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.tecnicas.index'))
            ->assertOk()
            ->assertSee('Código')->assertSee('Nombre')
            ->assertSee('Sistema origen')->assertSee('Estado')
            ->assertSee($activo->codigo)->assertSee($activo->nombre)
            ->assertSee($inactivo->codigo)->assertSee($inactivo->nombre)
            ->assertSee('INTERNO')->assertSee('SNOMED')
            ->assertSee('Activo')->assertSee('Inactivo')
            ->assertSee('hidden overflow-x-auto md:block')->assertSee('md:hidden')
            ->assertSee('Limpiar filtros')->assertSee('Sistema: INTERNO')
            ->assertSee(route('catalogos.tecnicas.inactivar', $activo))
            ->assertSee(route('catalogos.tecnicas.reactivar', $inactivo));

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_searches_codigo_and_excludes_other_records(): void
    {
        $this->tecnica('TEC-001', 'Movilización articular');
        $this->tecnica('TEC-999', 'Otra técnica');

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.tecnicas.index', ['q' => 'TEC-001']));

        $response->assertOk()->assertSee('TEC-001')->assertDontSee('TEC-999');
        $this->assertSame(['TEC-001'], $response->viewData('tecnicas')->pluck('codigo')->all());
    }

    public function test_index_searches_nombre_and_excludes_other_records(): void
    {
        $this->tecnica('TEC-001', 'Movilización articular');
        $this->tecnica('TEC-999', 'Otra técnica');

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.tecnicas.index', ['q' => 'Movilización']));

        $response->assertOk()->assertSee('Movilización articular')->assertDontSee('Otra técnica');
        $this->assertSame(['TEC-001'], $response->viewData('tecnicas')->pluck('codigo')->all());
    }

    public function test_index_filters_todos_activos_inactivos_and_defaults_to_todos(): void
    {
        $this->tecnica('A-01', 'Activo', 'INTERNO', true);
        $this->tecnica('I-01', 'Inactivo', 'INTERNO', false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach ([
            'todos' => ['A-01', 'I-01'],
            'activos' => ['A-01'],
            'inactivos' => ['I-01'],
        ] as $estado => $codigos) {
            $response = $this->get(route('catalogos.tecnicas.index', ['estado' => $estado]));
            $response->assertOk();
            $this->assertSame($codigos, $response->viewData('tecnicas')->pluck('codigo')->sort()->values()->all());
        }

        $this->assertSame(2, $this->get(route('catalogos.tecnicas.index'))
            ->viewData('tecnicas')->total());
    }

    public function test_search_only_uses_code_or_name_and_respects_state_filter(): void
    {
        $this->tecnica('TEC-A', 'Movilización activa', 'INTERNO', true);
        $this->tecnica('TEC-I', 'Movilización inactiva', 'SNOMED', false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach (['SNOMED', 'Descripción'] as $q) {
            $this->assertSame(0, $this->get(route('catalogos.tecnicas.index', ['q' => $q]))
                ->viewData('tecnicas')->total());
        }

        $response = $this->get(route('catalogos.tecnicas.index', [
            'q' => 'Movilización', 'estado' => 'activos',
        ]));
        $this->assertSame(['TEC-A'], $response->viewData('tecnicas')->pluck('codigo')->all());
    }

    public function test_index_paginates_ten_records_and_keeps_filters(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->tecnica(sprintf('TEC-%02d', $i), sprintf('Técnica %02d', $i));
        }

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.tecnicas.index', ['q' => 'Técnica', 'estado' => 'activos']));

        $this->assertSame(10, $response->viewData('tecnicas')->count());
        $this->assertSame(11, $response->viewData('tecnicas')->total());
        $this->assertStringContainsString('q=T%C3%A9cnica', $response->viewData('tecnicas')->nextPageUrl());
        $this->assertStringContainsString('estado=activos', $response->viewData('tecnicas')->nextPageUrl());
    }

    public function test_store_persists_valid_enum_and_always_creates_active(): void
    {
        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->post(route('catalogos.tecnicas.store'), $this->payload([
                'sistema_origen' => 'SNOMED', 'activo' => false,
            ]));

        $response->assertRedirect(route('catalogos.tecnicas.index'))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'sistema_origen' => 'SNOMED', 'codigo' => 'TEC-001',
            'nombre' => 'Movilización articular', 'descripcion' => 'Técnica manual', 'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_store_rejects_invalid_enum_without_creating_record(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.tecnicas.create'))
            ->post(route('catalogos.tecnicas.store'), $this->payload(['sistema_origen' => 'LOINC']))
            ->assertRedirect(route('catalogos.tecnicas.create'))
            ->assertSessionHasErrors('sistema_origen');

        $this->assertDatabaseCount('tecnicas_terapeuticas', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_store_rejects_same_system_and_code(): void
    {
        $this->tecnica('TEC-001', 'Existente');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.tecnicas.create'))
            ->post(route('catalogos.tecnicas.store'), $this->payload(['nombre' => 'Duplicado']))
            ->assertRedirect(route('catalogos.tecnicas.create'))
            ->assertSessionHasErrors('codigo');

        $this->assertDatabaseCount('tecnicas_terapeuticas', 1);
        $this->assertDatabaseMissing('tecnicas_terapeuticas', ['nombre' => 'Duplicado']);
    }

    public function test_store_allows_same_code_in_different_system(): void
    {
        $this->tecnica('TEC-001', 'Interna', 'INTERNO');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->post(route('catalogos.tecnicas.store'), $this->payload([
                'sistema_origen' => 'SNOMED', 'nombre' => 'SNOMED',
            ]))->assertRedirect(route('catalogos.tecnicas.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tecnicas_terapeuticas', ['sistema_origen' => 'SNOMED', 'codigo' => 'TEC-001']);
        $this->assertDatabaseHas('tecnicas_terapeuticas', ['sistema_origen' => 'INTERNO', 'codigo' => 'TEC-001']);
        $this->assertDatabaseCount('tecnicas_terapeuticas', 2);
    }

    public function test_show_is_read_only_for_active_and_inactive_records(): void
    {
        $activo = $this->tecnica('TEC-001', 'Movilización articular', 'INTERNO', true);
        $inactivo = $this->tecnica('I-01', 'Histórico', 'INTERNO', false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach ([$activo, $inactivo] as $tecnica) {
            $this->get(route('catalogos.tecnicas.show', $tecnica))
                ->assertOk()->assertViewIs('catalogos.tecnicas-terapeuticas.show')
                ->assertSee($tecnica->codigo)->assertSee($tecnica->nombre)
                ->assertSee($tecnica->sistema_origen->value)
                ->assertSee($tecnica->descripcion)
                ->assertSee($tecnica->activo ? 'Activo' : 'Inactivo');
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_edit_accepts_active_and_inactive_without_state_field(): void
    {
        $activo = $this->tecnica('TEC-001', 'Movilización articular');
        $inactivo = $this->tecnica('I-01', 'Histórico', 'INTERNO', false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach ([$activo, $inactivo] as $tecnica) {
            $this->get(route('catalogos.tecnicas.edit', $tecnica))
                ->assertOk()->assertViewIs('catalogos.tecnicas-terapeuticas.edit')
                ->assertSee($tecnica->codigo)
                ->assertSee($tecnica->nombre)
                ->assertDontSee('name="activo"', false);
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_update_active_record_changes_data_but_preserves_state(): void
    {
        $tecnica = $this->tecnica('TEC-001', 'Movilización articular');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.tecnicas.update', $tecnica), $this->payload([
                'sistema_origen' => 'SNOMED', 'codigo' => 'S-100',
                'nombre' => 'Técnica manual', 'descripcion' => 'Actualizado', 'activo' => false,
            ]))->assertRedirect(route('catalogos.tecnicas.index'))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'id' => $tecnica->id, 'sistema_origen' => 'SNOMED',
            'codigo' => 'S-100', 'nombre' => 'Técnica manual',
            'descripcion' => 'Actualizado', 'activo' => true,
        ]);
    }

    public function test_update_inactive_record_changes_data_but_preserves_state(): void
    {
        $tecnica = $this->tecnica('I-01', 'Histórico', 'INTERNO', false);

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.tecnicas.update', $tecnica), $this->payload([
                'sistema_origen' => 'SNOMED', 'codigo' => 'I-02',
                'nombre' => 'Histórico editado', 'descripcion' => 'Revisado', 'activo' => true,
            ]))->assertRedirect(route('catalogos.tecnicas.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'id' => $tecnica->id, 'sistema_origen' => 'SNOMED',
            'codigo' => 'I-02', 'nombre' => 'Histórico editado',
            'descripcion' => 'Revisado', 'activo' => false,
        ]);
    }

    public function test_update_allows_own_composite_key(): void
    {
        $tecnica = $this->tecnica('TEC-001', 'Movilización articular');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.tecnicas.update', $tecnica), $this->payload([
                'nombre' => 'Movilización articular revisada',
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'id' => $tecnica->id, 'sistema_origen' => 'INTERNO',
            'codigo' => 'TEC-001', 'nombre' => 'Movilización articular revisada',
        ]);
    }

    public function test_update_allows_changing_only_code(): void
    {
        $tecnica = $this->tecnica('TEC-001', 'Movilización articular');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.tecnicas.update', $tecnica), $this->payload([
                'codigo' => 'TEC-002',
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'id' => $tecnica->id, 'sistema_origen' => 'INTERNO', 'codigo' => 'TEC-002',
        ]);
    }

    public function test_update_allows_changing_only_origin(): void
    {
        $tecnica = $this->tecnica('TEC-001', 'Movilización articular');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.tecnicas.update', $tecnica), $this->payload([
                'sistema_origen' => 'SNOMED',
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'id' => $tecnica->id, 'sistema_origen' => 'SNOMED', 'codigo' => 'TEC-001',
        ]);
    }

    public function test_unchanged_http_update_does_not_audit(): void
    {
        $tecnica = $this->tecnica('TEC-001', 'Movilización articular');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.tecnicas.update', $tecnica), $this->payload([
                'descripcion' => 'Descripción de Movilización articular',
                'activo' => false,
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'id' => $tecnica->id, 'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_update_rejects_composite_key_owned_by_another_record(): void
    {
        $this->tecnica('TEC-001', 'Ocupado');
        $tecnica = $this->tecnica('I-01', 'Editable', 'INTERNO');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.tecnicas.edit', $tecnica))
            ->put(route('catalogos.tecnicas.update', $tecnica), $this->payload())
            ->assertRedirect(route('catalogos.tecnicas.edit', $tecnica))
            ->assertSessionHasErrors('codigo');

        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'id' => $tecnica->id, 'sistema_origen' => 'INTERNO', 'codigo' => 'I-01',
        ]);
        $this->assertDatabaseCount('tecnicas_terapeuticas', 2);
    }

    public function test_update_allows_same_code_in_another_system(): void
    {
        $this->tecnica('TEC-001', 'Ocupado', 'SNOMED');
        $tecnica = $this->tecnica('I-01', 'Editable', 'INTERNO');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.tecnicas.update', $tecnica), $this->payload([
                'sistema_origen' => 'INTERNO', 'nombre' => 'Editable revisado',
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tecnicas_terapeuticas', [
            'id' => $tecnica->id, 'sistema_origen' => 'INTERNO',
            'codigo' => 'TEC-001', 'nombre' => 'Editable revisado',
        ]);
    }

    public function test_inactivar_and_reactivar_have_explicit_routes(): void
    {
        $tecnica = $this->tecnica('TEC-001', 'Movilización articular');
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->patch(route('catalogos.tecnicas.inactivar', $tecnica))
            ->assertRedirect(route('catalogos.tecnicas.index'))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('tecnicas_terapeuticas', ['id' => $tecnica->id, 'activo' => false]);

        $this->patch(route('catalogos.tecnicas.reactivar', $tecnica))
            ->assertRedirect(route('catalogos.tecnicas.index'))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('tecnicas_terapeuticas', ['id' => $tecnica->id, 'activo' => true]);
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create(['activo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function tecnica(
        string $codigo,
        string $nombre,
        string $sistema = 'INTERNO',
        bool $activo = true
    ): TecnicaTerapeutica {
        return TecnicaTerapeutica::create([
            'sistema_origen' => $sistema,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'descripcion' => 'Descripción de '.$nombre,
            'activo' => $activo,
        ]);
    }

    private function payload(array $cambios = []): array
    {
        return array_replace([
            'sistema_origen' => 'INTERNO',
            'codigo' => 'TEC-001',
            'nombre' => 'Movilización articular',
            'descripcion' => 'Técnica manual',
        ], $cambios);
    }
}
