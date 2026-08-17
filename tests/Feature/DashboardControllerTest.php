<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Http\Controllers\DashboardController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web');
        }
    }

    public function test_dashboard_route_uses_dashboard_controller(): void
    {
        $route = Route::getRoutes()->getByName('dashboard');

        $this->assertNotNull($route);

        $this->assertSame(
            DashboardController::class,
            $route->getActionName()
        );
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_administrador_sees_administrative_dashboard(): void
    {
        $response = $this->getDashboardFor(
            RoleEnum::ADMINISTRADOR
        );

        $response
            ->assertOk()
            ->assertSeeText('Administrador')
            ->assertSeeText('Accesos administrativos')
            ->assertSeeText(
                'Administre cuentas, roles y estado de acceso de los usuarios.'
            )
            ->assertSeeText(
                'Mantenga los catálogos generales utilizados por KinesioSoft.'
            )
            ->assertSeeText(
                'Consulte la trazabilidad de las operaciones registradas en el sistema.'
            )
            ->assertDontSeeText('Herramientas profesionales')
            ->assertDontSeeText('Accesos personales');
    }

    public function test_superadmin_sees_administrative_dashboard(): void
    {
        $response = $this->getDashboardFor(
            RoleEnum::SUPERADMIN
        );

        $response
            ->assertOk()
            ->assertSeeText('Superadministrador')
            ->assertSeeText('Accesos administrativos')
            ->assertSeeText(
                'Administre cuentas, roles y estado de acceso de los usuarios.'
            )
            ->assertSeeText(
                'Mantenga los catálogos generales utilizados por KinesioSoft.'
            )
            ->assertSeeText(
                'Consulte la trazabilidad de las operaciones registradas en el sistema.'
            )
            ->assertDontSeeText('Herramientas profesionales')
            ->assertDontSeeText('Accesos personales');
    }

    public function test_kinesiologo_sees_professional_dashboard(): void
    {
        $response = $this->getDashboardFor(
            RoleEnum::KINESIOLOGO
        );

        $response
            ->assertOk()
            ->assertSeeText('Kinesiólogo')
            ->assertSeeText('Herramientas profesionales')
            ->assertSeeText(
                'Consulte y mantenga actualizados sus datos personales y profesionales.'
            )
            ->assertSeeText(
                'Administre sus matrículas profesionales por jurisdicción y colegio emisor.'
            )
            ->assertDontSeeText('Accesos administrativos')
            ->assertDontSeeText('Accesos personales');
    }

    public function test_paciente_sees_patient_dashboard(): void
    {
        $response = $this->getDashboardFor(
            RoleEnum::PACIENTE
        );

        $response
            ->assertOk()
            ->assertSeeText('Paciente')
            ->assertSeeText('Accesos personales')
            ->assertSeeText(
                'Consulte y mantenga actualizados sus datos personales.'
            )
            ->assertDontSeeText('Accesos administrativos')
            ->assertDontSeeText('Herramientas profesionales');
    }

    public function test_user_without_role_cannot_access_dashboard_content(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario',
            'apellido' => 'Sin Rol',
        ]);

        $this->withoutMiddleware();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    private function getDashboardFor(
        RoleEnum $rol
    ): TestResponse {
        $user = User::factory()->create([
            'name' => 'Usuario',
            'apellido' => 'Prueba',
        ]);

        $user->assignRole($rol->value);

        /*
         * Estos tests están enfocados en la selección y presentación
         * del dashboard según el rol.
         *
         * La autenticación y el middleware perfil.completo tienen
         * sus propias pruebas específicas.
         */
        $this->withoutMiddleware();

        return $this->actingAs($user)
            ->get(route('dashboard'));
    }
}
