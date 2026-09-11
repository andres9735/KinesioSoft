<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PerfilAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    private function usuarioConRol(RoleEnum $role): User
    {
        $user = User::factory()->create([
            'activo' => true,
        ]);

        $user->assignRole($role->value);

        return $user;
    }

    private function pacienteSinEmailVerificado(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'dni' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'telefono' => null,
            'activo' => true,
        ]);

        $user->assignRole(
            RoleEnum::PACIENTE->value
        );

        Paciente::create([
            'user_id' => $user->id,
            'activo' => true,
        ]);

        return $user;
    }

    private function datosPaso1PacienteValidos(): array
    {
        return [
            'dni' => '43122015',
            'fecha_nacimiento' => '2001-02-06',
            'sexo' => 'masculino',
            'telefono' => '3758-409036',
            'direccion' => 'Av. Mitre 1234',
            'contacto_emergencia_nombre' => 'María Pérez',
            'contacto_emergencia_telefono' => '3764123456',
        ];
    }

    private function datosPaso2PacienteValidos(): array
    {
        return [
            'objetivo_salud' =>
            'Mejorar movilidad y reducir molestias.',
            'peso_kg' => 72.50,
            'altura_cm' => 175.00,
            'ocupacion' => 'Docente',
            'nivel_actividad_fisica' => 'MODERADO',
            'detalle_actividad_fisica' =>
            'Caminatas tres veces por semana.',

            'antecedentes_respuesta' => 'si',
            'antecedentes' => [
                'Hipertensión controlada',
            ],

            'cirugias_respuesta' => 'no',
            'cirugias' => [],

            'alergias_respuesta' => 'no',
            'alergias' => [],

            'medicacion_respuesta' => 'si',
            'medicaciones' => [
                'Losartán',
            ],
        ];
    }

    private function assertProfileRoutesAreForbidden(
        User $user,
        string $getRoute,
        string $postRoute
    ): void {
        $this->actingAs($user)
            ->get(route($getRoute))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route($postRoute), [])
            ->assertForbidden();
    }

    private function assertPatientUc15RoutesAreForbidden(
        User $user
    ): void {
        $this->actingAs($user)
            ->get(route('perfil.completar'))
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-1'),
                []
            )
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-2'),
                []
            )
            ->assertForbidden();

        $this->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                []
            )
            ->assertForbidden();
    }

    public function test_paciente_cannot_access_kinesiologist_profile_routes(): void
    {
        $user = $this->usuarioConRol(RoleEnum::PACIENTE);

        $this->assertProfileRoutesAreForbidden(
            $user,
            'perfil.kinesiologa.completar',
            'perfil.kinesiologa.actualizar'
        );
    }

    public function test_kinesiologist_cannot_access_patient_uc15_routes(): void
    {
        $user = $this->usuarioConRol(
            RoleEnum::KINESIOLOGO
        );

        $this->assertPatientUc15RoutesAreForbidden(
            $user
        );
    }

    public function test_administrator_cannot_access_patient_uc15_routes(): void
    {
        $user = $this->usuarioConRol(
            RoleEnum::ADMINISTRADOR
        );

        $this->assertPatientUc15RoutesAreForbidden(
            $user
        );
    }

    public function test_administrator_cannot_access_kinesiologist_profile_routes(): void
    {
        $user = $this->usuarioConRol(RoleEnum::ADMINISTRADOR);

        $this->assertProfileRoutesAreForbidden(
            $user,
            'perfil.kinesiologa.completar',
            'perfil.kinesiologa.actualizar'
        );
    }

    public function test_superadmin_cannot_access_patient_uc15_routes(): void
    {
        $user = $this->usuarioConRol(
            RoleEnum::SUPERADMIN
        );

        $this->assertPatientUc15RoutesAreForbidden(
            $user
        );
    }

    public function test_superadmin_cannot_access_kinesiologist_profile_routes(): void
    {
        $user = $this->usuarioConRol(RoleEnum::SUPERADMIN);

        $this->assertProfileRoutesAreForbidden(
            $user,
            'perfil.kinesiologa.completar',
            'perfil.kinesiologa.actualizar'
        );
    }

    public function test_guest_cannot_access_patient_uc15_routes(): void
    {
        $this->get(
            route('perfil.completar')
        )->assertRedirect(
            route('login')
        );

        $this->postJson(
            route('perfil.validar-paso-1'),
            []
        )->assertUnauthorized();

        $this->postJson(
            route('perfil.validar-paso-2'),
            []
        )->assertUnauthorized();

        $this->post(
            route('perfil.actualizar'),
            []
        )->assertRedirect(
            route('login')
        );
    }

    public function test_unverified_patient_can_access_all_uc15_routes(): void
    {
        $user = $this->pacienteSinEmailVerificado();

        $this->assertNull(
            $user->email_verified_at
        );

        $this->actingAs($user)
            ->get(route('perfil.completar'))
            ->assertOk();

        $this->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-1'),
                $this->datosPaso1PacienteValidos()
            )
            ->assertNoContent();

        $this->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-2'),
                $this->datosPaso2PacienteValidos()
            )
            ->assertNoContent();

        $response = $this->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                array_merge(
                    $this->datosPaso1PacienteValidos(),
                    $this->datosPaso2PacienteValidos()
                )
            );

        $response->assertRedirect(
            route('verification.notice')
        );

        $response->assertSessionHas(
            'success',
            'Perfil clínico inicial completado correctamente. '
                . 'Verifique su correo electrónico para continuar.'
        );

        $this->assertNull(
            $user->fresh()->email_verified_at
        );
    }
}
