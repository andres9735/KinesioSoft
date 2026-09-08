<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleEnum;
use App\Models\DeclaracionSaludPaciente;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmailVerificationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]
            ->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $rol) {
            Role::findOrCreate(
                $rol->value,
                'web'
            );
        }
    }

    public function test_user_model_requires_email_verification(): void
    {
        $user = new User();

        $this->assertInstanceOf(
            MustVerifyEmail::class,
            $user
        );
    }

    public function test_registration_sends_email_verification_notification(): void
    {
        Notification::fake();

        $response = $this->post(
            route('register'),
            [
                'name' => 'Ana',
                'apellido' => 'Paciente',
                'email' => 'ana@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]
        );

        $response->assertRedirect(
            route('dashboard', absolute: false)
        );

        $user = User::query()
            ->where('email', 'ana@example.com')
            ->sole();

        $this->assertFalse(
            $user->hasVerifiedEmail()
        );

        Notification::assertSentTo(
            $user,
            VerifyEmail::class
        );
    }

    public function test_unverified_incomplete_patient_is_redirected_to_profile_completion_before_email_verification(): void
    {
        $user = $this->pacienteNoVerificado(
            completo: false
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(
                route('perfil.completar')
            );
    }

    public function test_unverified_complete_patient_is_redirected_to_email_verification_notice(): void
    {
        $user = $this->pacienteNoVerificado(
            completo: true
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(
                route('verification.notice')
            );
    }

    public function test_unverified_user_can_access_my_account(): void
    {
        $user = $this->pacienteNoVerificado(
            completo: false
        );

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSeeText('Mi cuenta')
            ->assertSeeText(
                'Su dirección de correo electrónico todavía no fue verificada.'
            );
    }

    public function test_unverified_administrator_cannot_access_administration(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'activo' => true,
        ]);

        $user->assignRole(
            RoleEnum::ADMINISTRADOR->value
        );

        $this->actingAs($user)
            ->get(route('usuarios.index'))
            ->assertRedirect(
                route('verification.notice')
            );
    }

    public function test_operational_routes_require_verified_middleware(): void
    {
        $routeNames = [
            'dashboard',
            'usuarios.index',
            'catalogos.index',
            'auditorias.index',
            'perfil.kinesiologa.matriculas.index',
        ];

        foreach ($routeNames as $routeName) {
            $route = Route::getRoutes()
                ->getByName($routeName);

            $this->assertNotNull(
                $route,
                "La ruta [{$routeName}] no está registrada."
            );

            $this->assertContains(
                'verified',
                $route->gatherMiddleware(),
                "La ruta [{$routeName}] debe requerir correo verificado."
            );
        }
    }

    public function test_onboarding_routes_do_not_require_verified_middleware(): void
    {
        $routeNames = [
            'perfil.completar',
            'perfil.actualizar',
            'perfil.kinesiologa.completar',
            'perfil.kinesiologa.actualizar',
            'profile.edit',
            'profile.update',
            'verification.notice',
            'verification.verify',
            'verification.send',
            'logout',
        ];

        foreach ($routeNames as $routeName) {
            $route = Route::getRoutes()
                ->getByName($routeName);

            $this->assertNotNull(
                $route,
                "La ruta [{$routeName}] no está registrada."
            );

            $this->assertNotContains(
                'verified',
                $route->gatherMiddleware(),
                "La ruta [{$routeName}] debe permanecer disponible durante el onboarding."
            );
        }
    }

    public function test_changing_email_requires_new_verification_and_allows_resend(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Paciente',
            'email' => 'ana@example.com',
            'email_verified_at' => now(),
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino',
            'telefono' => '3764123456',
            'activo' => true,
        ]);

        $user->assignRole(
            RoleEnum::PACIENTE->value
        );

        $paciente = Paciente::create([
            'user_id' => $user->id,
            'direccion' => 'Av. Mitre 1234',
            'contacto_emergencia_nombre' => 'María Pérez',
            'contacto_emergencia_telefono' => '3764123457',
            'activo' => true,
        ]);

        DeclaracionSaludPaciente::create([
            'paciente_id' => $paciente->id,
            'objetivo_salud' => 'Mejorar movilidad',
            'peso_kg' => 62.50,
            'altura_cm' => 165.00,
            'ocupacion' => 'Docente',
            'nivel_actividad_fisica' => 'MODERADO',
            'detalle_actividad_fisica' =>
            'Caminatas tres veces por semana.',
            'estado' => 'PENDIENTE_REVISION',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(
                route('profile.update'),
                [
                    'name' => 'Ana',
                    'apellido' => 'Paciente',
                    'email' => 'ana.nueva@example.com',
                ]
            );

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('profile.edit')
            );

        $user->refresh();

        $this->assertSame(
            'ana.nueva@example.com',
            $user->email
        );

        $this->assertFalse(
            $user->hasVerifiedEmail()
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(
                route('verification.notice')
            );

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSeeText(
                'Su dirección de correo electrónico todavía no fue verificada.'
            );

        Notification::fake();

        $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('verification.send'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas(
                'status',
                'verification-link-sent'
            );

        Notification::assertSentTo(
            $user,
            VerifyEmail::class
        );
    }

    private function pacienteNoVerificado(
        bool $completo
    ): User {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'dni' => $completo
                ? '30111222'
                : null,
            'fecha_nacimiento' => $completo
                ? '1990-01-01'
                : null,
            'sexo' => $completo
                ? 'femenino'
                : null,
            'telefono' => $completo
                ? '3764123456'
                : null,
            'activo' => true,
        ]);

        $user->assignRole(
            RoleEnum::PACIENTE->value
        );

        $paciente = Paciente::create([
            'user_id' => $user->id,
            'direccion' => $completo
                ? 'Av. Mitre 1234'
                : null,
            'contacto_emergencia_nombre' => $completo
                ? 'María Pérez'
                : null,
            'contacto_emergencia_telefono' => $completo
                ? '3764123457'
                : null,
            'activo' => true,
        ]);

        if ($completo) {
            DeclaracionSaludPaciente::create([
                'paciente_id' => $paciente->id,
                'objetivo_salud' => 'Mejorar movilidad',
                'peso_kg' => 72.50,
                'altura_cm' => 165.00,
                'ocupacion' => 'Docente',
                'nivel_actividad_fisica' => 'MODERADO',
                'detalle_actividad_fisica' =>
                'Caminatas tres veces por semana.',
                'estado' => 'PENDIENTE_REVISION',
            ]);
        }

        return $user->fresh();
    }
}
