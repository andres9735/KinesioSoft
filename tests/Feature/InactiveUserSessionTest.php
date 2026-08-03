<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InactiveUserSessionTest extends TestCase
{
    use RefreshDatabase;

    private const INACTIVE_MESSAGE =
    'Tu cuenta está inactiva. Contactá al administrador del sistema.';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    private function usuarioConRol(
        RoleEnum $role,
        array $attributes = []
    ): User {
        $user = User::factory()->create(array_merge([
            'activo' => true,
        ], $attributes));

        $user->assignRole($role->value);

        return $user;
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = $this->usuarioConRol(RoleEnum::PACIENTE, [
            'email' => 'inactivo@example.test',
            'password' => Hash::make('password'),
            'activo' => false,
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors([
            'email' => self::INACTIVE_MESSAGE,
        ]);

        $this->assertGuest();
    }

    public function test_authenticated_user_is_logged_out_on_next_request_when_inactivated(): void
    {
        $user = $this->usuarioConRol(RoleEnum::PACIENTE);

        $this
            ->actingAs($user)
            ->withSession([
                'private_marker' => 'informacion-sensible',
            ]);

        $user->update([
            'activo' => false,
        ]);

        $response = $this->get('/');

        $response->assertRedirect(route('login'));

        $response->assertSessionHasErrors([
            'email' => self::INACTIVE_MESSAGE,
        ]);

        $response->assertSessionMissing('private_marker');

        $this->assertGuest();
    }

    public function test_deactivating_user_removes_database_sessions_and_remember_token(): void
    {
        config([
            'session.driver' => 'database',
        ]);

        $admin = $this->usuarioConRol(RoleEnum::ADMINISTRADOR);

        $user = $this->usuarioConRol(RoleEnum::PACIENTE, [
            'remember_token' => 'remember-token-activo',
        ]);

        $sessionTable = config('session.table', 'sessions');

        DB::table($sessionTable)->insert([
            [
                'id' => 'session-target-one',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => '',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'session-target-two',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => '',
                'last_activity' => now()->timestamp,
            ],
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('usuarios.toggle-activo', $user));

        $response->assertRedirect(route('usuarios.index'));

        $user->refresh();

        $this->assertFalse($user->activo);
        $this->assertNull($user->remember_token);

        $this->assertDatabaseMissing($sessionTable, [
            'user_id' => $user->id,
        ]);
    }

    public function test_active_authenticated_user_remains_authenticated(): void
    {
        $user = $this->usuarioConRol(RoleEnum::PACIENTE);

        $response = $this
            ->actingAs($user)
            ->get('/');

        $response->assertOk();

        $this->assertAuthenticatedAs($user);
    }
}
