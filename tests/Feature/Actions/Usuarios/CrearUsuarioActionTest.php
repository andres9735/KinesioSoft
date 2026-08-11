<?php

namespace Tests\Feature\Actions\Usuarios;

use App\Actions\Usuarios\CrearUsuarioAction;
use App\Enums\RoleEnum;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CrearUsuarioActionTest extends TestCase
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

    public function test_creates_paciente_user_with_role_profile_hashed_password_and_audit(): void
    {
        $data = $this->validUserData('paciente.action@example.com');

        $user = app(CrearUsuarioAction::class)->ejecutar(
            $data,
            RoleEnum::PACIENTE
        );

        $user->load('paciente', 'roles');

        $this->assertTrue($user->exists);
        $this->assertTrue($user->activo);
        $this->assertTrue($user->hasRole(RoleEnum::PACIENTE->value));
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertNotNull($user->paciente);
        $this->assertSame($user->id, $user->paciente->user_id);

        $this->assertMatchesRegularExpression(
            '/^HC-\d{6}$/',
            $user->paciente->nro_historia_clinica
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $data['name'],
            'apellido' => $data['apellido'],
            'email' => $data['email'],
            'activo' => true,
        ]);

        $this->assertDatabaseHas('pacientes', [
            'user_id' => $user->id,
            'nro_historia_clinica' =>
            $user->paciente->nro_historia_clinica,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertNull($auditoria->actor_user_id);
        $this->assertSame('usuario_creado', $auditoria->evento);
        $this->assertSame('Usuarios', $auditoria->modulo);
        $this->assertSame($user->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($user->id, $auditoria->auditable_id);
        $this->assertNull($auditoria->valores_anteriores);

        $this->assertSame(
            [
                'name' => $data['name'],
                'apellido' => $data['apellido'],
                'email' => $data['email'],
                'activo' => true,
                'rol' => RoleEnum::PACIENTE->value,
            ],
            $auditoria->valores_nuevos
        );

        $this->assertArrayNotHasKey(
            'password',
            $auditoria->valores_nuevos
        );
    }

    public function test_creates_non_paciente_user_without_paciente_profile(): void
    {
        $data = $this->validUserData('kinesiologo.action@example.com');

        $user = app(CrearUsuarioAction::class)->ejecutar(
            $data,
            RoleEnum::KINESIOLOGO
        );

        $this->assertTrue($user->hasRole(RoleEnum::KINESIOLOGO->value));
        $this->assertNull($user->paciente);

        $this->assertDatabaseMissing('pacientes', [
            'user_id' => $user->id,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame('usuario_creado', $auditoria->evento);

        $this->assertSame(
            RoleEnum::KINESIOLOGO->value,
            $auditoria->valores_nuevos['rol']
        );
    }

    public function test_uses_authenticated_administrator_as_audit_actor(): void
    {
        $actor = User::factory()->create([
            'name' => 'Admin',
            'apellido' => 'Auditoria',
            'email' => 'admin.auditoria@example.com',
        ]);

        $actor->assignRole(RoleEnum::ADMINISTRADOR->value);

        $this->actingAs($actor);

        $data = $this->validUserData('creado.por.admin@example.com');

        $user = app(CrearUsuarioAction::class)->ejecutar(
            $data,
            RoleEnum::PACIENTE
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertSame('Admin Auditoria', $auditoria->actor_nombre);
        $this->assertSame(
            'admin.auditoria@example.com',
            $auditoria->actor_email
        );

        $this->assertSame($user->id, $auditoria->auditable_id);
    }

    public function test_creation_audit_does_not_store_password_or_credentials(): void
    {
        $data = $this->validUserData('sin.credenciales@example.com');

        app(CrearUsuarioAction::class)->ejecutar(
            $data,
            RoleEnum::PACIENTE
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            [
                'name',
                'apellido',
                'email',
                'activo',
                'rol',
            ],
            array_keys($auditoria->valores_nuevos)
        );

        $this->assertStringNotContainsString(
            $data['password'],
            json_encode($auditoria->valores_nuevos)
        );
    }

    public function test_rolls_back_user_creation_when_role_assignment_fails(): void
    {
        Role::query()
            ->where('name', RoleEnum::PACIENTE->value)
            ->where('guard_name', 'web')
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $data = $this->validUserData('rollback.action@example.com');

        $this->expectException(RoleDoesNotExist::class);

        try {
            app(CrearUsuarioAction::class)->ejecutar(
                $data,
                RoleEnum::PACIENTE
            );
        } finally {
            $this->assertDatabaseMissing('users', [
                'email' => $data['email'],
            ]);

            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    /**
     * @return array{
     *     name: string,
     *     apellido: string,
     *     email: string,
     *     password: string
     * }
     */
    private function validUserData(string $email): array
    {
        return [
            'name' => 'Usuario',
            'apellido' => 'Action',
            'email' => $email,
            'password' => 'password123',
        ];
    }
}
