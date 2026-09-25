<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\DiagnosticoClinico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DiagnosticoClinicoControllerTest extends TestCase
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

        $this->get(route('catalogos.diagnosticos.index'))
            ->assertOk()
            ->assertViewIs('catalogos.diagnosticos-clinicos.index')
            ->assertSee('No hay diagnósticos clínicos registrados');

        $this->get(route('catalogos.diagnosticos.create'))
            ->assertOk()
            ->assertViewIs('catalogos.diagnosticos-clinicos.create')
            ->assertSee('Nuevo diagnóstico clínico')
            ->assertSee('name="sistema_origen"', false)
            ->assertDontSee('name="activo"', false);

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_superadmin_can_access_catalog_and_create_diagnostico(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::SUPERADMIN));

        $this->get(route('catalogos.diagnosticos.index'))->assertOk();
        $this->get(route('catalogos.diagnosticos.create'))->assertOk();

        $this->post(route('catalogos.diagnosticos.store'), $this->payload())
            ->assertRedirect(route('catalogos.diagnosticos.index'));

        $this->assertDatabaseHas('diagnosticos_clinicos', [
            'sistema_origen' => 'CIE-10', 'codigo' => 'M54.5', 'activo' => true,
        ]);
    }

    public function test_kinesiologo_and_paciente_cannot_access_or_write_catalog(): void
    {
        foreach ([RoleEnum::KINESIOLOGO, RoleEnum::PACIENTE] as $role) {
            $this->actingAs($this->userWithRole($role));
            $destino = $role === RoleEnum::KINESIOLOGO
                ? route('perfil.kinesiologa.completar')
                : route('perfil.completar');

            $this->get(route('catalogos.diagnosticos.index'))
                ->assertRedirect($destino);
            $this->get(route('catalogos.diagnosticos.create'))
                ->assertRedirect($destino);
            $this->post(route('catalogos.diagnosticos.store'), $this->payload())
                ->assertRedirect($destino);
        }

        $this->assertDatabaseCount('diagnosticos_clinicos', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_lists_records_and_columns_for_both_states(): void
    {
        $activo = $this->diagnostico('M54.5', 'Lumbalgia', 'CIE-10', true);
        $inactivo = $this->diagnostico('D-02', 'Dolor histórico', 'INTERNO', false);

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.diagnosticos.index'))
            ->assertOk()
            ->assertSee('Código')->assertSee('Nombre')
            ->assertSee('Sistema origen')->assertSee('Estado')
            ->assertSee($activo->codigo)->assertSee($activo->nombre)
            ->assertSee($inactivo->codigo)->assertSee($inactivo->nombre)
            ->assertSee('CIE-10')->assertSee('INTERNO')
            ->assertSee('Activo')->assertSee('Inactivo')
            ->assertSee('hidden overflow-x-auto md:block')->assertSee('md:hidden')
            ->assertSee('Limpiar filtros')->assertSee('Sistema: CIE-10')
            ->assertSee(route('catalogos.diagnosticos.inactivar', $activo))
            ->assertSee(route('catalogos.diagnosticos.reactivar', $inactivo));

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_searches_codigo_and_excludes_other_records(): void
    {
        $this->diagnostico('M54.5', 'Lumbalgia');
        $this->diagnostico('Z99.1', 'Otro diagnóstico');

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.diagnosticos.index', ['q' => '54.5']));

        $response->assertOk()->assertSee('M54.5')->assertDontSee('Z99.1');
        $this->assertSame(['M54.5'], $response->viewData('diagnosticos')->pluck('codigo')->all());
    }

    public function test_index_searches_nombre_and_excludes_other_records(): void
    {
        $this->diagnostico('M54.5', 'Lumbalgia');
        $this->diagnostico('Z99.1', 'Otro diagnóstico');

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.diagnosticos.index', ['q' => 'Lumb']));

        $response->assertOk()->assertSee('Lumbalgia')->assertDontSee('Otro diagnóstico');
        $this->assertSame(['M54.5'], $response->viewData('diagnosticos')->pluck('codigo')->all());
    }

    public function test_index_filters_todos_activos_inactivos_and_defaults_to_todos(): void
    {
        $this->diagnostico('A-01', 'Activo', 'CIE-10', true);
        $this->diagnostico('I-01', 'Inactivo', 'INTERNO', false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach ([
            'todos' => ['A-01', 'I-01'],
            'activos' => ['A-01'],
            'inactivos' => ['I-01'],
        ] as $estado => $codigos) {
            $response = $this->get(route('catalogos.diagnosticos.index', ['estado' => $estado]));
            $response->assertOk();
            $this->assertSame($codigos, $response->viewData('diagnosticos')->pluck('codigo')->sort()->values()->all());
        }

        $this->assertSame(2, $this->get(route('catalogos.diagnosticos.index'))
            ->viewData('diagnosticos')->total());
    }

    public function test_store_persists_valid_enum_and_always_creates_active(): void
    {
        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->post(route('catalogos.diagnosticos.store'), $this->payload([
                'sistema_origen' => 'CIF', 'activo' => false,
            ]));

        $response->assertRedirect(route('catalogos.diagnosticos.index'))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('diagnosticos_clinicos', [
            'sistema_origen' => 'CIF', 'codigo' => 'M54.5',
            'nombre' => 'Lumbalgia', 'descripcion' => 'Dolor lumbar', 'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_store_rejects_invalid_enum_without_creating_record(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.diagnosticos.create'))
            ->post(route('catalogos.diagnosticos.store'), $this->payload(['sistema_origen' => 'INVALIDO']))
            ->assertRedirect(route('catalogos.diagnosticos.create'))
            ->assertSessionHasErrors('sistema_origen');

        $this->assertDatabaseCount('diagnosticos_clinicos', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_store_rejects_same_system_and_code(): void
    {
        $this->diagnostico('M54.5', 'Existente');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.diagnosticos.create'))
            ->post(route('catalogos.diagnosticos.store'), $this->payload(['nombre' => 'Duplicado']))
            ->assertRedirect(route('catalogos.diagnosticos.create'))
            ->assertSessionHasErrors('codigo');

        $this->assertDatabaseCount('diagnosticos_clinicos', 1);
        $this->assertDatabaseMissing('diagnosticos_clinicos', ['nombre' => 'Duplicado']);
    }

    public function test_store_allows_same_code_in_different_system(): void
    {
        $this->diagnostico('M54.5', 'CIE', 'CIE-10');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->post(route('catalogos.diagnosticos.store'), $this->payload([
                'sistema_origen' => 'INTERNO', 'nombre' => 'Interno',
            ]))->assertRedirect(route('catalogos.diagnosticos.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diagnosticos_clinicos', ['sistema_origen' => 'CIE-10', 'codigo' => 'M54.5']);
        $this->assertDatabaseHas('diagnosticos_clinicos', ['sistema_origen' => 'INTERNO', 'codigo' => 'M54.5']);
        $this->assertDatabaseCount('diagnosticos_clinicos', 2);
    }

    public function test_show_is_read_only_for_active_and_inactive_records(): void
    {
        $activo = $this->diagnostico('M54.5', 'Lumbalgia', 'CIE-10', true);
        $inactivo = $this->diagnostico('I-01', 'Histórico', 'INTERNO', false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach ([$activo, $inactivo] as $diagnostico) {
            $this->get(route('catalogos.diagnosticos.show', $diagnostico))
                ->assertOk()->assertViewIs('catalogos.diagnosticos-clinicos.show')
                ->assertSee($diagnostico->codigo)->assertSee($diagnostico->nombre)
                ->assertSee($diagnostico->sistema_origen->value)
                ->assertSee($diagnostico->descripcion)
                ->assertSee($diagnostico->activo ? 'Activo' : 'Inactivo');
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_edit_accepts_active_and_inactive_without_state_field(): void
    {
        $activo = $this->diagnostico('M54.5', 'Lumbalgia');
        $inactivo = $this->diagnostico('I-01', 'Histórico', 'INTERNO', false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach ([$activo, $inactivo] as $diagnostico) {
            $this->get(route('catalogos.diagnosticos.edit', $diagnostico))
                ->assertOk()->assertViewIs('catalogos.diagnosticos-clinicos.edit')
                ->assertSee($diagnostico->codigo)
                ->assertSee($diagnostico->nombre)
                ->assertDontSee('name="activo"', false);
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_update_active_record_changes_data_but_preserves_state(): void
    {
        $diagnostico = $this->diagnostico('M54.5', 'Lumbalgia');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.diagnosticos.update', $diagnostico), $this->payload([
                'sistema_origen' => 'SNOMED', 'codigo' => 'S-100',
                'nombre' => 'Dolor lumbar', 'descripcion' => 'Actualizado', 'activo' => false,
            ]))->assertRedirect(route('catalogos.diagnosticos.index'))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('diagnosticos_clinicos', [
            'id' => $diagnostico->id, 'sistema_origen' => 'SNOMED',
            'codigo' => 'S-100', 'nombre' => 'Dolor lumbar',
            'descripcion' => 'Actualizado', 'activo' => true,
        ]);
    }

    public function test_update_inactive_record_changes_data_but_preserves_state(): void
    {
        $diagnostico = $this->diagnostico('I-01', 'Histórico', 'INTERNO', false);

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.diagnosticos.update', $diagnostico), $this->payload([
                'sistema_origen' => 'CIF', 'codigo' => 'I-02',
                'nombre' => 'Histórico editado', 'descripcion' => 'Revisado', 'activo' => true,
            ]))->assertRedirect(route('catalogos.diagnosticos.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diagnosticos_clinicos', [
            'id' => $diagnostico->id, 'sistema_origen' => 'CIF',
            'codigo' => 'I-02', 'nombre' => 'Histórico editado',
            'descripcion' => 'Revisado', 'activo' => false,
        ]);
    }

    public function test_update_allows_own_composite_key(): void
    {
        $diagnostico = $this->diagnostico('M54.5', 'Lumbalgia');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.diagnosticos.update', $diagnostico), $this->payload([
                'nombre' => 'Lumbalgia revisada',
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diagnosticos_clinicos', [
            'id' => $diagnostico->id, 'sistema_origen' => 'CIE-10',
            'codigo' => 'M54.5', 'nombre' => 'Lumbalgia revisada',
        ]);
    }

    public function test_update_rejects_composite_key_owned_by_another_record(): void
    {
        $this->diagnostico('M54.5', 'Ocupado');
        $diagnostico = $this->diagnostico('I-01', 'Editable', 'INTERNO');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.diagnosticos.edit', $diagnostico))
            ->put(route('catalogos.diagnosticos.update', $diagnostico), $this->payload())
            ->assertRedirect(route('catalogos.diagnosticos.edit', $diagnostico))
            ->assertSessionHasErrors('codigo');

        $this->assertDatabaseHas('diagnosticos_clinicos', [
            'id' => $diagnostico->id, 'sistema_origen' => 'INTERNO', 'codigo' => 'I-01',
        ]);
        $this->assertDatabaseCount('diagnosticos_clinicos', 2);
    }

    public function test_update_allows_same_code_in_another_system(): void
    {
        $this->diagnostico('M54.5', 'Ocupado', 'CIE-10');
        $diagnostico = $this->diagnostico('I-01', 'Editable', 'INTERNO');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.diagnosticos.update', $diagnostico), $this->payload([
                'sistema_origen' => 'INTERNO', 'nombre' => 'Editable revisado',
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diagnosticos_clinicos', [
            'id' => $diagnostico->id, 'sistema_origen' => 'INTERNO',
            'codigo' => 'M54.5', 'nombre' => 'Editable revisado',
        ]);
    }

    public function test_inactivar_and_reactivar_have_explicit_routes(): void
    {
        $diagnostico = $this->diagnostico('M54.5', 'Lumbalgia');
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->patch(route('catalogos.diagnosticos.inactivar', $diagnostico))
            ->assertRedirect(route('catalogos.diagnosticos.index'))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('diagnosticos_clinicos', ['id' => $diagnostico->id, 'activo' => false]);

        $this->patch(route('catalogos.diagnosticos.reactivar', $diagnostico))
            ->assertRedirect(route('catalogos.diagnosticos.index'))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('diagnosticos_clinicos', ['id' => $diagnostico->id, 'activo' => true]);
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create(['activo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function diagnostico(
        string $codigo,
        string $nombre,
        string $sistema = 'CIE-10',
        bool $activo = true
    ): DiagnosticoClinico {
        return DiagnosticoClinico::create([
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
            'sistema_origen' => 'CIE-10',
            'codigo' => 'M54.5',
            'nombre' => 'Lumbalgia',
            'descripcion' => 'Dolor lumbar',
        ], $cambios);
    }
}
