<?php

namespace Tests\Feature\Actions\Usuarios;

use App\Actions\Usuarios\ActualizarUsuarioAction;
use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ActualizarUsuarioActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findOrCreate(RoleEnum::ADMINISTRADOR->value, 'web');
        Role::findOrCreate(RoleEnum::KINESIOLOGO->value, 'web');
        Role::findOrCreate(RoleEnum::PACIENTE->value, 'web');
    }

    public function test_updates_basic_data_without_changing_role(): void
    {
        $user = User::factory()->create([
            'name' => 'Nombre',
            'apellido' => 'Anterior',
            'email' => 'anterior.action@example.com',
        ]);

        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        app(ActualizarUsuarioAction::class)->ejecutar(
            $user,
            [
                'name' => 'Nombre actualizado',
                'apellido' => 'Apellido actualizado',
                'email' => 'actualizado.action@example.com',
            ],
            RoleEnum::KINESIOLOGO
        );

        $user->refresh();

        $this->assertSame('Nombre actualizado', $user->name);
        $this->assertSame('Apellido actualizado', $user->apellido);
        $this->assertSame('actualizado.action@example.com', $user->email);
        $this->assertTrue($user->hasRole(RoleEnum::KINESIOLOGO->value));
        $this->assertNull($user->paciente);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nombre actualizado',
            'apellido' => 'Apellido actualizado',
            'email' => 'actualizado.action@example.com',
        ]);
    }

    public function test_changes_role_to_paciente_and_creates_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'cambio.rol.action@example.com',
        ]);

        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        app(ActualizarUsuarioAction::class)->ejecutar(
            $user,
            [
                'name' => $user->name,
                'apellido' => $user->apellido,
                'email' => $user->email,
            ],
            RoleEnum::PACIENTE
        );

        $user->refresh()->load('paciente', 'roles');

        $this->assertTrue($user->hasRole(RoleEnum::PACIENTE->value));
        $this->assertFalse($user->hasRole(RoleEnum::KINESIOLOGO->value));
        $this->assertNotNull($user->paciente);
        $this->assertSame($user->id, $user->paciente->user_id);
        $this->assertMatchesRegularExpression(
            '/^HC-\d{6}$/',
            $user->paciente->nro_historia_clinica
        );

        $this->assertDatabaseHas('pacientes', [
            'user_id' => $user->id,
            'nro_historia_clinica' =>
            $user->paciente->nro_historia_clinica,
        ]);
    }

    public function test_rolls_back_update_when_role_change_fails(): void
    {
        $user = User::factory()->create([
            'name' => 'Nombre original',
            'apellido' => 'Apellido original',
            'email' => 'rollback.update.action@example.com',
        ]);

        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        Role::query()
            ->where('name', RoleEnum::PACIENTE->value)
            ->where('guard_name', 'web')
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->expectException(RoleDoesNotExist::class);

        try {
            app(ActualizarUsuarioAction::class)->ejecutar(
                $user,
                [
                    'name' => 'Nombre modificado',
                    'apellido' => 'Apellido modificado',
                    'email' => 'modificado.update.action@example.com',
                ],
                RoleEnum::PACIENTE
            );
        } finally {
            $user->refresh();

            $this->assertSame('Nombre original', $user->name);
            $this->assertSame('Apellido original', $user->apellido);
            $this->assertSame(
                'rollback.update.action@example.com',
                $user->email
            );
            $this->assertTrue(
                $user->hasRole(RoleEnum::KINESIOLOGO->value)
            );
            $this->assertDatabaseMissing('pacientes', [
                'user_id' => $user->id,
            ]);
        }
    }
}
