<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleEnum;
use App\Models\Jurisdiccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class JurisdiccionPolicyTest extends TestCase
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

    public function test_admin_can_manage_jurisdicciones(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMINISTRADOR);
        $jurisdiccion = $this->jurisdiccion();

        $this->assertTrue(
            Gate::forUser($admin)->allows('viewAny', Jurisdiccion::class)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('create', Jurisdiccion::class)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('update', $jurisdiccion)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('toggleActivo', $jurisdiccion)
        );
    }

    public function test_superadmin_can_manage_jurisdicciones(): void
    {
        $superadmin = $this->userWithRole(RoleEnum::SUPERADMIN);
        $jurisdiccion = $this->jurisdiccion();

        $this->assertTrue(
            Gate::forUser($superadmin)->allows('viewAny', Jurisdiccion::class)
        );
        $this->assertTrue(
            Gate::forUser($superadmin)->allows('create', Jurisdiccion::class)
        );
        $this->assertTrue(
            Gate::forUser($superadmin)->allows('update', $jurisdiccion)
        );
        $this->assertTrue(
            Gate::forUser($superadmin)->allows('toggleActivo', $jurisdiccion)
        );
    }

    public function test_paciente_cannot_manage_jurisdicciones(): void
    {
        $paciente = $this->userWithRole(RoleEnum::PACIENTE);
        $jurisdiccion = $this->jurisdiccion();

        $this->assertFalse(
            Gate::forUser($paciente)->allows('viewAny', Jurisdiccion::class)
        );
        $this->assertFalse(
            Gate::forUser($paciente)->allows('create', Jurisdiccion::class)
        );
        $this->assertFalse(
            Gate::forUser($paciente)->allows('update', $jurisdiccion)
        );
        $this->assertFalse(
            Gate::forUser($paciente)->allows('toggleActivo', $jurisdiccion)
        );
    }

    public function test_kinesiologo_cannot_manage_jurisdicciones(): void
    {
        $kinesiologo = $this->userWithRole(RoleEnum::KINESIOLOGO);
        $jurisdiccion = $this->jurisdiccion();

        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('viewAny', Jurisdiccion::class)
        );
        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('create', Jurisdiccion::class)
        );
        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('update', $jurisdiccion)
        );
        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('toggleActivo', $jurisdiccion)
        );
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create([
            'activo' => true,
        ]);

        $user->assignRole($role->value);

        return $user;
    }

    private function jurisdiccion(): Jurisdiccion
    {
        return Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);
    }
}
