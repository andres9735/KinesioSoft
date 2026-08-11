<?php

namespace Tests\Feature\Services;

use App\Models\Auditoria;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditoriaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_audit_with_actor_snapshot_target_and_context(): void
    {
        $actor = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Gómez',
            'email' => 'ana@example.com',
        ]);

        $objetivo = User::factory()->create();

        $auditoria = app(AuditoriaService::class)->registrar(
            evento: 'usuario_actualizado',
            modulo: 'Usuarios',
            auditable: $objetivo,
            valoresAnteriores: [
                'name' => 'Nombre anterior',
            ],
            valoresNuevos: [
                'name' => 'Nombre nuevo',
            ],
            metadata: [
                'origen' => 'administracion_usuarios',
            ],
            actor: $actor,
            ipAddress: '192.0.2.10',
            userAgent: 'PHPUnit'
        );

        $this->assertDatabaseHas('auditorias', [
            'id' => $auditoria->id,
            'actor_user_id' => $actor->id,
            'actor_nombre' => 'Ana Gómez',
            'actor_email' => 'ana@example.com',
            'evento' => 'usuario_actualizado',
            'modulo' => 'Usuarios',
            'auditable_type' => $objetivo->getMorphClass(),
            'auditable_id' => $objetivo->id,
            'ip_address' => '192.0.2.10',
            'user_agent' => 'PHPUnit',
        ]);

        $auditoria->refresh();

        $this->assertSame(
            ['name' => 'Nombre anterior'],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            ['name' => 'Nombre nuevo'],
            $auditoria->valores_nuevos
        );

        $this->assertSame(
            ['origen' => 'administracion_usuarios'],
            $auditoria->metadata
        );

        $this->assertNotNull($auditoria->created_at);
        $this->assertArrayNotHasKey(
            'updated_at',
            $auditoria->getAttributes()
        );
    }

    public function test_uses_authenticated_user_as_actor_when_actor_is_not_provided(): void
    {
        $actor = User::factory()->create([
            'name' => 'Carlos',
            'apellido' => 'Pérez',
            'email' => 'carlos@example.com',
        ]);

        $this->actingAs($actor);

        $auditoria = app(AuditoriaService::class)->registrar(
            evento: 'inicio_sesion',
            modulo: 'Sistema / Acceso',
            ipAddress: '198.51.100.20',
            userAgent: 'PHPUnit'
        );

        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertSame('Carlos Pérez', $auditoria->actor_nombre);
        $this->assertSame('carlos@example.com', $auditoria->actor_email);
        $this->assertNull($auditoria->auditable_type);
        $this->assertNull($auditoria->auditable_id);
    }

    public function test_supports_event_without_actor_or_auditable_record(): void
    {
        $auditoria = app(AuditoriaService::class)->registrar(
            evento: 'evento_sistema',
            modulo: 'Sistema',
            ipAddress: '203.0.113.30',
            userAgent: 'PHPUnit'
        );

        $this->assertNull($auditoria->actor_user_id);
        $this->assertNull($auditoria->actor_nombre);
        $this->assertNull($auditoria->actor_email);
        $this->assertNull($auditoria->auditable_type);
        $this->assertNull($auditoria->auditable_id);
    }

    public function test_removes_sensitive_fields_recursively_before_persisting(): void
    {
        $auditoria = app(AuditoriaService::class)->registrar(
            evento: 'password_cambiado',
            modulo: 'Sistema / Acceso',
            valoresAnteriores: [
                'name' => 'Andrés',
                'password' => 'hash-anterior',
                'remember_token' => 'remember-anterior',
            ],
            valoresNuevos: [
                'name' => 'Andrés',
                'password' => 'hash-nuevo',
                'password_confirmation' => 'secreto',
                'datos' => [
                    'email' => 'andres@example.com',
                    'token' => 'token-secreto',
                ],
            ],
            metadata: [
                'current_password' => 'password-actual',
                'session_id' => 'session-secreta',
                'contexto' => [
                    'resultado' => 'correcto',
                    'two_factor_secret' => '2fa-secreto',
                ],
            ],
            ipAddress: '192.0.2.40',
            userAgent: 'PHPUnit'
        );

        $auditoria->refresh();

        $this->assertSame(
            ['name' => 'Andrés'],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            [
                'name' => 'Andrés',
                'datos' => [
                    'email' => 'andres@example.com',
                ],
            ],
            $auditoria->valores_nuevos
        );

        $this->assertSame(
            [
                'contexto' => [
                    'resultado' => 'correcto',
                ],
            ],
            $auditoria->metadata
        );
    }

    public function test_audit_record_cannot_be_updated_through_model(): void
    {
        $auditoria = $this->auditoria();

        try {
            $auditoria->update([
                'evento' => 'evento_modificado',
            ]);

            $this->fail(
                'Se esperaba impedir la modificación del registro de auditoría.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Los registros de auditoría son inmutables y no pueden modificarse.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('auditorias', [
            'id' => $auditoria->id,
            'evento' => 'evento_original',
        ]);
    }

    public function test_audit_record_cannot_be_deleted_through_model(): void
    {
        $auditoria = $this->auditoria();

        try {
            $auditoria->delete();

            $this->fail(
                'Se esperaba impedir la eliminación del registro de auditoría.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Los registros de auditoría son inmutables y no pueden eliminarse.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('auditorias', [
            'id' => $auditoria->id,
            'evento' => 'evento_original',
        ]);
    }

    private function auditoria(): Auditoria
    {
        return app(AuditoriaService::class)->registrar(
            evento: 'evento_original',
            modulo: 'Pruebas',
            ipAddress: '192.0.2.50',
            userAgent: 'PHPUnit'
        );
    }
}
