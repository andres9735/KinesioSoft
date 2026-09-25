<?php

namespace Tests\Feature;

use App\Models\EspecialidadKinesiologia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EspecialidadKinesiologiaControllerTest extends TestCase
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

    public function test_admin_can_view_especialidades_index(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('catalogos.especialidades.index'));

        $response->assertOk();
        $response->assertSee('Especialidades de kinesiología');
    }

    public function test_superadmin_can_view_especialidades_index(): void
    {
        $superadmin = $this->superadminUser();

        $response = $this->actingAs($superadmin)->get(route('catalogos.especialidades.index'));

        $response->assertOk();
    }

    public function test_non_admin_user_cannot_access_especialidades_index(): void
    {
        $user = $this->nonAdminUser();

        $response = $this->actingAs($user)->get(route('catalogos.especialidades.index'));

        $response->assertRedirect(route('perfil.completar'));
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('catalogos.especialidades.create'));

        $response->assertOk();
        $response->assertSee('Nueva especialidad de kinesiología');
    }

    public function test_admin_can_store_valid_especialidad(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('catalogos.especialidades.store'), [
            'nombre' => 'Kinesiología Deportiva',
        ]);

        $response->assertRedirect(route('catalogos.especialidades.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => 1,
        ]);
    }

    public function test_store_normalizes_nombre_before_persisting(): void
    {
        $admin = $this->adminUser();

        $response = $this
            ->actingAs($admin)
            ->post(route('catalogos.especialidades.store'), [
                'nombre' => '  Kinesiología   Deportiva  ',
            ]);

        $response->assertRedirect(
            route('catalogos.especialidades.index')
        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => 1,
        ]);
    }

    public function test_store_always_creates_active_especialidad_even_if_state_is_submitted(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('catalogos.especialidades.store'), [
                'nombre' => 'Rehabilitación Respiratoria',
                'activo' => false,
            ])
            ->assertRedirect(route('catalogos.especialidades.index'));

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'nombre' => 'Rehabilitación Respiratoria',
            'slug' => 'rehabilitacion-respiratoria',
            'activo' => true,
        ]);
    }

    public function test_store_rejects_invalid_nombre_characters(): void
    {
        $admin = $this->adminUser();

        $response = $this
            ->from(route('catalogos.especialidades.create'))
            ->actingAs($admin)
            ->post(route('catalogos.especialidades.store'), [
                'nombre' => 'Kinesiología / Deportiva',
            ]);

        $response->assertRedirect(
            route('catalogos.especialidades.create')
        );

        $response->assertSessionHasErrors([
            'nombre' => 'El nombre solo puede contener letras y espacios.',
        ]);

        $this->assertDatabaseMissing(
            'especialidades_kinesiologia',
            [
                'nombre' => 'Kinesiología / Deportiva',
            ]
        );
    }

    public function test_store_requires_nombre(): void
    {
        $admin = $this->adminUser();

        $response = $this->from(route('catalogos.especialidades.create'))
            ->actingAs($admin)
            ->post(route('catalogos.especialidades.store'), [
                'nombre' => '',
            ]);

        $response->assertRedirect(
            route('catalogos.especialidades.create')
        );

        $response->assertSessionHasErrors([
            'nombre' => 'Debe ingresar el nombre de la especialidad.',
        ]);
    }

    public function test_store_rejects_duplicate_nombre(): void
    {
        $admin = $this->adminUser();

        EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.especialidades.create'))
            ->actingAs($admin)
            ->post(route('catalogos.especialidades.store'), [
                'nombre' => 'Kinesiología Deportiva',
            ]);

        $response->assertRedirect(
            route('catalogos.especialidades.create')
        );

        $response->assertSessionHasErrors([
            'nombre' => 'Ya existe una especialidad de kinesiología con ese nombre.',
        ]);
    }

    public function test_store_generates_slug_automatically(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('catalogos.especialidades.store'), [
            'nombre' => 'Rehabilitación Respiratoria',
        ]);

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'nombre' => 'Rehabilitación Respiratoria',
            'slug' => 'rehabilitacion-respiratoria',
        ]);
    }

    public function test_store_rejects_when_nombre_generates_duplicate_slug(): void
    {
        $admin = $this->adminUser();

        EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.especialidades.create'))
            ->actingAs($admin)
            ->post(route('catalogos.especialidades.store'), [
                'nombre' => 'Kinesiologia Deportiva',
            ]);

        $response->assertRedirect(
            route('catalogos.especialidades.create')
        );

        $response->assertSessionHasErrors([
            'nombre' => 'El nombre ingresado genera un slug que ya existe. Intente con una variante distinta.',
        ]);
    }

    public function test_admin_can_view_edit_form(): void
    {
        $admin = $this->adminUser();

        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('catalogos.especialidades.edit', $especialidad));

        $response->assertOk();
        $response->assertSee('Editar especialidad de kinesiología');
        $response->assertSee('Kinesiología Deportiva');
    }

    public function test_admin_can_update_especialidad(): void
    {
        $admin = $this->adminUser();

        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('catalogos.especialidades.update', $especialidad), [
            'nombre' => 'Kinesiología Traumatológica',
        ]);

        $response->assertRedirect(route('catalogos.especialidades.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'id' => $especialidad->id,
            'nombre' => 'Kinesiología Traumatológica',
            'slug' => 'kinesiologia-traumatologica',
        ]);
    }

    public function test_update_normalizes_nombre_before_persisting(): void
    {
        $admin = $this->adminUser();

        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->put(
                route(
                    'catalogos.especialidades.update',
                    $especialidad
                ),
                [
                    'nombre' => '  Kinesiología   Traumatológica  ',
                ]
            );

        $response->assertRedirect(
            route('catalogos.especialidades.index')
        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas(
            'especialidades_kinesiologia',
            [
                'id' => $especialidad->id,
                'nombre' => 'Kinesiología Traumatológica',
                'slug' => 'kinesiologia-traumatologica',
            ]
        );
    }

    public function test_update_rejects_invalid_nombre_characters(): void
    {
        $admin = $this->adminUser();

        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $response = $this
            ->from(
                route(
                    'catalogos.especialidades.edit',
                    $especialidad
                )
            )
            ->actingAs($admin)
            ->put(
                route(
                    'catalogos.especialidades.update',
                    $especialidad
                ),
                [
                    'nombre' => 'Kinesiología / Traumatológica',
                ]
            );

        $response->assertRedirect(
            route(
                'catalogos.especialidades.edit',
                $especialidad
            )
        );

        $response->assertSessionHasErrors([
            'nombre' => 'El nombre solo puede contener letras y espacios.',
        ]);

        $this->assertDatabaseHas(
            'especialidades_kinesiologia',
            [
                'id' => $especialidad->id,
                'nombre' => 'Kinesiología Deportiva',
                'slug' => 'kinesiologia-deportiva',
            ]
        );
    }

    public function test_update_requires_nombre(): void
    {
        $admin = $this->adminUser();

        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $response = $this
            ->from(
                route(
                    'catalogos.especialidades.edit',
                    $especialidad
                )
            )
            ->actingAs($admin)
            ->put(
                route(
                    'catalogos.especialidades.update',
                    $especialidad
                ),
                [
                    'nombre' => '',
                ]
            );

        $response->assertRedirect(
            route(
                'catalogos.especialidades.edit',
                $especialidad
            )
        );

        $response->assertSessionHasErrors([
            'nombre' => 'Debe ingresar el nombre de la especialidad.',
        ]);

        $this->assertDatabaseHas(
            'especialidades_kinesiologia',
            [
                'id' => $especialidad->id,
                'nombre' => 'Kinesiología Deportiva',
                'slug' => 'kinesiologia-deportiva',
            ]
        );
    }

    public function test_update_allows_same_nombre_for_same_record(): void
    {
        $admin = $this->adminUser();

        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('catalogos.especialidades.update', $especialidad), [
            'nombre' => 'Kinesiología Deportiva',
        ]);

        $response->assertRedirect(route('catalogos.especialidades.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_update_rejects_duplicate_nombre_from_another_record(): void
    {
        $admin = $this->adminUser();

        $especialidad1 = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $especialidad2 = EspecialidadKinesiologia::create([
            'nombre' => 'Rehabilitación Respiratoria',
            'slug' => 'rehabilitacion-respiratoria',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.especialidades.edit', $especialidad2))
            ->actingAs($admin)
            ->put(route('catalogos.especialidades.update', $especialidad2), [
                'nombre' => $especialidad1->nombre,
            ]);

        $response->assertRedirect(route('catalogos.especialidades.edit', $especialidad2));
        $response->assertSessionHasErrors([
            'nombre' => 'Ya existe una especialidad de kinesiología con ese nombre.',
        ]);
    }

    public function test_update_rejects_when_new_nombre_generates_duplicate_slug(): void
    {
        $admin = $this->adminUser();

        EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'Rehabilitación Respiratoria',
            'slug' => 'rehabilitacion-respiratoria',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.especialidades.edit', $especialidad))
            ->actingAs($admin)
            ->put(route('catalogos.especialidades.update', $especialidad), [
                'nombre' => 'Kinesiologia Deportiva',
            ]);

        $response->assertRedirect(route('catalogos.especialidades.edit', $especialidad));
        $response->assertSessionHasErrors('nombre');
        $response->assertSessionHasErrors([
            'nombre' => 'El nombre ingresado genera un slug que ya existe. Intente con una variante distinta.',
        ]);
    }

    public function test_inactivar_inactivates_especialidad(): void
    {
        $admin = $this->adminUser();

        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.especialidades.inactivar', $especialidad));

        $response->assertRedirect(route('catalogos.especialidades.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'id' => $especialidad->id,
            'activo' => 0,
        ]);
    }

    public function test_reactivar_activates_especialidad(): void
    {
        $admin = $this->adminUser();

        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => false,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.especialidades.reactivar', $especialidad));

        $response->assertRedirect(route('catalogos.especialidades.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'id' => $especialidad->id,
            'activo' => 1,
        ]);
    }

    public function test_repeated_state_requests_do_not_toggle_especialidad_or_audit(): void
    {
        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => false,
        ]);

        $this->actingAs($this->adminUser())
            ->patch(route('catalogos.especialidades.inactivar', $especialidad))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'id' => $especialidad->id,
            'activo' => false,
        ]);

        $especialidad->update(['activo' => true]);

        $this->patch(route('catalogos.especialidades.reactivar', $especialidad))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'id' => $especialidad->id,
            'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_searches_only_by_name_and_shows_empty_result(): void
    {
        $this->especialidad('Kinesiología Deportiva', true);
        $this->especialidad('Rehabilitación Respiratoria', true);

        $response = $this->actingAs($this->adminUser())
            ->get(route('catalogos.especialidades.index', ['q' => 'Deportiva']));

        $response->assertOk()->assertSee('value="Deportiva"', false);
        $this->assertSame(
            ['Kinesiología Deportiva'],
            $response->viewData('especialidades')->pluck('nombre')->all()
        );

        $response = $this->get(route('catalogos.especialidades.index', ['q' => 'Inexistente']));
        $response->assertOk()
            ->assertSee('No se encontraron especialidades')
            ->assertSee('Pruebe con otros criterios de búsqueda.')
            ->assertDontSee('Crear primera especialidad');
        $this->assertSame(0, $response->viewData('especialidades')->total());
    }

    public function test_index_filters_all_active_inactive_and_unknown_state_safely(): void
    {
        $this->especialidad('Kinesiología Deportiva', true);
        $this->especialidad('Rehabilitación Respiratoria', false);
        $this->actingAs($this->adminUser());

        foreach ([
            'todos' => ['Kinesiología Deportiva', 'Rehabilitación Respiratoria'],
            'activos' => ['Kinesiología Deportiva'],
            'inactivos' => ['Rehabilitación Respiratoria'],
        ] as $estado => $nombres) {
            $response = $this->get(route('catalogos.especialidades.index', ['estado' => $estado]));
            $response->assertOk();
            $this->assertSame($nombres, $response->viewData('especialidades')->pluck('nombre')->all());
        }

        $response = $this->get(route('catalogos.especialidades.index'));
        $response->assertOk();
        $this->assertSame(2, $response->viewData('especialidades')->total());

        $response = $this->get(route('catalogos.especialidades.index', ['estado' => 'desconocido']));
        $response->assertOk()->assertSee('<option value="todos" selected>', false);
        $this->assertSame(2, $response->viewData('especialidades')->total());
    }

    public function test_index_combines_name_search_with_state_filter(): void
    {
        $this->especialidad('Kinesiología Deportiva', true);
        $this->especialidad('Kinesiología Respiratoria', false);
        $this->especialidad('Rehabilitación Deportiva', false);

        $response = $this->actingAs($this->adminUser())->get(
            route('catalogos.especialidades.index', [
                'q' => 'Kinesiología',
                'estado' => 'inactivos',
            ])
        );

        $response->assertOk();
        $this->assertSame(
            ['Kinesiología Respiratoria'],
            $response->viewData('especialidades')->pluck('nombre')->all()
        );
    }

    public function test_index_paginates_ten_and_keeps_query_string(): void
    {
        for ($numero = 1; $numero <= 11; $numero++) {
            $this->especialidad('Especialidad '.chr(64 + $numero), true);
        }

        $response = $this->actingAs($this->adminUser())->get(
            route('catalogos.especialidades.index', [
                'q' => 'Especialidad',
                'estado' => 'activos',
            ])
        );

        $response->assertOk();
        $paginador = $response->viewData('especialidades');
        $this->assertCount(10, $paginador->items());
        $this->assertSame(11, $paginador->total());
        $this->assertStringContainsString('q=Especialidad', $paginador->url(2));
        $this->assertStringContainsString('estado=activos', $paginador->url(2));
        $this->assertStringContainsString('page=2', $paginador->url(2));

        $segundaPagina = $this->get($paginador->url(2));
        $segundaPagina->assertOk();
        $this->assertCount(1, $segundaPagina->viewData('especialidades')->items());
    }

    public function test_admin_can_show_active_and_inactive_especialidades_without_changes_or_audit(): void
    {
        $activa = $this->especialidad('Kinesiología Deportiva', true);
        $inactiva = $this->especialidad('Rehabilitación Respiratoria', false);
        $this->actingAs($this->adminUser());

        foreach ([$activa, $inactiva] as $especialidad) {
            $original = $especialidad->fresh()->getAttributes();

            $this->get(route('catalogos.especialidades.show', $especialidad))
                ->assertOk()
                ->assertViewIs('catalogos.especialidades.show')
                ->assertSee('Consultar especialidad de kinesiología')
                ->assertSee($especialidad->nombre)
                ->assertSee($especialidad->activo ? 'Activa' : 'Inactiva')
                ->assertDontSee('name="nombre"', false)
                ->assertDontSee('action="'.route('catalogos.especialidades.update', $especialidad).'"', false)
                ->assertDontSee('action="'.route('catalogos.especialidades.inactivar', $especialidad).'"', false)
                ->assertDontSee('action="'.route('catalogos.especialidades.reactivar', $especialidad).'"', false);

            $this->assertSame($original, $especialidad->fresh()->getAttributes());
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_superadmin_can_show_especialidad(): void
    {
        $especialidad = $this->especialidad('Kinesiología Deportiva', true);

        $this->actingAs($this->superadminUser())
            ->get(route('catalogos.especialidades.show', $especialidad))
            ->assertOk();
    }

    public function test_non_admin_cannot_show_especialidad(): void
    {
        $especialidad = $this->especialidad('Kinesiología Deportiva', true);

        $this->actingAs($this->nonAdminUser())
            ->get(route('catalogos.especialidades.show', $especialidad))
            ->assertRedirect(route('perfil.completar'));
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_renders_desktop_mobile_components_and_state_actions(): void
    {
        $activa = $this->especialidad('Kinesiología Deportiva', true);
        $inactiva = $this->especialidad('Rehabilitación Respiratoria', false);
        $this->actingAs($this->adminUser());

        foreach ([$activa, $inactiva] as $especialidad) {
            $this->get(route('catalogos.especialidades.edit', $especialidad))->assertOk();
        }

        $response = $this->get(route('catalogos.especialidades.index'));
        $response->assertOk()
            ->assertSee('Nombre</th>', false)
            ->assertSee('Estado</th>', false)
            ->assertSee('Acciones</th>', false)
            ->assertDontSee('Slug</th>', false)
            ->assertDontSee('ID</th>', false)
            ->assertSee('name="q"', false)
            ->assertSee('name="estado"', false)
            ->assertSee('hidden overflow-x-auto md:block')
            ->assertSee('md:hidden')
            ->assertSee('Limpiar filtros')
            ->assertSee('Activa')
            ->assertSee('Inactiva')
            ->assertSee('Consultar')
            ->assertSee('Editar')
            ->assertSee('Inactivar')
            ->assertSee('Reactivar')
            ->assertSee(route('catalogos.especialidades.show', $activa))
            ->assertSee(route('catalogos.especialidades.show', $inactiva))
            ->assertSee(route('catalogos.especialidades.edit', $activa))
            ->assertSee(route('catalogos.especialidades.edit', $inactiva))
            ->assertSee(route('catalogos.especialidades.inactivar', $activa))
            ->assertSee(route('catalogos.especialidades.reactivar', $inactiva))
            ->assertDontSee(route('catalogos.especialidades.reactivar', $activa))
            ->assertDontSee(route('catalogos.especialidades.inactivar', $inactiva))
            ->assertSee('js-confirm-action')
            ->assertSee('data-confirm-title="¿Inactivar especialidad?"', false)
            ->assertSee('data-confirm-title="¿Reactivar especialidad?"', false)
            ->assertSee('data-confirm-text=', false)
            ->assertSee('data-confirm-button=', false)
            ->assertSee('data-cancel-button="Cancelar"', false)
            ->assertSee('data-confirm-icon=', false);
    }

    public function test_inactive_especialidad_can_be_edited_without_changing_state(): void
    {
        $especialidad = $this->especialidad('Kinesiología Deportiva', false);
        $this->actingAs($this->adminUser())
            ->get(route('catalogos.especialidades.create'))
            ->assertOk()
            ->assertDontSee('name="activo"', false);

        $this->get(route('catalogos.especialidades.edit', $especialidad))
            ->assertOk()
            ->assertDontSee('name="activo"', false);

        $this->put(route('catalogos.especialidades.update', $especialidad), [
            'nombre' => 'Kinesiología Respiratoria',
            'activo' => true,
        ])->assertRedirect(route('catalogos.especialidades.index'));

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'id' => $especialidad->id,
            'nombre' => 'Kinesiología Respiratoria',
            'slug' => 'kinesiologia-respiratoria',
            'activo' => false,
        ]);
    }

    private function especialidad(string $nombre, bool $activo): EspecialidadKinesiologia
    {
        return EspecialidadKinesiologia::create([
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'activo' => $activo,
        ]);
    }
}
