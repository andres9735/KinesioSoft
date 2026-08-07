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
}
