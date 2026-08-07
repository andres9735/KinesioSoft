<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserPolicyTest extends TestCase
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

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create([
            'activo' => true,
        ]);

        $user->assignRole($role->value);

        return $user;
    }

    public function test_admin_can_view_users_and_create_users(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMINISTRADOR);

        $this->assertTrue(
            Gate::forUser($admin)->allows('viewAny', User::class)
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows('create', User::class)
        );
    }

    public function test_superadmin_can_view_users_and_create_users(): void
    {
        $superadmin = $this->userWithRole(RoleEnum::SUPERADMIN);

        $this->assertTrue(
            Gate::forUser($superadmin)->allows('viewAny', User::class)
        );

        $this->assertTrue(
            Gate::forUser($superadmin)->allows('create', User::class)
        );
    }

    public function test_paciente_cannot_view_users_or_create_users(): void
    {
        $paciente = $this->userWithRole(RoleEnum::PACIENTE);

        $this->assertFalse(
            Gate::forUser($paciente)->allows('viewAny', User::class)
        );

        $this->assertFalse(
            Gate::forUser($paciente)->allows('create', User::class)
        );
    }

    public function test_kinesiologo_cannot_view_users_or_create_users(): void
    {
        $kinesiologo = $this->userWithRole(RoleEnum::KINESIOLOGO);

        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('viewAny', User::class)
        );

        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('create', User::class)
        );
    }

    public function test_admin_can_update_regular_users(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMINISTRADOR);
        $paciente = $this->userWithRole(RoleEnum::PACIENTE);
        $kinesiologo = $this->userWithRole(RoleEnum::KINESIOLOGO);

        $this->assertTrue(
            Gate::forUser($admin)->allows('update', $paciente)
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows('update', $kinesiologo)
        );
    }

    public function test_admin_cannot_update_admin_or_superadmin(): void
    {
        $actor = $this->userWithRole(RoleEnum::ADMINISTRADOR);
        $admin = $this->userWithRole(RoleEnum::ADMINISTRADOR);
        $superadmin = $this->userWithRole(RoleEnum::SUPERADMIN);

        $this->assertFalse(
            Gate::forUser($actor)->allows('update', $admin)
        );

        $this->assertFalse(
            Gate::forUser($actor)->allows('update', $superadmin)
        );
    }

    public function test_superadmin_can_update_users_of_any_role(): void
    {
        $superadmin = $this->userWithRole(RoleEnum::SUPERADMIN);
        $paciente = $this->userWithRole(RoleEnum::PACIENTE);
        $kinesiologo = $this->userWithRole(RoleEnum::KINESIOLOGO);
        $admin = $this->userWithRole(RoleEnum::ADMINISTRADOR);
        $otherSuperadmin = $this->userWithRole(RoleEnum::SUPERADMIN);

        $this->assertTrue(
            Gate::forUser($superadmin)->allows('update', $paciente)
        );

        $this->assertTrue(
            Gate::forUser($superadmin)->allows('update', $kinesiologo)
        );

        $this->assertTrue(
            Gate::forUser($superadmin)->allows('update', $admin)
        );

        $this->assertTrue(
            Gate::forUser($superadmin)->allows('update', $otherSuperadmin)
        );
    }

    public function test_regular_users_cannot_update_other_users(): void
    {
        $paciente = $this->userWithRole(RoleEnum::PACIENTE);
        $kinesiologo = $this->userWithRole(RoleEnum::KINESIOLOGO);
        $target = $this->userWithRole(RoleEnum::PACIENTE);

        $this->assertFalse(
            Gate::forUser($paciente)->allows('update', $target)
        );

        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('update', $target)
        );
    }

    public function test_superadmin_role_change_rules_are_preserved(): void
    {
        $actor = $this->userWithRole(RoleEnum::SUPERADMIN);
        $targetSuperadmin = $this->userWithRole(RoleEnum::SUPERADMIN);
        $targetAdmin = $this->userWithRole(RoleEnum::ADMINISTRADOR);

        $this->assertTrue(
            Gate::forUser($actor)->allows('changeRole', [
                $targetSuperadmin,
                RoleEnum::SUPERADMIN->value,
            ])
        );

        $this->assertFalse(
            Gate::forUser($actor)->allows('changeRole', [
                $targetSuperadmin,
                RoleEnum::PACIENTE->value,
            ])
        );

        $this->assertTrue(
            Gate::forUser($actor)->allows('changeRole', [
                $targetAdmin,
                RoleEnum::PACIENTE->value,
            ])
        );
    }

    public function test_admin_can_toggle_regular_users_but_not_privileged_users(): void
    {
        $actor = $this->userWithRole(RoleEnum::ADMINISTRADOR);
        $paciente = $this->userWithRole(RoleEnum::PACIENTE);
        $admin = $this->userWithRole(RoleEnum::ADMINISTRADOR);
        $superadmin = $this->userWithRole(RoleEnum::SUPERADMIN);

        $this->assertTrue(
            Gate::forUser($actor)->allows('toggleActivo', $paciente)
        );

        $this->assertFalse(
            Gate::forUser($actor)->allows('toggleActivo', $admin)
        );

        $this->assertFalse(
            Gate::forUser($actor)->allows('toggleActivo', $superadmin)
        );
    }

    public function test_superadmin_can_toggle_privileged_users(): void
    {
        $actor = $this->userWithRole(RoleEnum::SUPERADMIN);
        $admin = $this->userWithRole(RoleEnum::ADMINISTRADOR);
        $superadmin = $this->userWithRole(RoleEnum::SUPERADMIN);

        $this->assertTrue(
            Gate::forUser($actor)->allows('toggleActivo', $admin)
        );

        $this->assertTrue(
            Gate::forUser($actor)->allows('toggleActivo', $superadmin)
        );
    }

    public function test_regular_users_cannot_toggle_users(): void
    {
        $paciente = $this->userWithRole(RoleEnum::PACIENTE);
        $kinesiologo = $this->userWithRole(RoleEnum::KINESIOLOGO);
        $target = $this->userWithRole(RoleEnum::PACIENTE);

        $this->assertFalse(
            Gate::forUser($paciente)->allows('toggleActivo', $target)
        );

        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('toggleActivo', $target)
        );
    }
}
