<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function renderNavigationFor(
        RoleEnum $rol
    ): string {
        $user = User::factory()->create([
            'name' => 'Usuario',
            'apellido' => 'Prueba',
        ]);

        $user->assignRole($rol->value);

        $this->actingAs($user);

        return view('layouts.navigation')->render();
    }
}
