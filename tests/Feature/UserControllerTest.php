<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findOrCreate(RoleEnum::SUPERADMIN->value, 'web');
        Role::findOrCreate(RoleEnum::ADMINISTRADOR->value, 'web');
        Role::findOrCreate(RoleEnum::KINESIOLOGO->value, 'web');
        Role::findOrCreate(RoleEnum::PACIENTE->value, 'web');
    }

    private function superadmin(): User
    {
        $user = User::factory()->create([
            'name' => 'Super',
            'apellido' => 'Admin',
            'email' => 'superadmin_' . uniqid() . '@example.com',
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::SUPERADMIN->value);

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'name' => 'Admin',
            'apellido' => 'Sistema',
            'email' => 'admin_' . uniqid() . '@example.com',
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::ADMINISTRADOR->value);

        return $user;
    }

    private function paciente(): User
    {
        $user = User::factory()->create([
            'name' => 'Paciente',
            'apellido' => 'Prueba',
            'email' => 'paciente_' . uniqid() . '@example.com',
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::PACIENTE->value);

        return $user;
    }

    private function kinesiologo(): User
    {
        $user = User::factory()->create([
            'name' => 'Kinesio',
            'apellido' => 'Prueba',
            'email' => 'kinesio_' . uniqid() . '@example.com',
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        return $user;
    }

    public function test_admin_can_view_users_index(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('usuarios.index'));

        $response->assertOk();
        $response->assertSee('Usuarios');
    }

    public function test_superadmin_can_view_users_index(): void
    {
        $superadmin = $this->superadmin();

        $response = $this->actingAs($superadmin)->get(route('usuarios.index'));

        $response->assertOk();
    }

    public function test_non_admin_user_cannot_access_users_index(): void
    {
        $paciente = $this->paciente();

        $response = $this->actingAs($paciente)->get(route('usuarios.index'));

        $response->assertRedirect(route('perfil.completar'));
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('usuarios.create'));

        $response->assertOk();
        $response->assertSee('Nuevo usuario');
        $response->assertSee('Paciente');
        $response->assertSee('Kinesi');
        $response->assertDontSee('Administrador');
    }

    public function test_superadmin_can_view_create_form_with_admin_role_available(): void
    {
        $superadmin = $this->superadmin();

        $response = $this->actingAs($superadmin)->get(route('usuarios.create'));

        $response->assertOk();
        $response->assertSee('Administrador');
    }

    public function test_admin_can_store_valid_paciente_user_and_create_paciente_profile(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Andrés',
            'apellido' => 'Schupiak',
            'email' => 'andres@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'rol' => RoleEnum::PACIENTE->value,
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('success');

        $user = User::where('email', 'andres@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(RoleEnum::PACIENTE->value));
        $this->assertTrue((bool) $user->activo);

        $paciente = Paciente::where('user_id', $user->id)->first();

        $this->assertNotNull($paciente);
        $this->assertMatchesRegularExpression('/^HC-\d{6}$/', $paciente->nro_historia_clinica);
    }

    public function test_admin_can_store_valid_kinesiologo_user_without_creating_paciente_profile(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Fabiana',
            'apellido' => 'Alenka',
            'email' => 'fabiana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'rol' => RoleEnum::KINESIOLOGO->value,
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('success');

        $user = User::where('email', 'fabiana@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(RoleEnum::KINESIOLOGO->value));
        $this->assertNull($user->paciente);
    }

    public function test_superadmin_can_store_valid_admin_user(): void
    {
        $superadmin = $this->superadmin();

        $response = $this->actingAs($superadmin)->post(route('usuarios.store'), [
            'name' => 'Administrador',
            'apellido' => 'Secundario',
            'email' => 'admin2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'rol' => RoleEnum::ADMINISTRADOR->value,
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('success');

        $user = User::where('email', 'admin2@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(RoleEnum::ADMINISTRADOR->value));
    }

    public function test_admin_cannot_store_admin_user(): void
    {
        $admin = $this->admin();

        $response = $this->from(route('usuarios.create'))
            ->actingAs($admin)
            ->post(route('usuarios.store'), [
                'name' => 'Administrador',
                'apellido' => 'Secundario',
                'email' => 'admin2@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'rol' => RoleEnum::ADMINISTRADOR->value,
            ]);

        $response->assertRedirect(route('usuarios.create'));
        $response->assertSessionHasErrors('rol');
    }

    public function test_store_requires_name(): void
    {
        $admin = $this->admin();

        $response = $this->from(route('usuarios.create'))
            ->actingAs($admin)
            ->post(route('usuarios.store'), [
                'name' => '',
                'apellido' => 'Schupiak',
                'email' => 'andres@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'rol' => RoleEnum::PACIENTE->value,
            ]);

        $response->assertRedirect(route('usuarios.create'));
        $response->assertSessionHasErrors('name');
    }

    public function test_store_requires_apellido(): void
    {
        $admin = $this->admin();

        $response = $this->from(route('usuarios.create'))
            ->actingAs($admin)
            ->post(route('usuarios.store'), [
                'name' => 'Andrés',
                'apellido' => '',
                'email' => 'andres@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'rol' => RoleEnum::PACIENTE->value,
            ]);

        $response->assertRedirect(route('usuarios.create'));
        $response->assertSessionHasErrors('apellido');
    }

    public function test_store_requires_unique_email(): void
    {
        $admin = $this->admin();

        User::factory()->create([
            'email' => 'andres@example.com',
        ]);

        $response = $this->from(route('usuarios.create'))
            ->actingAs($admin)
            ->post(route('usuarios.store'), [
                'name' => 'Andrés',
                'apellido' => 'Schupiak',
                'email' => 'andres@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'rol' => RoleEnum::PACIENTE->value,
            ]);

        $response->assertRedirect(route('usuarios.create'));
        $response->assertSessionHasErrors('email');
    }

    public function test_store_requires_password_confirmation(): void
    {
        $admin = $this->admin();

        $response = $this->from(route('usuarios.create'))
            ->actingAs($admin)
            ->post(route('usuarios.store'), [
                'name' => 'Andrés',
                'apellido' => 'Schupiak',
                'email' => 'andres@example.com',
                'password' => 'password123',
                'password_confirmation' => 'otra-password',
                'rol' => RoleEnum::PACIENTE->value,
            ]);

        $response->assertRedirect(route('usuarios.create'));
        $response->assertSessionHasErrors('password');
    }

    public function test_admin_can_view_edit_form_for_paciente(): void
    {
        $admin = $this->admin();
        $user = $this->paciente();

        $response = $this->actingAs($admin)->get(route('usuarios.edit', $user));

        $response->assertOk();
        $response->assertSee($user->name);
    }

    public function test_admin_cannot_view_edit_form_for_admin(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();

        $response = $this->actingAs($admin)->get(route('usuarios.edit', $otherAdmin));

        $response->assertForbidden();
    }

    public function test_admin_cannot_view_edit_form_for_superadmin(): void
    {
        $admin = $this->admin();
        $superadmin = $this->superadmin();

        $response = $this->actingAs($admin)->get(route('usuarios.edit', $superadmin));

        $response->assertForbidden();
    }

    public function test_superadmin_can_view_edit_form_for_admin(): void
    {
        $superadmin = $this->superadmin();
        $admin = $this->admin();

        $response = $this->actingAs($superadmin)->get(route('usuarios.edit', $admin));

        $response->assertOk();
    }

    public function test_admin_can_update_basic_data_of_paciente(): void
    {
        $admin = $this->admin();
        $user = $this->paciente();

        $response = $this->actingAs($admin)->put(route('usuarios.update', $user), [
            'name' => 'Nombre actualizado',
            'apellido' => 'Apellido actualizado',
            'email' => 'actualizado@example.com',
            'rol' => RoleEnum::PACIENTE->value,
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nombre actualizado',
            'apellido' => 'Apellido actualizado',
            'email' => 'actualizado@example.com',
        ]);
    }

    public function test_admin_can_change_role_from_kinesiologo_to_paciente_and_create_paciente_profile_if_missing(): void
    {
        $admin = $this->admin();
        $user = $this->kinesiologo();

        $this->assertNull($user->paciente);

        $response = $this->actingAs($admin)->put(route('usuarios.update', $user), [
            'name' => $user->name,
            'apellido' => $user->apellido,
            'email' => $user->email,
            'rol' => RoleEnum::PACIENTE->value,
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('success');

        $user->refresh();

        $this->assertTrue($user->hasRole(RoleEnum::PACIENTE->value));
        $this->assertNotNull($user->paciente);
        $this->assertMatchesRegularExpression('/^HC-\d{6}$/', $user->paciente->nro_historia_clinica);
    }

    public function test_superadmin_can_change_role_of_admin_to_paciente(): void
    {
        $superadmin = $this->superadmin();
        $admin = $this->admin();

        $response = $this->actingAs($superadmin)->put(route('usuarios.update', $admin), [
            'name' => $admin->name,
            'apellido' => $admin->apellido,
            'email' => $admin->email,
            'rol' => RoleEnum::PACIENTE->value,
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('success');

        $admin->refresh();

        $this->assertTrue($admin->hasRole(RoleEnum::PACIENTE->value));
    }

    public function test_admin_cannot_update_admin_user(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();

        $response = $this->actingAs($admin)->put(route('usuarios.update', $otherAdmin), [
            'name' => 'Nuevo nombre',
            'apellido' => 'Nuevo apellido',
            'email' => 'nuevo@example.com',
            'rol' => RoleEnum::PACIENTE->value,
        ]);

        $response->assertForbidden();
    }

    public function test_superadmin_cannot_change_role_of_superadmin(): void
    {
        $superadmin = $this->superadmin();

        $response = $this->actingAs($superadmin)->put(route('usuarios.update', $superadmin), [
            'name' => $superadmin->name,
            'apellido' => $superadmin->apellido,
            'email' => $superadmin->email,
            'rol' => RoleEnum::PACIENTE->value,
        ]);

        $response->assertForbidden();
    }

    public function test_update_requires_unique_email_except_current_user(): void
    {
        $admin = $this->admin();
        $user1 = $this->paciente();
        $user2 = $this->kinesiologo();

        $response = $this->from(route('usuarios.edit', $user2))
            ->actingAs($admin)
            ->put(route('usuarios.update', $user2), [
                'name' => $user2->name,
                'apellido' => $user2->apellido,
                'email' => $user1->email,
                'rol' => RoleEnum::KINESIOLOGO->value,
            ]);

        $response->assertRedirect(route('usuarios.edit', $user2));
        $response->assertSessionHasErrors('email');
    }

    public function test_toggle_activo_inactivates_regular_user(): void
    {
        $admin = $this->admin();
        $user = $this->paciente();

        $response = $this->actingAs($admin)
            ->patch(route('usuarios.toggle-activo', $user));

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'activo' => 0,
        ]);
    }

    public function test_toggle_activo_activates_regular_user(): void
    {
        $admin = $this->admin();

        $user = User::factory()->create([
            'activo' => false,
        ]);
        $user->assignRole(RoleEnum::PACIENTE->value);

        $response = $this->actingAs($admin)
            ->patch(route('usuarios.toggle-activo', $user));

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'activo' => 1,
        ]);
    }

    public function test_admin_cannot_toggle_admin_user(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();

        $response = $this->actingAs($admin)
            ->patch(route('usuarios.toggle-activo', $otherAdmin));

        $response->assertForbidden();
    }

    public function test_admin_cannot_toggle_superadmin_user(): void
    {
        $admin = $this->admin();
        $superadmin = $this->superadmin();

        $response = $this->actingAs($admin)
            ->patch(route('usuarios.toggle-activo', $superadmin));

        $response->assertForbidden();
    }

    public function test_cannot_deactivate_only_active_superadmin(): void
    {
        $superadmin = $this->superadmin();

        $response = $this->actingAs($superadmin)
            ->patch(route('usuarios.toggle-activo', $superadmin));

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('warning', 'No se puede desactivar al único superadmin activo.');

        $this->assertDatabaseHas('users', [
            'id' => $superadmin->id,
            'activo' => 1,
        ]);
    }

    public function test_superadmin_can_deactivate_one_superadmin_if_there_is_another_active_superadmin(): void
    {
        $actingSuperadmin = $this->superadmin();
        $otherSuperadmin = $this->superadmin();

        $response = $this->actingAs($actingSuperadmin)
            ->patch(route('usuarios.toggle-activo', $otherSuperadmin));

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $otherSuperadmin->id,
            'activo' => 0,
        ]);
    }
}
