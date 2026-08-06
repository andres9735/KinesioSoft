<?php

namespace Tests\Feature;

use App\Models\Jurisdiccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'nombre' =>
            'El nombre solo puede contener letras y espacios.',
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
            'nombre' =>
            'Ya existe una jurisdicción con ese nombre.',
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
            'nombre' =>
            'El nombre ingresado genera un slug que ya existe. Intente con una variante distinta.',
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
            'nombre' =>
            'El nombre solo puede contener letras y espacios.',
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
            'nombre' =>
                'Ya existe una jurisdicción con ese nombre.',
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
            'nombre' =>
                'El nombre ingresado genera un slug que ya existe. Intente con una variante distinta.',
        ]);
    }

    public function test_toggle_activo_inactivates_jurisdiccion(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.jurisdicciones.toggle-activo', $jurisdiccion));

        $response->assertRedirect(route('catalogos.jurisdicciones.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('jurisdicciones', [
            'id' => $jurisdiccion->id,
            'activo' => 0,
        ]);
    }

    public function test_toggle_activo_activates_jurisdiccion(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => false,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.jurisdicciones.toggle-activo', $jurisdiccion));

        $response->assertRedirect(route('catalogos.jurisdicciones.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('jurisdicciones', [
            'id' => $jurisdiccion->id,
            'activo' => 1,
        ]);
    }
}
