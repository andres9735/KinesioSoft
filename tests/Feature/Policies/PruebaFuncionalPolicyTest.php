<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleEnum;
use App\Models\PruebaFuncional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PruebaFuncionalPolicyTest extends TestCase
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

    public function test_admin_can_use_all_six_abilities(): void
    {
        $this->assertAbilities(RoleEnum::ADMINISTRADOR, true);
    }

    public function test_superadmin_can_use_all_six_abilities(): void
    {
        $this->assertAbilities(RoleEnum::SUPERADMIN, true);
    }

    public function test_kinesiologo_cannot_use_any_of_six_abilities(): void
    {
        $this->assertAbilities(RoleEnum::KINESIOLOGO, false);
    }

    public function test_paciente_cannot_use_any_of_six_abilities(): void
    {
        $this->assertAbilities(RoleEnum::PACIENTE, false);
    }

    private function assertAbilities(RoleEnum $role, bool $permitido): void
    {
        $user = User::factory()->create(['activo' => true]);
        $user->assignRole($role->value);

        $prueba = PruebaFuncional::create([
            'sistema_origen' => 'INTERNO', 'codigo' => 'TEST-001',
            'nombre' => 'Test funcional', 'activo' => true,
        ]);

        foreach (['viewAny', 'create'] as $ability) {
            $this->assertSame($permitido, Gate::forUser($user)->allows($ability, PruebaFuncional::class));
        }

        foreach (['view', 'update', 'inactivar', 'reactivar'] as $ability) {
            $this->assertSame($permitido, Gate::forUser($user)->allows($ability, $prueba));
        }
    }
}
