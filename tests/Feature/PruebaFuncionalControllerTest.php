<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\PruebaFuncional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PruebaFuncionalControllerTest extends TestCase
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

        $this->get(route('catalogos.pruebas.index'))
            ->assertOk()
            ->assertViewIs('catalogos.pruebas-funcionales.index')
            ->assertSee('No hay pruebas funcionales registradas');

        $this->get(route('catalogos.pruebas.create'))
            ->assertOk()
            ->assertViewIs('catalogos.pruebas-funcionales.create')
            ->assertSee('Nueva prueba funcional')
            ->assertSee('name="sistema_origen"', false)
            ->assertSee('name="unidad_medida"', false)
            ->assertDontSee('name="activo"', false);

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_superadmin_can_access_catalog_and_create_prueba(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::SUPERADMIN));

        $this->get(route('catalogos.pruebas.index'))->assertOk();
        $this->get(route('catalogos.pruebas.create'))->assertOk();

        $this->post(route('catalogos.pruebas.store'), $this->payload())
            ->assertRedirect(route('catalogos.pruebas.index'));

        $this->assertDatabaseHas('pruebas_funcionales', [
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEST-001', 'activo' => true,
        ]);
    }

    public function test_kinesiologo_and_paciente_cannot_access_or_write_catalog(): void
    {
        foreach ([RoleEnum::KINESIOLOGO, RoleEnum::PACIENTE] as $role) {
            $this->actingAs($this->userWithRole($role));
            $destino = $role === RoleEnum::KINESIOLOGO
                ? route('perfil.kinesiologa.completar')
                : route('perfil.completar');

            $this->get(route('catalogos.pruebas.index'))
                ->assertRedirect($destino);
            $this->get(route('catalogos.pruebas.create'))
                ->assertRedirect($destino);
            $this->post(route('catalogos.pruebas.store'), $this->payload())
                ->assertRedirect($destino);
        }

        $this->assertDatabaseCount('pruebas_funcionales', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_lists_records_and_columns_for_both_states(): void
    {
        $activo = $this->prueba('TEST-001', 'Test funcional', 'LOINC', true, 'cm');
        $inactivo = $this->prueba('D-02', 'Test histórico', 'INTERNO', false);

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.pruebas.index'))
            ->assertOk()
            ->assertSee('Código')->assertSee('Nombre')
            ->assertSee('Sistema origen')->assertSee('Unidad')->assertSee('Estado')
            ->assertSee($activo->codigo)->assertSee($activo->nombre)
            ->assertSee($inactivo->codigo)->assertSee($inactivo->nombre)
            ->assertSee('LOINC')->assertSee('INTERNO')
            ->assertSee('cm')->assertSee('Sin unidad')
            ->assertSee('Activo')->assertSee('Inactivo')
            ->assertSee('hidden overflow-x-auto md:block')->assertSee('md:hidden')
            ->assertSee('Limpiar filtros')->assertSee('Sistema: LOINC')->assertSee('Unidad: cm')
            ->assertSee(route('catalogos.pruebas.inactivar', $activo))
            ->assertSee(route('catalogos.pruebas.reactivar', $inactivo));

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_searches_codigo_and_excludes_other_records(): void
    {
        $this->prueba('TEST-001', 'Test funcional');
        $this->prueba('OTRO-001', 'Otra prueba');

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.pruebas.index', ['q' => 'TEST-001']));

        $response->assertOk()->assertSee('TEST-001')->assertDontSee('OTRO-001');
        $this->assertSame(['TEST-001'], $response->viewData('pruebas')->pluck('codigo')->all());
    }

    public function test_index_searches_nombre_and_excludes_other_records(): void
    {
        $this->prueba('TEST-001', 'Test funcional');
        $this->prueba('OTRO-001', 'Otra prueba');

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.pruebas.index', ['q' => 'funcional']));

        $response->assertOk()->assertSee('Test funcional')->assertDontSee('Otra prueba');
        $this->assertSame(['TEST-001'], $response->viewData('pruebas')->pluck('codigo')->all());
    }

    public function test_index_filters_todos_activos_inactivos_and_defaults_to_todos(): void
    {
        $this->prueba('A-01', 'Activo', 'INTERNO', true);
        $this->prueba('I-01', 'Inactivo', 'INTERNO', false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach ([
            'todos' => ['A-01', 'I-01'],
            'activos' => ['A-01'],
            'inactivos' => ['I-01'],
        ] as $estado => $codigos) {
            $response = $this->get(route('catalogos.pruebas.index', ['estado' => $estado]));
            $response->assertOk();
            $this->assertSame($codigos, $response->viewData('pruebas')->pluck('codigo')->sort()->values()->all());
        }

        $this->assertSame(2, $this->get(route('catalogos.pruebas.index'))
            ->viewData('pruebas')->total());
    }

    public function test_search_only_uses_code_or_name_and_respects_state_filter(): void
    {
        $this->prueba('A-01', 'Escala activa', 'INTERNO', true, 'cm');
        $this->prueba('I-01', 'Escala inactiva', 'LOINC', false, 'grados');
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach (['cm', 'grados', 'LOINC', 'Descripción'] as $q) {
            $this->assertSame(0, $this->get(route('catalogos.pruebas.index', ['q' => $q]))
                ->viewData('pruebas')->total());
        }

        $response = $this->get(route('catalogos.pruebas.index', [
            'q' => 'Escala', 'estado' => 'activos',
        ]));
        $this->assertSame(['A-01'], $response->viewData('pruebas')->pluck('codigo')->all());
    }

    public function test_index_paginates_ten_records_and_keeps_filters(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->prueba(sprintf('P-%02d', $i), sprintf('Escala %02d', $i));
        }

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.pruebas.index', ['q' => 'Escala', 'estado' => 'activos']));

        $this->assertSame(10, $response->viewData('pruebas')->count());
        $this->assertSame(11, $response->viewData('pruebas')->total());
        $this->assertStringContainsString('q=Escala', $response->viewData('pruebas')->nextPageUrl());
        $this->assertStringContainsString('estado=activos', $response->viewData('pruebas')->nextPageUrl());
    }

    public function test_store_persists_valid_enum_and_always_creates_active(): void
    {
        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->post(route('catalogos.pruebas.store'), $this->payload([
                'sistema_origen' => 'LOINC', 'activo' => false,
            ]));

        $response->assertRedirect(route('catalogos.pruebas.index'))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('pruebas_funcionales', [
            'sistema_origen' => 'LOINC', 'codigo' => 'TEST-001',
            'nombre' => 'Test funcional', 'descripcion' => 'Medición funcional',
            'unidad_medida' => null, 'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_store_rejects_invalid_enum_without_creating_record(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.pruebas.create'))
            ->post(route('catalogos.pruebas.store'), $this->payload(['sistema_origen' => 'CIE-10']))
            ->assertRedirect(route('catalogos.pruebas.create'))
            ->assertSessionHasErrors('sistema_origen');

        $this->assertDatabaseCount('pruebas_funcionales', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_store_accepts_optional_unit_and_persists_it_when_provided(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->post(route('catalogos.pruebas.store'), $this->payload([
                'sistema_origen' => 'SNOMED', 'unidad_medida' => 'cm',
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pruebas_funcionales', [
            'sistema_origen' => 'SNOMED', 'codigo' => 'TEST-001',
            'unidad_medida' => 'cm', 'activo' => true,
        ]);
    }

    public function test_store_rejects_same_system_and_code(): void
    {
        $this->prueba('TEST-001', 'Existente');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.pruebas.create'))
            ->post(route('catalogos.pruebas.store'), $this->payload(['nombre' => 'Duplicado']))
            ->assertRedirect(route('catalogos.pruebas.create'))
            ->assertSessionHasErrors('codigo');

        $this->assertDatabaseCount('pruebas_funcionales', 1);
        $this->assertDatabaseMissing('pruebas_funcionales', ['nombre' => 'Duplicado']);
    }

    public function test_store_allows_same_code_in_different_system(): void
    {
        $this->prueba('TEST-001', 'Interna', 'INTERNO');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->post(route('catalogos.pruebas.store'), $this->payload([
                'sistema_origen' => 'LOINC', 'nombre' => 'LOINC',
            ]))->assertRedirect(route('catalogos.pruebas.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pruebas_funcionales', ['sistema_origen' => 'INTERNO', 'codigo' => 'TEST-001']);
        $this->assertDatabaseHas('pruebas_funcionales', ['sistema_origen' => 'LOINC', 'codigo' => 'TEST-001']);
        $this->assertDatabaseCount('pruebas_funcionales', 2);
    }

    public function test_show_is_read_only_for_active_and_inactive_records(): void
    {
        $activo = $this->prueba('TEST-001', 'Test funcional', 'INTERNO', true, 'cm');
        $inactivo = $this->prueba('I-01', 'Histórico', 'INTERNO', false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach ([$activo, $inactivo] as $prueba) {
            $this->get(route('catalogos.pruebas.show', $prueba))
                ->assertOk()->assertViewIs('catalogos.pruebas-funcionales.show')
                ->assertSee($prueba->codigo)->assertSee($prueba->nombre)
                ->assertSee($prueba->sistema_origen->value)
                ->assertSee($prueba->descripcion)
                ->assertSee($prueba->unidad_medida ?? 'Sin unidad')
                ->assertSee($prueba->activo ? 'Activo' : 'Inactivo');
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_edit_accepts_active_and_inactive_without_state_field(): void
    {
        $activo = $this->prueba('TEST-001', 'Test funcional');
        $inactivo = $this->prueba('I-01', 'Histórico', 'INTERNO', false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach ([$activo, $inactivo] as $prueba) {
            $this->get(route('catalogos.pruebas.edit', $prueba))
                ->assertOk()->assertViewIs('catalogos.pruebas-funcionales.edit')
                ->assertSee($prueba->codigo)
                ->assertSee($prueba->nombre)
                ->assertSee('name="unidad_medida"', false)
                ->assertDontSee('name="activo"', false);
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_update_active_record_changes_data_but_preserves_state(): void
    {
        $prueba = $this->prueba('TEST-001', 'Test funcional');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.pruebas.update', $prueba), $this->payload([
                'sistema_origen' => 'SNOMED', 'codigo' => 'S-100',
                'nombre' => 'Medición funcional', 'descripcion' => 'Actualizado', 'activo' => false,
            ]))->assertRedirect(route('catalogos.pruebas.index'))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('pruebas_funcionales', [
            'id' => $prueba->id, 'sistema_origen' => 'SNOMED',
            'codigo' => 'S-100', 'nombre' => 'Medición funcional',
            'descripcion' => 'Actualizado', 'activo' => true,
        ]);
    }

    public function test_update_inactive_record_changes_data_but_preserves_state(): void
    {
        $prueba = $this->prueba('I-01', 'Histórico', 'INTERNO', false, 'cm');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.pruebas.update', $prueba), $this->payload([
                'sistema_origen' => 'LOINC', 'codigo' => 'I-02',
                'nombre' => 'Histórico editado', 'descripcion' => 'Revisado', 'activo' => true,
            ]))->assertRedirect(route('catalogos.pruebas.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pruebas_funcionales', [
            'id' => $prueba->id, 'sistema_origen' => 'LOINC',
            'codigo' => 'I-02', 'nombre' => 'Histórico editado',
            'descripcion' => 'Revisado', 'unidad_medida' => null, 'activo' => false,
        ]);
    }

    public function test_update_allows_own_composite_key(): void
    {
        $prueba = $this->prueba('TEST-001', 'Test funcional');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.pruebas.update', $prueba), $this->payload([
                'nombre' => 'Test funcional revisada',
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pruebas_funcionales', [
            'id' => $prueba->id, 'sistema_origen' => 'INTERNO',
            'codigo' => 'TEST-001', 'nombre' => 'Test funcional revisada',
        ]);
    }

    public function test_update_rejects_composite_key_owned_by_another_record(): void
    {
        $this->prueba('TEST-001', 'Ocupado');
        $prueba = $this->prueba('I-01', 'Editable', 'INTERNO');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.pruebas.edit', $prueba))
            ->put(route('catalogos.pruebas.update', $prueba), $this->payload())
            ->assertRedirect(route('catalogos.pruebas.edit', $prueba))
            ->assertSessionHasErrors('codigo');

        $this->assertDatabaseHas('pruebas_funcionales', [
            'id' => $prueba->id, 'sistema_origen' => 'INTERNO', 'codigo' => 'I-01',
        ]);
        $this->assertDatabaseCount('pruebas_funcionales', 2);
    }

    public function test_update_allows_same_code_in_another_system(): void
    {
        $this->prueba('TEST-001', 'Ocupado', 'LOINC');
        $prueba = $this->prueba('I-01', 'Editable', 'INTERNO');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.pruebas.update', $prueba), $this->payload([
                'sistema_origen' => 'INTERNO', 'nombre' => 'Editable revisado',
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pruebas_funcionales', [
            'id' => $prueba->id, 'sistema_origen' => 'INTERNO',
            'codigo' => 'TEST-001', 'nombre' => 'Editable revisado',
        ]);
    }

    public function test_inactivar_and_reactivar_have_explicit_routes(): void
    {
        $prueba = $this->prueba('TEST-001', 'Test funcional');
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->patch(route('catalogos.pruebas.inactivar', $prueba))
            ->assertRedirect(route('catalogos.pruebas.index'))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('pruebas_funcionales', ['id' => $prueba->id, 'activo' => false]);

        $this->patch(route('catalogos.pruebas.reactivar', $prueba))
            ->assertRedirect(route('catalogos.pruebas.index'))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('pruebas_funcionales', ['id' => $prueba->id, 'activo' => true]);
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create(['activo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function prueba(
        string $codigo,
        string $nombre,
        string $sistema = 'INTERNO',
        bool $activo = true,
        ?string $unidad = null
    ): PruebaFuncional {
        return PruebaFuncional::create([
            'sistema_origen' => $sistema,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'descripcion' => 'Descripción de '.$nombre,
            'unidad_medida' => $unidad,
            'activo' => $activo,
        ]);
    }

    private function payload(array $cambios = []): array
    {
        return array_replace([
            'sistema_origen' => 'INTERNO',
            'codigo' => 'TEST-001',
            'nombre' => 'Test funcional',
            'descripcion' => 'Medición funcional',
            'unidad_medida' => null,
        ], $cambios);
    }
}
