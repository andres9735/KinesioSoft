<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NavigationTest extends TestCase
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

    public function test_administrador_sees_administrative_navigation(): void
    {
        $html = $this->renderNavigationFor(
            RoleEnum::ADMINISTRADOR
        );

        $this->assertSame(
            2,
            substr_count($html, 'Usuarios')
        );

        $this->assertSame(
            2,
            substr_count($html, 'Catálogos')
        );

        $this->assertSame(
            2,
            substr_count($html, 'Auditoría')
        );

        $this->assertSame(
            2,
            substr_count($html, 'Mi cuenta')
        );

        $this->assertStringNotContainsString(
            'Mi perfil profesional',
            $html
        );

        $this->assertStringNotContainsString(
            'Mis matrículas',
            $html
        );

        $this->assertStringNotContainsString(
            'Mi perfil</a>',
            $html
        );
    }

    public function test_superadmin_sees_administrative_navigation(): void
    {
        $html = $this->renderNavigationFor(
            RoleEnum::SUPERADMIN
        );

        $this->assertSame(
            2,
            substr_count($html, 'Usuarios')
        );

        $this->assertSame(
            2,
            substr_count($html, 'Catálogos')
        );

        $this->assertSame(
            2,
            substr_count($html, 'Auditoría')
        );

        $this->assertSame(
            2,
            substr_count($html, 'Mi cuenta')
        );

        $this->assertStringNotContainsString(
            'Mi perfil profesional',
            $html
        );

        $this->assertStringNotContainsString(
            'Mis matrículas',
            $html
        );
    }

    public function test_kinesiologo_sees_professional_navigation(): void
    {
        $html = $this->renderNavigationFor(
            RoleEnum::KINESIOLOGO
        );

        $this->assertSame(
            2,
            substr_count($html, 'Mi perfil profesional')
        );

        $this->assertSame(
            2,
            substr_count($html, 'Mis matrículas')
        );

        $this->assertSame(
            2,
            substr_count($html, 'Mi cuenta')
        );

        $this->assertStringNotContainsString(
            'Usuarios',
            $html
        );

        $this->assertStringNotContainsString(
            'Catálogos',
            $html
        );

        $this->assertStringNotContainsString(
            'Auditoría',
            $html
        );
    }

    public function test_paciente_sees_patient_navigation(): void
    {
        $html = $this->renderNavigationFor(
            RoleEnum::PACIENTE
        );

        $this->assertSame(
            2,
            substr_count($html, 'Mi perfil')
        );

        $this->assertSame(
            2,
            substr_count($html, 'Mi cuenta')
        );

        $this->assertStringNotContainsString(
            'Mi perfil profesional',
            $html
        );

        $this->assertStringNotContainsString(
            'Mis matrículas',
            $html
        );

        $this->assertStringNotContainsString(
            'Usuarios',
            $html
        );

        $this->assertStringNotContainsString(
            'Catálogos',
            $html
        );

        $this->assertStringNotContainsString(
            'Auditoría',
            $html
        );
    }

    public function test_navigation_uses_real_submit_for_logout_confirmation(): void
    {
        $html = $this->renderNavigationFor(
            RoleEnum::PACIENTE
        );

        $this->assertSame(
            2,
            substr_count(
                $html,
                'class="js-confirm-action"'
            )
        );

        $this->assertSame(
            2,
            substr_count(
                $html,
                'data-confirm-title="¿Cerrar sesión?"'
            )
        );

        $this->assertSame(
            2,
            substr_count(
                $html,
                'data-confirm-button="Sí, cerrar sesión"'
            )
        );

        $this->assertSame(
            2,
            substr_count(
                $html,
                'data-cancel-button="Cancelar"'
            )
        );

        $this->assertSame(
            2,
            substr_count(
                $html,
                'data-confirm-icon="question"'
            )
        );

        $this->assertSame(
            2,
            substr_count(
                $html,
                'action="' . route('logout') . '"'
            )
        );

        $this->assertStringContainsString(
            'type="submit"',
            $html
        );

        $this->assertStringNotContainsString(
            "this.closest('form').submit()",
            $html
        );
    }

    public function test_active_navigation_components_expose_aria_current(): void
    {
        $desktop = $this->blade(
            '<x-nav-link href="/prueba" :active="true">Prueba</x-nav-link>'
        );

        $responsive = $this->blade(
            '<x-responsive-nav-link href="/prueba" :active="true">Prueba</x-responsive-nav-link>'
        );

        $dropdown = $this->blade(
            '<x-dropdown-link href="/prueba" :active="true">Prueba</x-dropdown-link>'
        );

        $desktop->assertSee('aria-current="page"', false);
        $responsive->assertSee('aria-current="page"', false);
        $dropdown->assertSee('aria-current="page"', false);
    }

    public function test_my_account_is_active_on_profile_routes(): void
    {
        $html = $this->renderNavigationFor(
            RoleEnum::PACIENTE,
            'profile.edit'
        );

        $this->assertSame(
            2,
            substr_count(
                $html,
                'aria-current="page"'
            )
        );
    }

    public function test_navigation_exposes_basic_accessibility_state(): void
    {
        $html = $this->renderNavigationFor(
            RoleEnum::PACIENTE
        );

        $this->assertStringContainsString(
            'aria-label="Navegación principal"',
            $html
        );

        $this->assertStringContainsString(
            'aria-label="Ir al dashboard"',
            $html
        );

        $this->assertStringContainsString(
            'aria-label="Abrir o cerrar menú de navegación"',
            $html
        );

        $this->assertStringContainsString(
            'aria-controls="responsive-navigation-menu"',
            $html
        );

        $this->assertStringContainsString(
            'id="responsive-navigation-menu"',
            $html
        );

        $this->assertStringContainsString(
            'aria-controls="user-account-menu"',
            $html
        );

        $this->assertStringContainsString(
            'id="user-account-menu"',
            $html
        );

        $this->assertSame(
            2,
            substr_count(
                $html,
                ':aria-expanded="open.toString()"'
            )
        );

        $this->assertSame(
            2,
            substr_count(
                $html,
                '@keydown.escape.window="open = false"'
            )
        );
    }

    private function renderNavigationFor(
        RoleEnum $rol,
        ?string $routeName = null
    ): string {
        $user = User::factory()->create([
            'name' => 'Usuario',
            'apellido' => 'Prueba',
        ]);

        $user->assignRole($rol->value);

        $this->actingAs($user);

        if ($routeName !== null) {
            $route = new Route(
                ['GET'],
                '/prueba',
                static fn() => null
            );

            $route->name($routeName);

            request()->setRouteResolver(
                static fn() => $route
            );
        }

        return view('layouts.navigation')->render();
    }
}
