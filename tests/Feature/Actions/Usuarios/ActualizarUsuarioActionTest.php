<?php

namespace Tests\Feature\Actions\Usuarios;

use App\Actions\Usuarios\ActualizarUsuarioAction;
use App\Enums\RoleEnum;
use App\Models\Auditoria;
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

    public function test_updates_basic_data_without_changing_role_and_audits_changes(): void
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

        $auditoria = Auditoria::query()->sole();

        $this->assertSame('usuario_actualizado', $auditoria->evento);
        $this->assertSame('Usuarios', $auditoria->modulo);
        $this->assertSame($user->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($user->id, $auditoria->auditable_id);

        $this->assertSame(
            [
                'name' => 'Nombre',
                'apellido' => 'Anterior',
                'email' => 'anterior.action@example.com',
            ],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            [
                'name' => 'Nombre actualizado',
                'apellido' => 'Apellido actualizado',
                'email' => 'actualizado.action@example.com',
            ],
            $auditoria->valores_nuevos
        );
    }

    public function test_audits_only_fields_that_really_changed(): void
    {
        $user = User::factory()->create([
            'name' => 'Nombre',
            'apellido' => 'Sin cambios',
            'email' => 'anterior.email@example.com',
        ]);

        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        app(ActualizarUsuarioAction::class)->ejecutar(
            $user,
            [
                'name' => 'Nombre',
                'apellido' => 'Sin cambios',
                'email' => 'nuevo.email@example.com',
            ],
            RoleEnum::KINESIOLOGO
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            ['email' => 'anterior.email@example.com'],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            ['email' => 'nuevo.email@example.com'],
            $auditoria->valores_nuevos
        );
    }

    public function test_changes_role_to_paciente_creates_profile_and_audits_role_change(): void
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

        $this->assertDatabaseHas('pacientes', [
            'user_id' => $user->id,
            'direccion' => null,
            'contacto_emergencia_nombre' => null,
            'contacto_emergencia_telefono' => null,
            'activo' => true,
        ]);

        $this->assertDatabaseCount('historias_clinicas', 0);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame('rol_cambiado', $auditoria->evento);
        $this->assertSame('Usuarios', $auditoria->modulo);
        $this->assertSame($user->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($user->id, $auditoria->auditable_id);

        $this->assertSame(
            ['rol' => RoleEnum::KINESIOLOGO->value],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            ['rol' => RoleEnum::PACIENTE->value],
            $auditoria->valores_nuevos
        );
    }

    public function test_audits_data_and_role_as_separate_semantic_events(): void
    {
        $user = User::factory()->create([
            'name' => 'Nombre anterior',
            'apellido' => 'Apellido',
            'email' => 'datos.y.rol@example.com',
        ]);

        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        app(ActualizarUsuarioAction::class)->ejecutar(
            $user,
            [
                'name' => 'Nombre nuevo',
                'apellido' => 'Apellido',
                'email' => 'datos.y.rol@example.com',
            ],
            RoleEnum::PACIENTE
        );

        $auditorias = Auditoria::query()
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $auditorias);

        $this->assertSame(
            'usuario_actualizado',
            $auditorias[0]->evento
        );

        $this->assertSame(
            ['name' => 'Nombre anterior'],
            $auditorias[0]->valores_anteriores
        );

        $this->assertSame(
            ['name' => 'Nombre nuevo'],
            $auditorias[0]->valores_nuevos
        );

        $this->assertSame(
            'rol_cambiado',
            $auditorias[1]->evento
        );

        $this->assertSame(
            ['rol' => RoleEnum::KINESIOLOGO->value],
            $auditorias[1]->valores_anteriores
        );

        $this->assertSame(
            ['rol' => RoleEnum::PACIENTE->value],
            $auditorias[1]->valores_nuevos
        );
    }

    public function test_does_not_audit_when_data_and_role_are_unchanged(): void
    {
        $user = User::factory()->create([
            'name' => 'Sin',
            'apellido' => 'Cambios',
            'email' => 'sin.cambios@example.com',
        ]);

        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        app(ActualizarUsuarioAction::class)->ejecutar(
            $user,
            [
                'name' => 'Sin',
                'apellido' => 'Cambios',
                'email' => 'sin.cambios@example.com',
            ],
            RoleEnum::KINESIOLOGO
        );

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_uses_authenticated_user_as_actor_for_update_audit(): void
    {
        $actor = User::factory()->create([
            'name' => 'Admin',
            'apellido' => 'Actualizador',
            'email' => 'admin.actualizador@example.com',
        ]);

        $actor->assignRole(RoleEnum::ADMINISTRADOR->value);

        $this->actingAs($actor);

        $user = User::factory()->create([
            'name' => 'Usuario',
            'apellido' => 'Objetivo',
            'email' => 'usuario.objetivo@example.com',
        ]);

        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        app(ActualizarUsuarioAction::class)->ejecutar(
            $user,
            [
                'name' => 'Usuario actualizado',
                'apellido' => 'Objetivo',
                'email' => 'usuario.objetivo@example.com',
            ],
            RoleEnum::KINESIOLOGO
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertSame('Admin Actualizador', $auditoria->actor_nombre);
        $this->assertSame(
            'admin.actualizador@example.com',
            $auditoria->actor_email
        );

        $this->assertSame($user->id, $auditoria->auditable_id);
    }

    public function test_rolls_back_update_and_audit_when_role_change_fails(): void
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

            $this->assertDatabaseCount('auditorias', 0);
        }
    }
}
