<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleEnum;
use App\Models\Auditoria;
use App\Models\User;
use App\Policies\AuditoriaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            Role::findOrCreate(
                $role->value,
                'web'
            );
        }
    }

    public function test_admin_can_consult_auditoria(): void
    {
        $user = $this->userWithRole(
            RoleEnum::ADMINISTRADOR
        );

        $policy = app(
            AuditoriaPolicy::class
        );

        $auditoria = new Auditoria();

        $this->assertTrue(
            $policy->viewAny($user)
        );

        $this->assertTrue(
            $policy->view(
                $user,
                $auditoria
            )
        );

        $this->assertTrue(
            $policy->export($user)
        );
    }

    public function test_superadmin_can_consult_auditoria(): void
    {
        $user = $this->userWithRole(
            RoleEnum::SUPERADMIN
        );

        $policy = app(
            AuditoriaPolicy::class
        );

        $auditoria = new Auditoria();

        $this->assertTrue(
            $policy->viewAny($user)
        );

        $this->assertTrue(
            $policy->view(
                $user,
                $auditoria
            )
        );

        $this->assertTrue(
            $policy->export($user)
        );
    }

    public function test_paciente_cannot_consult_auditoria(): void
    {
        $user = $this->userWithRole(
            RoleEnum::PACIENTE
        );

        $policy = app(
            AuditoriaPolicy::class
        );

        $auditoria = new Auditoria();

        $this->assertFalse(
            $policy->viewAny($user)
        );

        $this->assertFalse(
            $policy->view(
                $user,
                $auditoria
            )
        );

        $this->assertFalse(
            $policy->export($user)
        );
    }

    public function test_kinesiologo_cannot_consult_auditoria(): void
    {
        $user = $this->userWithRole(
            RoleEnum::KINESIOLOGO
        );

        $policy = app(
            AuditoriaPolicy::class
        );

        $auditoria = new Auditoria();

        $this->assertFalse(
            $policy->viewAny($user)
        );

        $this->assertFalse(
            $policy->view(
                $user,
                $auditoria
            )
        );

        $this->assertFalse(
            $policy->export($user)
        );
    }

    private function userWithRole(
        RoleEnum $role
    ): User {
        $user = User::factory()->create([
            'activo' => true,
        ]);

        $user->assignRole(
            $role->value
        );

        return $user;
    }
}
