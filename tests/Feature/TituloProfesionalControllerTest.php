<?php

namespace Tests\Feature;

use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TituloProfesionalControllerTest extends TestCase
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

    public function test_admin_can_view_titulos_index(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('catalogos.titulos.index'));

        $response->assertOk();
        $response->assertSee('Títulos profesionales');
        $response->assertSee('No hay títulos profesionales registrados');
        $response->assertSee('Aún no se cargaron títulos profesionales en el sistema.');
        $response->assertSee('Crear primer título profesional');
    }

    public function test_superadmin_can_view_titulos_index(): void
    {
        $superadmin = $this->superadminUser();

        $response = $this->actingAs($superadmin)->get(route('catalogos.titulos.index'));

        $response->assertOk();
    }

    public function test_non_admin_user_cannot_access_titulos_index(): void
    {
        $user = $this->nonAdminUser();

        $response = $this->actingAs($user)->get(route('catalogos.titulos.index'));

        $response->assertRedirect(route('perfil.completar'));
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('catalogos.titulos.create'));

        $response->assertOk();
        $response->assertSee('Nuevo título profesional');
    }

    public function test_admin_can_store_valid_titulo(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('catalogos.titulos.store'), [
            'nombre' => 'Kinesiólogo',
        ]);

        $response->assertRedirect(route('catalogos.titulos.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('titulos_profesionales', [
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => 1,
        ]);
    }

    public function test_store_trims_nombre_before_persisting(): void
    {
        $admin = $this->adminUser();

        $response = $this
            ->actingAs($admin)
            ->post(route('catalogos.titulos.store'), [
                'nombre' => '  Licenciado en Kinesiología  ',
            ]);

        $response->assertRedirect(
            route('catalogos.titulos.index')
        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('titulos_profesionales', [
            'nombre' => 'Licenciado en Kinesiología',
            'slug' => 'licenciado-en-kinesiologia',
            'activo' => 1,
        ]);
    }

    public function test_store_requires_nombre(): void
    {
        $admin = $this->adminUser();

        $response = $this->from(route('catalogos.titulos.create'))
            ->actingAs($admin)
            ->post(route('catalogos.titulos.store'), [
                'nombre' => '',
            ]);

        $response->assertRedirect(route('catalogos.titulos.create'));
        $response->assertSessionHasErrors('nombre');
    }

    public function test_store_rejects_duplicate_nombre(): void
    {
        $admin = $this->adminUser();

        TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.titulos.create'))
            ->actingAs($admin)
            ->post(route('catalogos.titulos.store'), [
                'nombre' => 'Kinesiólogo',
            ]);

        $response->assertRedirect(route('catalogos.titulos.create'));
        $response->assertSessionHasErrors([
            'nombre' => 'Ya existe un título profesional con ese nombre.',
        ]);
    }

    public function test_store_generates_slug_automatically(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('catalogos.titulos.store'), [
            'nombre' => 'Licenciado en Kinesiología',
        ]);

        $this->assertDatabaseHas('titulos_profesionales', [
            'nombre' => 'Licenciado en Kinesiología',
            'slug' => 'licenciado-en-kinesiologia',
        ]);
    }

    public function test_store_rejects_when_nombre_generates_duplicate_slug(): void
    {
        $admin = $this->adminUser();

        TituloProfesional::create([
            'nombre' => 'Kinesiólogo!',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.titulos.create'))
            ->actingAs($admin)
            ->post(route('catalogos.titulos.store'), [
                'nombre' => 'Kinesiólogo ',
            ]);

        $response->assertRedirect(route('catalogos.titulos.create'));
        $response->assertSessionHasErrors([
            'nombre' => 'El nombre ingresado genera un slug duplicado.',
        ]);
    }

    public function test_admin_can_view_edit_form(): void
    {
        $admin = $this->adminUser();

        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('catalogos.titulos.edit', $titulo));

        $response->assertOk();
        $response->assertSee('Editar título profesional');
        $response->assertSee('Kinesiólogo');
    }

    public function test_admin_can_update_titulo(): void
    {
        $admin = $this->adminUser();

        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('catalogos.titulos.update', $titulo), [
            'nombre' => 'Kinesiólogo Fisiatra',
        ]);

        $response->assertRedirect(route('catalogos.titulos.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('titulos_profesionales', [
            'id' => $titulo->id,
            'nombre' => 'Kinesiólogo Fisiatra',
            'slug' => 'kinesiologo-fisiatra',
        ]);
    }

    public function test_update_trims_nombre_before_persisting(): void
    {
        $admin = $this->adminUser();

        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->put(
                route('catalogos.titulos.update', $titulo),
                [
                    'nombre' => '  Kinesiólogo Fisiatra  ',
                ]
            );

        $response->assertRedirect(
            route('catalogos.titulos.index')
        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('titulos_profesionales', [
            'id' => $titulo->id,
            'nombre' => 'Kinesiólogo Fisiatra',
            'slug' => 'kinesiologo-fisiatra',
        ]);
    }

    public function test_update_allows_new_nombre_when_another_different_titulo_exists(): void
    {
        $admin = $this->adminUser();

        TituloProfesional::create([
            'nombre' => 'Fisioterapeuta',
            'slug' => 'fisioterapeuta',
            'activo' => true,
        ]);

        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->put(
                route('catalogos.titulos.update', $titulo),
                [
                    'nombre' => 'Licenciado en Kinesiología',
                ]
            );

        $response->assertRedirect(
            route('catalogos.titulos.index')
        );

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('titulos_profesionales', [
            'id' => $titulo->id,
            'nombre' => 'Licenciado en Kinesiología',
            'slug' => 'licenciado-en-kinesiologia',
        ]);
    }

    public function test_update_allows_same_nombre_for_same_record(): void
    {
        $admin = $this->adminUser();

        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('catalogos.titulos.update', $titulo), [
            'nombre' => 'Kinesiólogo',
        ]);

        $response->assertRedirect(route('catalogos.titulos.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_update_rejects_duplicate_nombre_from_another_record(): void
    {
        $admin = $this->adminUser();

        $titulo1 = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $titulo2 = TituloProfesional::create([
            'nombre' => 'Fisioterapeuta',
            'slug' => 'fisioterapeuta',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.titulos.edit', $titulo2))
            ->actingAs($admin)
            ->put(route('catalogos.titulos.update', $titulo2), [
                'nombre' => $titulo1->nombre,
            ]);

        $response->assertRedirect(route('catalogos.titulos.edit', $titulo2));
        $response->assertSessionHasErrors([
            'nombre' => 'Ya existe un título profesional con ese nombre.',
        ]);
    }

    public function test_update_rejects_when_new_nombre_generates_duplicate_slug(): void
    {
        $admin = $this->adminUser();

        TituloProfesional::create([
            'nombre' => 'Kinesiólogo!',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $titulo = TituloProfesional::create([
            'nombre' => 'Fisioterapeuta',
            'slug' => 'fisioterapeuta',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.titulos.edit', $titulo))
            ->actingAs($admin)
            ->put(route('catalogos.titulos.update', $titulo), [
                'nombre' => 'Kinesiólogo ',
            ]);

        $response->assertRedirect(route('catalogos.titulos.edit', $titulo));
        $response->assertSessionHasErrors([
            'nombre' => 'El nombre ingresado genera un slug duplicado.',
        ]);
    }

    public function test_inactivar_inactivates_titulo(): void
    {
        $admin = $this->adminUser();

        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.titulos.inactivar', $titulo));

        $response->assertRedirect(route('catalogos.titulos.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('titulos_profesionales', [
            'id' => $titulo->id,
            'activo' => 0,
        ]);
    }

    public function test_reactivar_activates_titulo(): void
    {
        $admin = $this->adminUser();

        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => false,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.titulos.reactivar', $titulo));

        $response->assertRedirect(route('catalogos.titulos.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('titulos_profesionales', [
            'id' => $titulo->id,
            'activo' => 1,
        ]);
    }

    public function test_repeated_state_requests_do_not_toggle_titulo_or_audit(): void
    {
        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => false,
        ]);

        $this->actingAs($this->adminUser())
            ->patch(route('catalogos.titulos.inactivar', $titulo))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('titulos_profesionales', [
            'id' => $titulo->id,
            'activo' => false,
        ]);

        $titulo->update(['activo' => true]);

        $this->patch(route('catalogos.titulos.reactivar', $titulo))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('titulos_profesionales', [
            'id' => $titulo->id,
            'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_searches_by_name_and_shows_empty_result_message(): void
    {
        $this->titulo('Kinesiólogo', true);
        $this->titulo('Fisioterapeuta', true);

        $response = $this->actingAs($this->adminUser())
            ->get(route('catalogos.titulos.index', ['q' => 'Kinesió']));

        $response->assertOk();
        $this->assertSame(['Kinesiólogo'], $response->viewData('titulos')->pluck('nombre')->all());
        $response->assertSee('value="Kinesió"', false);

        $response = $this->get(route('catalogos.titulos.index', ['q' => 'Odontólogo']));
        $response->assertOk()->assertSee('No se encontraron títulos profesionales');
        $this->assertSame(0, $response->viewData('titulos')->total());
        $response->assertDontSee('Crear primer título profesional');
    }

    public function test_index_filters_all_active_and_inactive_titulos(): void
    {
        $this->titulo('Kinesiólogo', true);
        $this->titulo('Fisioterapeuta', false);
        $this->actingAs($this->adminUser());

        foreach (['todos' => ['Fisioterapeuta', 'Kinesiólogo'], 'activos' => ['Kinesiólogo'], 'inactivos' => ['Fisioterapeuta']] as $estado => $nombres) {
            $response = $this->get(route('catalogos.titulos.index', ['estado' => $estado]));
            $response->assertOk();
            $this->assertSame($nombres, $response->viewData('titulos')->pluck('nombre')->all());
        }

        $response = $this->get(route('catalogos.titulos.index'));
        $this->assertSame(2, $response->viewData('titulos')->total());

        $response = $this->get(route('catalogos.titulos.index', ['estado' => 'desconocido']));
        $response->assertOk();
        $this->assertSame(2, $response->viewData('titulos')->total());
        $response->assertSee('<option value="todos" selected>', false);
    }

    public function test_index_combines_search_and_state_filter(): void
    {
        $this->titulo('Kinesiólogo general', true);
        $this->titulo('Kinesiólogo deportivo', false);
        $this->titulo('Fisioterapeuta', false);

        $response = $this->actingAs($this->adminUser())->get(route('catalogos.titulos.index', [
            'q' => 'Kinesiólogo',
            'estado' => 'inactivos',
        ]));

        $response->assertOk();
        $this->assertSame(['Kinesiólogo deportivo'], $response->viewData('titulos')->pluck('nombre')->all());
    }

    public function test_index_pagination_keeps_search_and_state_query_string(): void
    {
        for ($numero = 1; $numero <= 11; $numero++) {
            $this->titulo('Título profesional '.chr(64 + $numero), true);
        }

        $response = $this->actingAs($this->adminUser())->get(route('catalogos.titulos.index', [
            'q' => 'Título profesional',
            'estado' => 'activos',
        ]));

        $response->assertOk();
        $paginador = $response->viewData('titulos');
        $this->assertCount(10, $paginador->items());
        $this->assertSame(11, $paginador->total());
        $this->assertStringContainsString('q=T%C3%ADtulo%20profesional', $paginador->url(2));
        $this->assertStringContainsString('estado=activos', $paginador->url(2));
        $this->assertStringContainsString('page=2', $paginador->url(2));

        $segundaPagina = $this->get($paginador->url(2));
        $segundaPagina->assertOk();
        $this->assertCount(1, $segundaPagina->viewData('titulos')->items());
    }

    public function test_admin_can_show_active_and_inactive_titulos_without_changes_or_audit(): void
    {
        $activo = $this->titulo('Kinesiólogo', true);
        $inactivo = $this->titulo('Fisioterapeuta', false);
        $this->actingAs($this->adminUser());

        foreach ([$activo, $inactivo] as $titulo) {
            $original = $titulo->fresh()->getAttributes();

            $this->get(route('catalogos.titulos.show', $titulo))
                ->assertOk()
                ->assertViewIs('catalogos.titulos.show')
                ->assertSee('Consultar título profesional')
                ->assertSee($titulo->nombre)
                ->assertSee($titulo->activo ? 'Activo' : 'Inactivo')
                ->assertDontSee('action="'.route('catalogos.titulos.inactivar', $titulo).'"', false)
                ->assertDontSee('action="'.route('catalogos.titulos.reactivar', $titulo).'"', false);

            $this->assertSame($original, $titulo->fresh()->getAttributes());
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_superadmin_can_show_titulo(): void
    {
        $titulo = $this->titulo('Kinesiólogo', true);

        $this->actingAs($this->superadminUser())
            ->get(route('catalogos.titulos.show', $titulo))
            ->assertOk();
    }

    public function test_non_admin_cannot_show_titulo(): void
    {
        $titulo = $this->titulo('Kinesiólogo', true);

        $this->actingAs($this->nonAdminUser())
            ->get(route('catalogos.titulos.show', $titulo))
            ->assertRedirect(route('perfil.completar'));
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_shows_actions_matching_each_state_and_edit_works_for_both(): void
    {
        $activo = $this->titulo('Kinesiólogo', true);
        $inactivo = $this->titulo('Fisioterapeuta', false);
        $this->actingAs($this->adminUser());

        foreach ([$activo, $inactivo] as $titulo) {
            $this->get(route('catalogos.titulos.edit', $titulo))->assertOk();
        }

        $response = $this->get(route('catalogos.titulos.index'));
        $response->assertOk()
            ->assertSee('Nombre</th>', false)
            ->assertSee('Estado</th>', false)
            ->assertSee('Acciones</th>', false)
            ->assertDontSee('Slug</th>', false)
            ->assertDontSee('ID</th>', false)
            ->assertSee(route('catalogos.titulos.show', $activo))
            ->assertSee(route('catalogos.titulos.show', $inactivo))
            ->assertSee(route('catalogos.titulos.edit', $activo))
            ->assertSee(route('catalogos.titulos.edit', $inactivo))
            ->assertSee(route('catalogos.titulos.inactivar', $activo))
            ->assertSee(route('catalogos.titulos.reactivar', $inactivo))
            ->assertDontSee(route('catalogos.titulos.reactivar', $activo))
            ->assertDontSee(route('catalogos.titulos.inactivar', $inactivo))
            ->assertSee('hidden overflow-x-auto md:block')
            ->assertSee('md:hidden')
            ->assertSee('Limpiar filtros')
            ->assertSee('Activo')
            ->assertSee('Inactivo')
            ->assertSee('Consultar')
            ->assertSee('Editar')
            ->assertSee('Inactivar')
            ->assertSee('Reactivar')
            ->assertSee('js-confirm-action')
            ->assertSee('data-confirm-title="¿Inactivar título profesional?"', false)
            ->assertSee('data-confirm-title="¿Reactivar título profesional?"', false)
            ->assertSee('data-confirm-text=', false)
            ->assertSee('data-confirm-button=', false)
            ->assertSee('data-cancel-button="Cancelar"', false)
            ->assertSee('data-confirm-icon=', false);
    }

    private function titulo(string $nombre, bool $activo): TituloProfesional
    {
        return TituloProfesional::create([
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'activo' => $activo,
        ]);
    }
}
