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
