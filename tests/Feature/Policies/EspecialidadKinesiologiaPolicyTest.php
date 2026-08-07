<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleEnum;
use App\Models\EspecialidadKinesiologia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EspecialidadKinesiologiaPolicyTest extends TestCase
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

    public function test_admin_can_manage_especialidades(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMINISTRADOR);
        $especialidad = $this->especialidad();

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'viewAny',
                EspecialidadKinesiologia::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'create',
                EspecialidadKinesiologia::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows('update', $especialidad)
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows('toggleActivo', $especialidad)
        );
    }

    public function test_superadmin_can_manage_especialidades(): void
    {
        $superadmin = $this->userWithRole(RoleEnum::SUPERADMIN);
        $especialidad = $this->especialidad();

        $this->assertTrue(
            Gate::forUser($superadmin)->allows(
                'viewAny',
                EspecialidadKinesiologia::class
            )
        );

        $this->assertTrue(
            Gate::forUser($superadmin)->allows(
                'create',
                EspecialidadKinesiologia::class
            )
        );

        $this->assertTrue(
            Gate::forUser($superadmin)->allows('update', $especialidad)
        );

        $this->assertTrue(
            Gate::forUser($superadmin)->allows(
                'toggleActivo',
                $especialidad
            )
        );
    }

    public function test_paciente_cannot_manage_especialidades(): void
    {
        $paciente = $this->userWithRole(RoleEnum::PACIENTE);
        $especialidad = $this->especialidad();

        $this->assertFalse(
            Gate::forUser($paciente)->allows(
                'viewAny',
                EspecialidadKinesiologia::class
            )
        );

        $this->assertFalse(
            Gate::forUser($paciente)->allows(
                'create',
                EspecialidadKinesiologia::class
            )
        );

        $this->assertFalse(
            Gate::forUser($paciente)->allows('update', $especialidad)
        );

        $this->assertFalse(
            Gate::forUser($paciente)->allows(
                'toggleActivo',
                $especialidad
            )
        );
    }

    public function test_kinesiologo_cannot_manage_especialidades(): void
    {
        $kinesiologo = $this->userWithRole(RoleEnum::KINESIOLOGO);
        $especialidad = $this->especialidad();

        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows(
                'viewAny',
                EspecialidadKinesiologia::class
            )
        );

        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows(
                'create',
                EspecialidadKinesiologia::class
            )
        );

        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('update', $especialidad)
        );

        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows(
                'toggleActivo',
                $especialidad
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

    private function especialidad(): EspecialidadKinesiologia
    {
        return EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);
    }
}
