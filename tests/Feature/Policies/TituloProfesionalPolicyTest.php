<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleEnum;
use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TituloProfesionalPolicyTest extends TestCase
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

    public function test_admin_can_manage_titulos_profesionales(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMINISTRADOR);
        $titulo = $this->tituloProfesional();

        $this->assertTrue(
            Gate::forUser($admin)->allows('viewAny', TituloProfesional::class)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('create', TituloProfesional::class)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('update', $titulo)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('inactivar', $titulo)
        );
    }

    public function test_superadmin_can_manage_titulos_profesionales(): void
    {
        $superadmin = $this->userWithRole(RoleEnum::SUPERADMIN);
        $titulo = $this->tituloProfesional();

        $this->assertTrue(
            Gate::forUser($superadmin)->allows('viewAny', TituloProfesional::class)
        );
        $this->assertTrue(
            Gate::forUser($superadmin)->allows('create', TituloProfesional::class)
        );
        $this->assertTrue(
            Gate::forUser($superadmin)->allows('update', $titulo)
        );
        $this->assertTrue(
            Gate::forUser($superadmin)->allows('inactivar', $titulo)
        );
    }

    public function test_paciente_cannot_manage_titulos_profesionales(): void
    {
        $paciente = $this->userWithRole(RoleEnum::PACIENTE);
        $titulo = $this->tituloProfesional();

        $this->assertFalse(
            Gate::forUser($paciente)->allows('viewAny', TituloProfesional::class)
        );
        $this->assertFalse(
            Gate::forUser($paciente)->allows('create', TituloProfesional::class)
        );
        $this->assertFalse(
            Gate::forUser($paciente)->allows('update', $titulo)
        );
        $this->assertFalse(
            Gate::forUser($paciente)->allows('inactivar', $titulo)
        );
    }

    public function test_kinesiologo_cannot_manage_titulos_profesionales(): void
    {
        $kinesiologo = $this->userWithRole(RoleEnum::KINESIOLOGO);
        $titulo = $this->tituloProfesional();

        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('viewAny', TituloProfesional::class)
        );
        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('create', TituloProfesional::class)
        );
        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('update', $titulo)
        );
        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('inactivar', $titulo)
        );
    }

    public function test_view_and_reactivar_follow_catalogo_admin_roles(): void
    {
        $titulo = $this->tituloProfesional();

        foreach (RoleEnum::cases() as $role) {
            $user = $this->userWithRole($role);
            $permitido = in_array($role, [
                RoleEnum::ADMINISTRADOR,
                RoleEnum::SUPERADMIN,
            ], true);

            foreach (['view', 'reactivar'] as $ability) {
                $this->assertSame(
                    $permitido,
                    Gate::forUser($user)->allows($ability, $titulo)
                );
            }
        }
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create([
            'activo' => true,
        ]);

        $user->assignRole($role->value);

        return $user;
    }

    private function tituloProfesional(): TituloProfesional
    {
        return TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);
    }
}
