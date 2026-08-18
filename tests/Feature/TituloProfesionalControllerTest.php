<?php

namespace Tests\Feature;

use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'nombre' =>
            'Ya existe un título profesional con ese nombre.',
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
            'nombre' =>
            'El nombre ingresado genera un slug duplicado.',
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
            'nombre' =>
            'Ya existe un título profesional con ese nombre.',
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
            'nombre' =>
            'El nombre ingresado genera un slug duplicado.',
        ]);
    }

    public function test_toggle_activo_inactivates_titulo(): void
    {
        $admin = $this->adminUser();

        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.titulos.toggle-activo', $titulo));

        $response->assertRedirect(route('catalogos.titulos.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('titulos_profesionales', [
            'id' => $titulo->id,
            'activo' => 0,
        ]);
    }

    public function test_toggle_activo_activates_titulo(): void
    {
        $admin = $this->adminUser();

        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => false,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.titulos.toggle-activo', $titulo));

        $response->assertRedirect(route('catalogos.titulos.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('titulos_profesionales', [
            'id' => $titulo->id,
            'activo' => 1,
        ]);
    }
}
