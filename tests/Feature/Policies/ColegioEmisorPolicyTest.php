<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleEnum;
use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ColegioEmisorPolicyTest extends TestCase
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

    public function test_admin_can_manage_colegios_emisores(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMINISTRADOR);
        $colegio = $this->colegioEmisor();

        $this->assertTrue(
            Gate::forUser($admin)->allows('viewAny', ColegioEmisor::class)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('create', ColegioEmisor::class)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('update', $colegio)
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('inactivar', $colegio)
        );
    }

    public function test_superadmin_can_manage_colegios_emisores(): void
    {
        $superadmin = $this->userWithRole(RoleEnum::SUPERADMIN);
        $colegio = $this->colegioEmisor();

        $this->assertTrue(
            Gate::forUser($superadmin)->allows('viewAny', ColegioEmisor::class)
        );
        $this->assertTrue(
            Gate::forUser($superadmin)->allows('create', ColegioEmisor::class)
        );
        $this->assertTrue(
            Gate::forUser($superadmin)->allows('update', $colegio)
        );
        $this->assertTrue(
            Gate::forUser($superadmin)->allows('inactivar', $colegio)
        );
    }

    public function test_paciente_cannot_manage_colegios_emisores(): void
    {
        $paciente = $this->userWithRole(RoleEnum::PACIENTE);
        $colegio = $this->colegioEmisor();

        $this->assertFalse(
            Gate::forUser($paciente)->allows('viewAny', ColegioEmisor::class)
        );
        $this->assertFalse(
            Gate::forUser($paciente)->allows('create', ColegioEmisor::class)
        );
        $this->assertFalse(
            Gate::forUser($paciente)->allows('update', $colegio)
        );
        $this->assertFalse(
            Gate::forUser($paciente)->allows('inactivar', $colegio)
        );
    }

    public function test_kinesiologo_cannot_manage_colegios_emisores(): void
    {
        $kinesiologo = $this->userWithRole(RoleEnum::KINESIOLOGO);
        $colegio = $this->colegioEmisor();

        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('viewAny', ColegioEmisor::class)
        );
        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('create', ColegioEmisor::class)
        );
        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('update', $colegio)
        );
        $this->assertFalse(
            Gate::forUser($kinesiologo)->allows('inactivar', $colegio)
        );
    }

    public function test_view_and_reactivar_follow_catalogo_admin_roles(): void
    {
        $colegio = $this->colegioEmisor();

        foreach (RoleEnum::cases() as $role) {
            $user = $this->userWithRole($role);
            $permitido = in_array($role, [
                RoleEnum::ADMINISTRADOR,
                RoleEnum::SUPERADMIN,
            ], true);

            foreach (['view', 'reactivar'] as $ability) {
                $this->assertSame(
                    $permitido,
                    Gate::forUser($user)->allows($ability, $colegio)
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

    private function colegioEmisor(): ColegioEmisor
    {
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        return ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);
    }
}
