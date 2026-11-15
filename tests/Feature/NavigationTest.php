<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Http\Middleware\EnsureUserProfileIsComplete;
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
            substr_count($html, 'href="'.route('catalogos.index').'"')
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
            'Horarios de atención',
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
            substr_count($html, 'href="'.route('catalogos.index').'"')
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
            'Horarios de atención',
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
            substr_count($html, 'Horarios de atención')
        );

        $this->assertSame(
            2,
            substr_count(
                $html,
                'href="'.route('turnos.agenda.index').'"'
            )
        );

        $primerDashboard = strpos($html, 'Dashboard');
        $primerPerfil = strpos($html, 'Mi perfil profesional');
        $primerMatriculas = strpos($html, 'Mis matrículas');
        $primerHorarios = strpos($html, 'Horarios de atención');

        $this->assertIsInt($primerDashboard);
        $this->assertIsInt($primerPerfil);
        $this->assertIsInt($primerMatriculas);
        $this->assertIsInt($primerHorarios);
        $this->assertLessThan($primerPerfil, $primerDashboard);
        $this->assertLessThan($primerMatriculas, $primerPerfil);
        $this->assertLessThan($primerHorarios, $primerMatriculas);

        $segundoDashboard = strpos($html, 'Dashboard', $primerDashboard + 1);
        $segundoPerfil = strpos($html, 'Mi perfil profesional', $primerPerfil + 1);
        $segundoMatriculas = strpos($html, 'Mis matrículas', $primerMatriculas + 1);
        $segundoHorarios = strpos($html, 'Horarios de atención', $primerHorarios + 1);

        $this->assertIsInt($segundoDashboard);
        $this->assertIsInt($segundoPerfil);
        $this->assertIsInt($segundoMatriculas);
        $this->assertIsInt($segundoHorarios);
        $this->assertLessThan($segundoPerfil, $segundoDashboard);
        $this->assertLessThan($segundoMatriculas, $segundoPerfil);
        $this->assertLessThan($segundoHorarios, $segundoMatriculas);

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
            'href="'.route('catalogos.index').'"',
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
            'Horarios de atención',
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
            'href="'.route('catalogos.index').'"',
            $html
        );

        $this->assertStringNotContainsString(
            'Auditoría',
            $html
        );
    }

    public function test_catalog_hub_links_the_ten_catalogs(): void
    {
        $response = $this
            ->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.index'));

        $response->assertOk();

        foreach (
            [
                'catalogos.jurisdicciones.index' => 'Jurisdicciones',
                'catalogos.colegios.index' => 'Colegios emisores',
                'catalogos.titulos.index' => 'Títulos profesionales',
                'catalogos.especialidades.index' => 'Especialidades de kinesiología',
                'catalogos.diagnosticos.index' => 'Diagnósticos clínicos',
                'catalogos.pruebas.index' => 'Pruebas funcionales',
                'catalogos.tecnicas.index' => 'Técnicas terapéuticas',
                'catalogos.categorias.index' => 'Categorías clínicas',
                'catalogos.ejercicios.index' => 'Ejercicios domiciliarios',
                'catalogos.tipos-equipo.index' => 'Tipos de equipo terapéutico',
            ] as $routeName => $catalogName
        ) {
            $response
                ->assertSeeText($catalogName)
                ->assertSee(
                    'href="'.route($routeName).'"',
                    false
                );
        }

        $response->assertDontSeeText('Aún no disponible');
    }

    public function test_superadmin_can_access_catalog_hub(): void
    {
        $this
            ->actingAs($this->userWithRole(RoleEnum::SUPERADMIN))
            ->get(route('catalogos.index'))
            ->assertOk();
    }

    public function test_non_administrative_roles_cannot_access_catalog_hub(): void
    {
        $this->withoutMiddleware(EnsureUserProfileIsComplete::class);

        foreach (
            [RoleEnum::KINESIOLOGO, RoleEnum::PACIENTE] as $role
        ) {
            $this
                ->actingAs($this->userWithRole($role))
                ->get(route('catalogos.index'))
                ->assertForbidden();
        }
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
                'action="'.route('logout').'"'
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

    public function test_horarios_de_atencion_is_active_on_every_agenda_route(): void
    {
        $html = $this->renderNavigationFor(
            RoleEnum::KINESIOLOGO,
            'turnos.agenda.bloqueos.show'
        );

        $this->assertSame(
            2,
            substr_count($html, 'aria-current="page"')
        );

        $this->assertSame(
            2,
            substr_count($html, 'Horarios de atención')
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

    public function test_agenda_de_atencion_esta_activa_en_menu_principal_y_movil_solo_para_kinesiologo(): void
    {
        foreach (RoleEnum::cases() as $rol) {
            $html = $this->renderNavigationFor($rol, 'turnos.agenda-atencion.show');
            $url = route('turnos.agenda-atencion.index');
            if ($rol !== RoleEnum::KINESIOLOGO) {
                $this->assertStringNotContainsString($url, $html);

                continue;
            }

            $this->assertSame(2, substr_count($html, 'href="'.$url.'"'));
            $principal = explode('<!-- Settings Dropdown -->', $html)[0];
            $this->assertSame(1, substr_count($principal, 'href="'.$url.'"'));
            $dom = new \DOMDocument;
            @$dom->loadHTML($html);
            $enlaces = (new \DOMXPath($dom))->query('//a[@href="'.$url.'"]');
            foreach ($enlaces as $enlace) {
                $this->assertSame('page', $enlace->getAttribute('aria-current'));
            }
        }
    }

    private function renderNavigationFor(
        RoleEnum $rol,
        ?string $routeName = null
    ): string {
        $user = $this->userWithRole($rol);

        $this->actingAs($user);

        if ($routeName !== null) {
            $route = new Route(
                ['GET'],
                '/prueba',
                static fn () => null
            );

            $route->name($routeName);

            request()->setRouteResolver(
                static fn () => $route
            );
        }

        return view('layouts.navigation')->render();
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create([
            'name' => 'Usuario',
            'apellido' => 'Prueba',
        ]);

        $user->assignRole($role->value);

        return $user;
    }
}
