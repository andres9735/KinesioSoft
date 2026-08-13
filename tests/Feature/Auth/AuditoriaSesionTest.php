<?php

namespace Tests\Feature\Auth;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditoriaSesionTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_audited(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Paciente',
            'email' => 'ana@example.com',
            'password' => bcrypt('password'),
            'activo' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'ana@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame('inicio_sesion', $auditoria->evento);
        $this->assertSame('Sistema / Acceso', $auditoria->modulo);
        $this->assertSame($user->id, $auditoria->actor_user_id);
        $this->assertSame('Ana Paciente', $auditoria->actor_nombre);
        $this->assertSame('ana@example.com', $auditoria->actor_email);
        $this->assertSame($user->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($user->id, $auditoria->auditable_id);
        $this->assertNull($auditoria->valores_anteriores);
        $this->assertNull($auditoria->valores_nuevos);
    }

    public function test_failed_login_is_not_audited(): void
    {
        User::factory()->create([
            'email' => 'ana@example.com',
            'password' => bcrypt('password'),
            'activo' => true,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'ana@example.com',
            'password' => 'incorrecta',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_inactive_user_login_is_not_audited(): void
    {
        User::factory()->create([
            'email' => 'inactivo@example.com',
            'password' => bcrypt('password'),
            'activo' => false,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'inactivo@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_logout_is_audited_before_session_is_destroyed(): void
    {
        $user = User::factory()->create([
            'name' => 'Carlos',
            'apellido' => 'Paciente',
            'email' => 'carlos@example.com',
            'activo' => true,
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();

        $auditoria = Auditoria::query()->sole();

        $this->assertSame('cierre_sesion', $auditoria->evento);
        $this->assertSame('Sistema / Acceso', $auditoria->modulo);
        $this->assertSame($user->id, $auditoria->actor_user_id);
        $this->assertSame('Carlos Paciente', $auditoria->actor_nombre);
        $this->assertSame('carlos@example.com', $auditoria->actor_email);
        $this->assertSame($user->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($user->id, $auditoria->auditable_id);
        $this->assertNull($auditoria->valores_anteriores);
        $this->assertNull($auditoria->valores_nuevos);
    }
}
