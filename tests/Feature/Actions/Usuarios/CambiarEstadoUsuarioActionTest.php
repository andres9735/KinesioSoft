<?php

namespace Tests\Feature\Actions\Usuarios;

use App\Actions\Usuarios\CambiarEstadoUsuarioAction;
use App\Enums\RoleEnum;
use App\Exceptions\Usuarios\NoSePuedeDesactivarUnicoSuperadminException;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CambiarEstadoUsuarioActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findOrCreate(RoleEnum::SUPERADMIN->value, 'web');
        Role::findOrCreate(RoleEnum::ADMINISTRADOR->value, 'web');
        Role::findOrCreate(RoleEnum::PACIENTE->value, 'web');
    }

    public function test_inactivates_regular_user_clears_remember_token_and_audits_change(): void
    {
        $user = $this->paciente([
            'activo' => true,
            'remember_token' => 'remember-token-prueba',
        ]);

        $actualizado = app(CambiarEstadoUsuarioAction::class)
            ->ejecutar($user);

        $actualizado->refresh();

        $this->assertFalse($actualizado->activo);
        $this->assertNull($actualizado->remember_token);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'activo' => false,
            'remember_token' => null,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame('usuario_inactivado', $auditoria->evento);
        $this->assertSame('Usuarios', $auditoria->modulo);
        $this->assertSame($user->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($user->id, $auditoria->auditable_id);
        $this->assertNull($auditoria->actor_user_id);

        $this->assertSame(
            ['activo' => true],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            ['activo' => false],
            $auditoria->valores_nuevos
        );
    }

    public function test_activates_inactive_regular_user_and_audits_change(): void
    {
        $user = $this->paciente([
            'activo' => false,
        ]);

        $actualizado = app(CambiarEstadoUsuarioAction::class)
            ->ejecutar($user);

        $actualizado->refresh();

        $this->assertTrue($actualizado->activo);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame('usuario_activado', $auditoria->evento);

        $this->assertSame(
            ['activo' => false],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            ['activo' => true],
            $auditoria->valores_nuevos
        );
    }

    public function test_prevents_deactivating_only_active_superadmin_without_auditing(): void
    {
        $superadmin = $this->superadmin();

        try {
            app(CambiarEstadoUsuarioAction::class)
                ->ejecutar($superadmin);

            $this->fail(
                'Se esperaba impedir la desactivación del único superadmin activo.'
            );
        } catch (NoSePuedeDesactivarUnicoSuperadminException $exception) {
            $this->assertSame(
                'No se puede desactivar al único superadmin activo.',
                $exception->getMessage()
            );
        }

        $superadmin->refresh();

        $this->assertTrue($superadmin->activo);

        $this->assertDatabaseHas('users', [
            'id' => $superadmin->id,
            'activo' => true,
        ]);

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_allows_deactivating_superadmin_when_another_is_active_and_audits_change(): void
    {
        $superadminObjetivo = $this->superadmin();
        $otroSuperadmin = $this->superadmin();

        $actualizado = app(CambiarEstadoUsuarioAction::class)
            ->ejecutar($superadminObjetivo);

        $actualizado->refresh();
        $otroSuperadmin->refresh();

        $this->assertFalse($actualizado->activo);
        $this->assertTrue($otroSuperadmin->activo);

        $this->assertDatabaseHas('users', [
            'id' => $superadminObjetivo->id,
            'activo' => false,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $otroSuperadmin->id,
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame('usuario_inactivado', $auditoria->evento);
        $this->assertSame(
            $superadminObjetivo->id,
            $auditoria->auditable_id
        );
    }

    public function test_uses_authenticated_administrator_as_audit_actor(): void
    {
        $actor = User::factory()->create([
            'name' => 'Admin',
            'apellido' => 'Estados',
            'email' => 'admin.estados@example.com',
        ]);

        $actor->assignRole(RoleEnum::ADMINISTRADOR->value);

        $this->actingAs($actor);

        $user = $this->paciente([
            'activo' => true,
        ]);

        app(CambiarEstadoUsuarioAction::class)
            ->ejecutar($user);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertSame('Admin Estados', $auditoria->actor_nombre);
        $this->assertSame(
            'admin.estados@example.com',
            $auditoria->actor_email
        );

        $this->assertSame($user->id, $auditoria->auditable_id);
    }

    public function test_rolls_back_state_change_and_audit_when_session_revocation_fails(): void
    {
        config([
            'session.driver' => 'database',
            'session.table' => 'sessions_inexistentes',
        ]);

        $user = $this->paciente([
            'activo' => true,
            'remember_token' => 'token-que-debe-conservarse',
        ]);

        try {
            app(CambiarEstadoUsuarioAction::class)
                ->ejecutar($user);

            $this->fail(
                'Se esperaba una QueryException al revocar sesiones.'
            );
        } catch (QueryException) {
            $user->refresh();

            $this->assertTrue($user->activo);

            $this->assertSame(
                'token-que-debe-conservarse',
                $user->remember_token
            );

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'activo' => true,
                'remember_token' => 'token-que-debe-conservarse',
            ]);

            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function paciente(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        $user->assignRole(RoleEnum::PACIENTE->value);

        return $user;
    }

    private function superadmin(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'activo' => true,
        ], $attributes));

        $user->assignRole(RoleEnum::SUPERADMIN->value);

        return $user;
    }
}
