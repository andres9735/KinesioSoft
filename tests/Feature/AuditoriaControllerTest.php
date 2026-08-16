<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Auditoria;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditoriaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]
            ->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate(
                $role->value,
                'web'
            );
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_view_audit_index(): void
    {
        $admin = $this->userWithRole(
            RoleEnum::ADMINISTRADOR
        );

        Auditoria::create([
            'actor_nombre' => 'Admin KinesioSoft',
            'actor_email' => 'admin@example.com',
            'evento' => 'usuario_creado',
            'modulo' => 'Usuarios',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('auditorias.index'));

        $response
            ->assertOk()
            ->assertViewIs('auditorias.index')
            ->assertViewHas('auditorias')
            ->assertViewHas('eventos')
            ->assertViewHas('modulos')
            ->assertSee('Auditoría')
            ->assertSee('Admin KinesioSoft')
            ->assertSee('Usuario creado')
            ->assertSee('Usuarios');
    }

    public function test_superadmin_can_view_audit_index(): void
    {
        $superadmin = $this->userWithRole(
            RoleEnum::SUPERADMIN
        );

        $response = $this
            ->actingAs($superadmin)
            ->get(route('auditorias.index'));

        $response
            ->assertOk()
            ->assertSee('Auditoría')
            ->assertSee('Superadministrador');
    }

    public function test_paciente_cannot_view_audit_index(): void
    {
        $paciente = $this->userWithRole(
            RoleEnum::PACIENTE
        );

        $response = $this
            ->actingAs($paciente)
            ->get(route('auditorias.index'));

        $response->assertRedirect(
            route('perfil.completar')
        );
    }

    public function test_kinesiologo_cannot_view_audit_index(): void
    {
        $kinesiologo = $this->userWithRole(
            RoleEnum::KINESIOLOGO
        );

        $response = $this
            ->actingAs($kinesiologo)
            ->get(route('auditorias.index'));

        $response->assertRedirect(
            route('perfil.kinesiologa.completar')
        );
    }

    public function test_index_applies_audit_filters(): void
    {
        $admin = $this->userWithRole(
            RoleEnum::ADMINISTRADOR
        );

        Carbon::setTestNow(
            Carbon::parse('2026-08-10 10:00:00')
        );

        Auditoria::create([
            'actor_nombre' => 'Ana Administradora',
            'actor_email' => 'ana.admin@example.com',
            'evento' => 'usuario_creado',
            'modulo' => 'Usuarios',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-11 10:00:00')
        );

        Auditoria::create([
            'actor_nombre' => 'Carlos Administrador',
            'actor_email' => 'carlos@example.com',
            'evento' => 'usuario_actualizado',
            'modulo' => 'Usuarios',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-15 10:00:00')
        );

        Auditoria::create([
            'actor_nombre' => 'Ana Administradora',
            'actor_email' => 'ana.admin@example.com',
            'evento' => 'usuario_creado',
            'modulo' => 'Usuarios',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'auditorias.index',
                    [
                        'fecha_desde' => '2026-08-10',
                        'fecha_hasta' => '2026-08-12',
                        'evento' => 'usuario_creado',
                        'modulo' => 'Usuarios',
                        'actor' => 'Ana',
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertSee('Ana Administradora')
            ->assertSee('Usuario creado')
            ->assertDontSee('Carlos Administrador');

        $auditorias = $response
            ->viewData('auditorias');

        $this->assertSame(
            1,
            $auditorias->total()
        );
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $admin = $this->userWithRole(
            RoleEnum::ADMINISTRADOR
        );

        $response = $this
            ->actingAs($admin)
            ->from(route('auditorias.index'))
            ->get(
                route(
                    'auditorias.index',
                    [
                        'fecha_desde' => '2026-08-12',
                        'fecha_hasta' => '2026-08-10',
                    ]
                )
            );

        $response
            ->assertRedirect(
                route('auditorias.index')
            )
            ->assertSessionHasErrors(
                'fecha_hasta'
            );
    }

    public function test_index_shows_empty_state_when_filters_have_no_matches(): void
    {
        $admin = $this->userWithRole(
            RoleEnum::ADMINISTRADOR
        );

        Auditoria::create([
            'evento' => 'usuario_creado',
            'modulo' => 'Usuarios',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'auditorias.index',
                    [
                        'modulo' => 'Modulo inexistente',
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertSee(
                'No se encontraron registros'
            )
            ->assertSee(
                'Pruebe con otros filtros o limpie la búsqueda.'
            );
    }

    public function test_index_renders_trace_detail_with_comparable_values(): void
    {
        $admin = $this->userWithRole(
            RoleEnum::ADMINISTRADOR
        );

        $usuarioAfectado = User::factory()->create([
            'name' => 'Andrés',
            'email' => 'andres@example.com',
        ]);

        Auditoria::create([
            'actor_user_id' => $admin->id,
            'actor_nombre' => 'Admin KinesioSoft',
            'actor_email' => 'admin@example.com',
            'evento' => 'usuario_actualizado',
            'modulo' => 'Usuarios',
            'auditable_type' => $usuarioAfectado->getMorphClass(),
            'auditable_id' => $usuarioAfectado->id,
            'valores_anteriores' => [
                'name' => 'Andrés',
                'email' => 'andres@example.com',
            ],
            'valores_nuevos' => [
                'name' => 'Andrés Martín',
                'email' => 'andres.nuevo@example.com',
            ],
            'ip_address' => '192.168.1.100',
            'user_agent' => 'PHPUnit Browser',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('auditorias.index'));

        $response
            ->assertOk()
            ->assertSee('Detalle de trazabilidad')
            ->assertSee('Cambios registrados')
            ->assertSee('Valor anterior')
            ->assertSee('Valor nuevo')
            ->assertSee('Andrés Martín')
            ->assertSee('andres.nuevo@example.com')
            ->assertSee('192.168.1.100')
            ->assertSee('Usuario #' . $usuarioAfectado->id)
            ->assertSee('PHPUnit Browser');
    }

    public function test_index_renders_context_when_audit_has_no_comparable_values(): void
    {
        $admin = $this->userWithRole(
            RoleEnum::ADMINISTRADOR
        );

        Auditoria::create([
            'actor_user_id' => $admin->id,
            'actor_nombre' => 'Admin KinesioSoft',
            'actor_email' => 'admin@example.com',
            'evento' => 'inicio_sesion',
            'modulo' => 'Sistema / Acceso',
            'metadata' => [
                'resultado' => 'correcto',
            ],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('auditorias.index'));

        $response
            ->assertOk()
            ->assertSee(
                'Este evento no incluye valores anteriores y posteriores'
            )
            ->assertSee(
                'comparables. Se muestra la información contextual disponible.'
            )
            ->assertSee('Información contextual')
            ->assertSee('Resultado')
            ->assertSee('correcto')
            ->assertSee('127.0.0.1');
    }

    public function test_admin_can_export_audit_pdf(): void
    {
        $admin = $this->userWithRole(
            RoleEnum::ADMINISTRADOR
        );

        Auditoria::create([
            'actor_nombre' =>
            'Admin KinesioSoft',

            'actor_email' =>
            'admin@example.com',

            'evento' =>
            'usuario_creado',

            'modulo' =>
            'Usuarios',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(
                route(
                    'auditorias.exportar-pdf'
                )
            );

        $response
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/pdf'
            );

        $this->assertDatabaseHas(
            'auditorias',
            [
                'evento' =>
                'auditoria_exportada_pdf',

                'modulo' =>
                'Auditoría',

                'actor_user_id' =>
                $admin->id,
            ]
        );
    }

    public function test_export_returns_to_filtered_index_without_auditing_when_there_are_no_results(): void
    {
        $admin = $this->userWithRole(
            RoleEnum::ADMINISTRADOR
        );

        Auditoria::create([
            'actor_nombre' =>
            'Admin KinesioSoft',

            'actor_email' =>
            'admin@example.com',

            'evento' =>
            'usuario_creado',

            'modulo' =>
            'Usuarios',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(
                route(
                    'auditorias.exportar-pdf'
                ),
                [
                    'modulo' =>
                    'Modulo inexistente',
                ]
            );

        $response
            ->assertRedirect(
                route(
                    'auditorias.index',
                    [
                        'modulo' =>
                        'Modulo inexistente',
                    ]
                )
            )
            ->assertSessionHas(
                'warning',
                'No hay registros de auditoría para exportar con los filtros aplicados.'
            );

        $this->assertDatabaseMissing(
            'auditorias',
            [
                'evento' =>
                'auditoria_exportada_pdf',
            ]
        );
    }

    private function userWithRole(
        RoleEnum $role
    ): User {
        $user = User::factory()->create([
            'activo' => true,
        ]);

        $user->assignRole(
            $role->value
        );

        return $user;
    }
}
