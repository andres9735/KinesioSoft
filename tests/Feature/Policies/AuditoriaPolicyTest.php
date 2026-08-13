<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleEnum;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditoriaPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]
            ->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    public function test_admin_can_consult_auditoria(): void
    {
        $admin = $this->userWithRole(
            RoleEnum::ADMINISTRADOR
        );

        $auditoria = new Auditoria();

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'viewAny',
                Auditoria::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'view',
                $auditoria
            )
        );
    }

    public function test_superadmin_can_consult_auditoria(): void
    {
        $superadmin = $this->userWithRole(
            RoleEnum::SUPERADMIN
        );

        $auditoria = new Auditoria();

        $this->assertTrue(
            Gate::forUser($superadmin)->allows(
                'viewAny',
                Auditoria::class
            )
        );

        $this->assertTrue(
            Gate::forUser($superadmin)->allows(
                'view',
                $auditoria
            )
        );
    }

    public function test_paciente_cannot_consult_auditoria(): void
    {
        $paciente = $this->userWithRole(
            RoleEnum::PACIENTE
        );

        $auditoria = new Auditoria();

        $this->assertFalse(
            Gate::forUser($paciente)->allows(
                'viewAny',
                Auditoria::class
            )
        );

        $this->assertFalse(
            Gate::forUser($paciente)->allows(
                'view',
                $auditoria
            )
        );
    }

    public function test_kinesiologo_cannot_consult_auditoria(): void
    {
        $kinesiologo = $this->userWithRole(
            RoleEnum::KINESIOLOGO
        );

        $auditoria = new Auditoria();

        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows(
                'viewAny',
                Auditoria::class
            )
        );

        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows(
                'view',
                $auditoria
            )
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
}
