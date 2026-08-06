<?php

namespace Tests\Feature;

use App\Models\EspecialidadKinesiologia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'nombre' =>
            'El nombre solo puede contener letras y espacios.',
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
            'nombre' =>
            'Debe ingresar el nombre de la especialidad.',
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
            'nombre' =>
            'Ya existe una especialidad de kinesiología con ese nombre.',
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
            'nombre' =>
            'El nombre ingresado genera un slug que ya existe. Intente con una variante distinta.',
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
                    'nombre' =>
                    '  Kinesiología   Traumatológica  ',
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
            'nombre' =>
            'El nombre solo puede contener letras y espacios.',
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
            'nombre' =>
            'Debe ingresar el nombre de la especialidad.',
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
            'nombre' =>
            'Ya existe una especialidad de kinesiología con ese nombre.',
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

    public function test_toggle_activo_inactivates_especialidad(): void
    {
        $admin = $this->adminUser();

        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.especialidades.toggle-activo', $especialidad));

        $response->assertRedirect(route('catalogos.especialidades.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'id' => $especialidad->id,
            'activo' => 0,
        ]);
    }

    public function test_toggle_activo_activates_especialidad(): void
    {
        $admin = $this->adminUser();

        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => false,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.especialidades.toggle-activo', $especialidad));

        $response->assertRedirect(route('catalogos.especialidades.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'id' => $especialidad->id,
            'activo' => 1,
        ]);
    }
}
