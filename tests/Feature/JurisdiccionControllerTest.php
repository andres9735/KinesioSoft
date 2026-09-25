<?php

namespace Tests\Feature;

use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class JurisdiccionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findOrCreate('administrador', 'web');
        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('paciente', 'web');
        Role::findOrCreate('kinesiologo', 'web');
    }

    private function adminUser(): User
    {
        $user = User::factory()->create([
            'activo' => true,
        ]);

        $user->assignRole('administrador');

        return $user;
    }

    private function superadminUser(): User
    {
        $user = User::factory()->create([
            'activo' => true,
        ]);

        $user->assignRole('superadmin');

        return $user;
    }

    private function nonAdminUser(): User
    {
        $user = User::factory()->create([
            'activo' => true,
        ]);

        $user->assignRole('paciente');

        return $user;
    }

    public function test_admin_can_view_jurisdicciones_index(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('catalogos.jurisdicciones.index'));

        $response->assertOk();
        $response->assertSee('Jurisdicciones');
        $response->assertSee('No hay jurisdicciones registradas');
        $response->assertSee('Aún no se cargaron jurisdicciones en el sistema.');
        $response->assertSee('Crear primera jurisdicción');
    }

    public function test_superadmin_can_view_jurisdicciones_index(): void
    {
        $superadmin = $this->superadminUser();

        $response = $this->actingAs($superadmin)->get(route('catalogos.jurisdicciones.index'));

        $response->assertOk();
    }

    public function test_non_admin_user_cannot_access_jurisdicciones_index(): void
    {
        $user = $this->nonAdminUser();

        $response = $this->actingAs($user)->get(route('catalogos.jurisdicciones.index'));

        $response->assertRedirect(route('perfil.completar'));
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('catalogos.jurisdicciones.create'));

        $response->assertOk();
        $response->assertSee('Nueva jurisdicción');
        $response->assertSee('flex flex-col-reverse');
    }

    public function test_admin_can_store_valid_jurisdiccion(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('catalogos.jurisdicciones.store'), [
            'nombre' => 'Misiones',
        ]);

        $response->assertRedirect(route('catalogos.jurisdicciones.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('jurisdicciones', [
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => 1,
        ]);
    }

    public function test_store_requires_nombre(): void
    {
        $admin = $this->adminUser();

        $response = $this->from(route('catalogos.jurisdicciones.create'))
            ->actingAs($admin)
            ->post(route('catalogos.jurisdicciones.store'), [
                'nombre' => '',
            ]);

        $response->assertRedirect(route('catalogos.jurisdicciones.create'));
        $response->assertSessionHasErrors('nombre');
    }

    public function test_store_rejects_nombre_with_invalid_characters(): void
    {
        $admin = $this->adminUser();

        $response = $this
            ->from(route('catalogos.jurisdicciones.create'))
            ->actingAs($admin)
            ->post(route('catalogos.jurisdicciones.store'), [
                'nombre' => 'Misiones 123',
            ]);

        $response->assertRedirect(
            route('catalogos.jurisdicciones.create')
        );

        $response->assertSessionHasErrors([
            'nombre' => 'El nombre solo puede contener letras y espacios.',
        ]);
    }

    public function test_store_rejects_duplicate_nombre(): void
    {
        $admin = $this->adminUser();

        Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.jurisdicciones.create'))
            ->actingAs($admin)
            ->post(route('catalogos.jurisdicciones.store'), [
                'nombre' => 'Misiones',
            ]);

        $response->assertRedirect(route('catalogos.jurisdicciones.create'));
        $response->assertSessionHasErrors([
            'nombre' => 'Ya existe una jurisdicción con ese nombre.',
        ]);
    }

    public function test_store_generates_slug_automatically(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('catalogos.jurisdicciones.store'), [
            'nombre' => 'Buenos Aires',
        ]);

        $this->assertDatabaseHas('jurisdicciones', [
            'nombre' => 'Buenos Aires',
            'slug' => 'buenos-aires',
        ]);
    }

    public function test_store_rejects_when_nombre_generates_duplicate_slug(): void
    {
        $admin = $this->adminUser();

        Jurisdiccion::create([
            'nombre' => 'Buenos Aires',
            'slug' => 'buenos-aires',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.jurisdicciones.create'))
            ->actingAs($admin)
            ->post(route('catalogos.jurisdicciones.store'), [
                'nombre' => 'Buenos  Aires',
            ]);

        $response->assertRedirect(route('catalogos.jurisdicciones.create'));
        $response->assertSessionHasErrors([
            'nombre' => 'El nombre ingresado genera un slug que ya existe. Intente con una variante distinta.',
        ]);
    }

    public function test_admin_can_view_edit_form(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('catalogos.jurisdicciones.edit', $jurisdiccion));

        $response->assertOk();
        $response->assertSee('Editar jurisdicción');
        $response->assertSee('Misiones');
        $response->assertSee('flex flex-col-reverse');
    }

    public function test_admin_can_update_jurisdiccion(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('catalogos.jurisdicciones.update', $jurisdiccion), [
            'nombre' => 'Corrientes',
        ]);

        $response->assertRedirect(route('catalogos.jurisdicciones.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('jurisdicciones', [
            'id' => $jurisdiccion->id,
            'nombre' => 'Corrientes',
            'slug' => 'corrientes',
        ]);
    }

    public function test_update_allows_same_nombre_for_same_record(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('catalogos.jurisdicciones.update', $jurisdiccion), [
            'nombre' => 'Misiones',
        ]);

        $response->assertRedirect(route('catalogos.jurisdicciones.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_update_rejects_nombre_with_invalid_characters(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this
            ->from(
                route(
                    'catalogos.jurisdicciones.edit',
                    $jurisdiccion
                )
            )
            ->actingAs($admin)
            ->put(
                route(
                    'catalogos.jurisdicciones.update',
                    $jurisdiccion
                ),
                [
                    'nombre' => 'Misiones 123',
                ]
            );

        $response->assertRedirect(
            route(
                'catalogos.jurisdicciones.edit',
                $jurisdiccion
            )
        );

        $response->assertSessionHasErrors([
            'nombre' => 'El nombre solo puede contener letras y espacios.',
        ]);
    }

    public function test_update_rejects_duplicate_nombre_from_another_record(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion1 = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $jurisdiccion2 = Jurisdiccion::create([
            'nombre' => 'Corrientes',
            'slug' => 'corrientes',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.jurisdicciones.edit', $jurisdiccion2))
            ->actingAs($admin)
            ->put(route('catalogos.jurisdicciones.update', $jurisdiccion2), [
                'nombre' => $jurisdiccion1->nombre,
            ]);

        $response->assertRedirect(route('catalogos.jurisdicciones.edit', $jurisdiccion2));
        $response->assertSessionHasErrors([
            'nombre' => 'Ya existe una jurisdicción con ese nombre.',
        ]);
    }

    public function test_update_rejects_when_new_nombre_generates_duplicate_slug(): void
    {
        $admin = $this->adminUser();

        Jurisdiccion::create([
            'nombre' => 'Buenos Aires',
            'slug' => 'buenos-aires',
            'activo' => true,
        ]);

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Corrientes',
            'slug' => 'corrientes',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.jurisdicciones.edit', $jurisdiccion))
            ->actingAs($admin)
            ->put(route('catalogos.jurisdicciones.update', $jurisdiccion), [
                'nombre' => 'Buenos  Aires',
            ]);

        $response->assertRedirect(route('catalogos.jurisdicciones.edit', $jurisdiccion));
        $response->assertSessionHasErrors([
            'nombre' => 'El nombre ingresado genera un slug que ya existe. Intente con una variante distinta.',
        ]);
    }

    public function test_inactivar_inactivates_jurisdiccion(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.jurisdicciones.inactivar', $jurisdiccion));

        $response->assertRedirect(route('catalogos.jurisdicciones.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('jurisdicciones', [
            'id' => $jurisdiccion->id,
            'activo' => 0,
        ]);
    }

    public function test_reactivar_activates_jurisdiccion(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => false,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.jurisdicciones.reactivar', $jurisdiccion));

        $response->assertRedirect(route('catalogos.jurisdicciones.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('jurisdicciones', [
            'id' => $jurisdiccion->id,
            'activo' => 1,
        ]);
    }

    public function test_repeated_state_requests_do_not_toggle_jurisdiccion_or_audit(): void
    {
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => false,
        ]);

        $this->actingAs($this->adminUser())
            ->patch(route('catalogos.jurisdicciones.inactivar', $jurisdiccion))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('jurisdicciones', [
            'id' => $jurisdiccion->id,
            'activo' => false,
        ]);

        $jurisdiccion->update(['activo' => true]);

        $this->patch(route('catalogos.jurisdicciones.reactivar', $jurisdiccion))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('jurisdicciones', [
            'id' => $jurisdiccion->id,
            'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_searches_by_name_and_shows_empty_result_message(): void
    {
        $this->jurisdiccion('Misiones', true);
        $this->jurisdiccion('Corrientes', true);

        $response = $this->actingAs($this->adminUser())
            ->get(route('catalogos.jurisdicciones.index', ['q' => 'Mision']));

        $response->assertOk();
        $this->assertSame(['Misiones'], $response->viewData('jurisdicciones')->pluck('nombre')->all());
        $response->assertSee('value="Mision"', false);

        $response = $this->get(route('catalogos.jurisdicciones.index', ['q' => 'Santa']));
        $response->assertOk()->assertSee('No se encontraron jurisdicciones');
        $this->assertSame(0, $response->viewData('jurisdicciones')->total());
    }

    public function test_index_filters_all_active_and_inactive_jurisdicciones(): void
    {
        $this->jurisdiccion('Misiones', true);
        $this->jurisdiccion('Corrientes', false);
        $this->actingAs($this->adminUser());

        foreach (['todos' => ['Corrientes', 'Misiones'], 'activos' => ['Misiones'], 'inactivos' => ['Corrientes']] as $estado => $nombres) {
            $response = $this->get(route('catalogos.jurisdicciones.index', ['estado' => $estado]));
            $response->assertOk();
            $this->assertSame($nombres, $response->viewData('jurisdicciones')->pluck('nombre')->all());
        }

        $response = $this->get(route('catalogos.jurisdicciones.index'));
        $this->assertSame(2, $response->viewData('jurisdicciones')->total());

        $response = $this->get(route('catalogos.jurisdicciones.index', ['estado' => 'desconocido']));
        $response->assertOk();
        $this->assertSame(2, $response->viewData('jurisdicciones')->total());
        $response->assertSee('<option value="todos" selected>', false);
    }

    public function test_index_combines_search_and_state_filter(): void
    {
        $this->jurisdiccion('Buenos Aires', true);
        $this->jurisdiccion('Entre Rios', false);
        $this->jurisdiccion('Corrientes', true);

        $response = $this->actingAs($this->adminUser())->get(route('catalogos.jurisdicciones.index', [
            'q' => 'rios',
            'estado' => 'inactivos',
        ]));

        $response->assertOk();
        $this->assertSame(['Entre Rios'], $response->viewData('jurisdicciones')->pluck('nombre')->all());
    }

    public function test_index_pagination_keeps_search_and_state_query_string(): void
    {
        for ($numero = 1; $numero <= 11; $numero++) {
            $this->jurisdiccion('Provincia '.chr(64 + $numero), true);
        }

        $response = $this->actingAs($this->adminUser())->get(route('catalogos.jurisdicciones.index', [
            'q' => 'Provincia',
            'estado' => 'activos',
        ]));

        $response->assertOk();
        $paginador = $response->viewData('jurisdicciones');
        $this->assertCount(10, $paginador->items());
        $this->assertSame(11, $paginador->total());
        $this->assertStringContainsString('q=Provincia', $paginador->url(2));
        $this->assertStringContainsString('estado=activos', $paginador->url(2));
        $this->assertStringContainsString('page=2', $paginador->url(2));

        $segundaPagina = $this->get($paginador->url(2));
        $segundaPagina->assertOk();
        $this->assertCount(1, $segundaPagina->viewData('jurisdicciones')->items());
    }

    public function test_admin_can_show_active_and_inactive_jurisdicciones_without_audit(): void
    {
        $activa = $this->jurisdiccion('Misiones', true);
        $inactiva = $this->jurisdiccion('Corrientes', false);
        $this->actingAs($this->adminUser());

        foreach ([$activa, $inactiva] as $jurisdiccion) {
            $this->get(route('catalogos.jurisdicciones.show', $jurisdiccion))
                ->assertOk()
                ->assertViewIs('catalogos.jurisdicciones.show')
                ->assertSee('Consultar jurisdicción')
                ->assertSee($jurisdiccion->nombre)
                ->assertSee($jurisdiccion->activo ? 'Activa' : 'Inactiva');
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_superadmin_can_show_jurisdiccion(): void
    {
        $jurisdiccion = $this->jurisdiccion('Misiones', true);

        $this->actingAs($this->superadminUser())
            ->get(route('catalogos.jurisdicciones.show', $jurisdiccion))
            ->assertOk();
    }

    public function test_non_admin_cannot_show_jurisdiccion(): void
    {
        $jurisdiccion = $this->jurisdiccion('Misiones', true);

        $this->actingAs($this->nonAdminUser())
            ->get(route('catalogos.jurisdicciones.show', $jurisdiccion))
            ->assertRedirect(route('perfil.completar'));
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_shows_actions_matching_each_state_and_edit_works_for_both(): void
    {
        $activa = $this->jurisdiccion('Misiones', true);
        $inactiva = $this->jurisdiccion('Corrientes', false);
        $this->actingAs($this->adminUser());

        foreach ([$activa, $inactiva] as $jurisdiccion) {
            $this->get(route('catalogos.jurisdicciones.edit', $jurisdiccion))->assertOk();
        }

        $response = $this->get(route('catalogos.jurisdicciones.index'));
        $response->assertOk()
            ->assertSee('Nombre</th>', false)
            ->assertSee('Estado</th>', false)
            ->assertSee('Acciones</th>', false)
            ->assertDontSee('Slug</th>', false)
            ->assertDontSee('ID</th>', false)
            ->assertSee(route('catalogos.jurisdicciones.show', $activa))
            ->assertSee(route('catalogos.jurisdicciones.show', $inactiva))
            ->assertSee(route('catalogos.jurisdicciones.edit', $activa))
            ->assertSee(route('catalogos.jurisdicciones.edit', $inactiva))
            ->assertSee(route('catalogos.jurisdicciones.inactivar', $activa))
            ->assertSee(route('catalogos.jurisdicciones.reactivar', $inactiva))
            ->assertDontSee(route('catalogos.jurisdicciones.reactivar', $activa))
            ->assertDontSee(route('catalogos.jurisdicciones.inactivar', $inactiva))
            ->assertSee('hidden overflow-x-auto md:block')
            ->assertSee('md:hidden')
            ->assertSee('Limpiar filtros')
            ->assertSee('Activa')
            ->assertSee('Inactiva')
            ->assertSee('Consultar')
            ->assertSee('Editar')
            ->assertSee('Inactivar')
            ->assertSee('Reactivar')
            ->assertSee('js-confirm-action')
            ->assertSee('data-confirm-title="¿Inactivar jurisdicción?"', false)
            ->assertSee('data-confirm-title="¿Reactivar jurisdicción?"', false)
            ->assertSee('data-confirm-text=', false)
            ->assertSee('data-confirm-button=', false)
            ->assertSee('data-cancel-button="Cancelar"', false)
            ->assertSee('data-confirm-icon=', false);
    }

    public function test_controller_rejects_inactivation_when_active_colegio_exists(): void
    {
        $jurisdiccion = $this->jurisdiccion('Misiones', true);
        ColegioEmisor::create([
            'nombre' => 'Colegio de Misiones',
            'sigla' => 'CM',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $this->actingAs($this->adminUser())
            ->patch(route('catalogos.jurisdicciones.inactivar', $jurisdiccion))
            ->assertRedirect(route('catalogos.jurisdicciones.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('jurisdicciones', ['id' => $jurisdiccion->id, 'activo' => true]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    private function jurisdiccion(string $nombre, bool $activo): Jurisdiccion
    {
        return Jurisdiccion::create([
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'activo' => $activo,
        ]);
    }
}
