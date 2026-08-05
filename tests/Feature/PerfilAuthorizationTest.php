<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PerfilAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    private function usuarioConRol(RoleEnum $role): User
    {
        $user = User::factory()->create([
            'activo' => true,
        ]);

        $user->assignRole($role->value);

        return $user;
    }

    private function assertProfileRoutesAreForbidden(
        User $user,
        string $getRoute,
        string $postRoute
    ): void {
        $this->actingAs($user)
            ->get(route($getRoute))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route($postRoute), [])
            ->assertForbidden();
    }

    public function test_paciente_cannot_access_kinesiologist_profile_routes(): void
    {
        $user = $this->usuarioConRol(RoleEnum::PACIENTE);

        $this->assertProfileRoutesAreForbidden(
            $user,
            'perfil.kinesiologa.completar',
            'perfil.kinesiologa.actualizar'
        );
    }

    public function test_kinesiologist_cannot_access_patient_profile_routes(): void
    {
        $user = $this->usuarioConRol(RoleEnum::KINESIOLOGO);

        $this->assertProfileRoutesAreForbidden(
            $user,
            'perfil.completar',
            'perfil.actualizar'
        );
    }

    public function test_administrator_cannot_access_patient_profile_routes(): void
    {
        $user = $this->usuarioConRol(RoleEnum::ADMINISTRADOR);

        $this->assertProfileRoutesAreForbidden(
            $user,
            'perfil.completar',
            'perfil.actualizar'
        );
    }

    public function test_administrator_cannot_access_kinesiologist_profile_routes(): void
    {
        $user = $this->usuarioConRol(RoleEnum::ADMINISTRADOR);

        $this->assertProfileRoutesAreForbidden(
            $user,
            'perfil.kinesiologa.completar',
            'perfil.kinesiologa.actualizar'
        );
    }

    public function test_superadmin_cannot_access_patient_profile_routes(): void
    {
        $user = $this->usuarioConRol(RoleEnum::SUPERADMIN);

        $this->assertProfileRoutesAreForbidden(
            $user,
            'perfil.completar',
            'perfil.actualizar'
        );
    }

    public function test_superadmin_cannot_access_kinesiologist_profile_routes(): void
    {
        $user = $this->usuarioConRol(RoleEnum::SUPERADMIN);

        $this->assertProfileRoutesAreForbidden(
            $user,
            'perfil.kinesiologa.completar',
            'perfil.kinesiologa.actualizar'
        );
    }
}
