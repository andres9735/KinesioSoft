<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\DeclaracionSaludPaciente;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PerfilPacienteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findOrCreate(RoleEnum::PACIENTE->value, 'web');
    }

    private function pacienteIncompleto(): User
    {
        $user = User::factory()->create([
            'dni' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'telefono' => null,
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::PACIENTE->value);

        Paciente::create([
            'user_id' => $user->id,
            'activo' => true,
        ]);

        return $user;
    }

    private function pacienteCompleto(): User
    {
        $user = User::factory()->create([
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'masculino',
            'telefono' => '3764123456',
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::PACIENTE->value);

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
            'peso_kg' => 72.50,
            'altura_cm' => 175.00,
            'ocupacion' => 'Docente',
            'nivel_actividad_fisica' => 'MODERADO',
            'detalle_actividad_fisica' =>
            'Caminatas tres veces por semana.',
            'estado' => 'PENDIENTE_REVISION',
        ]);

        return $user->fresh();
    }

    public function test_paciente_can_view_completar_perfil_screen(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->actingAs($user)->get(route('perfil.completar'));

        $response->assertOk();
    }

    public function test_step_1_validation_accepts_valid_data_without_persisting(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-1'),
                [
                    'dni' => '43122015',
                    'fecha_nacimiento' => '2001-02-06',
                    'sexo' => 'masculino',
                    'telefono' => '3758-409036',
                    'direccion' => 'Av. Mitre 1234',
                    'contacto_emergencia_nombre' => 'María Pérez',
                    'contacto_emergencia_telefono' => '3764123456',
                ]
            );

        $response->assertNoContent();

        $user->refresh();
        $user->paciente->refresh();

        $this->assertNull($user->dni);
        $this->assertNull($user->fecha_nacimiento);
        $this->assertNull($user->sexo);
        $this->assertNull($user->telefono);

        $this->assertNull($user->paciente->direccion);
        $this->assertNull(
            $user->paciente->contacto_emergencia_nombre
        );
        $this->assertNull(
            $user->paciente->contacto_emergencia_telefono
        );

        $this->assertDatabaseCount(
            'declaraciones_salud_paciente',
            0
        );

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );
    }

    public function test_step_1_validation_rejects_invalid_data(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-1'),
                [
                    'dni' => '',
                    'fecha_nacimiento' => now()
                        ->addDay()
                        ->format('Y-m-d'),
                    'sexo' => 'invalido',
                    'telefono' => 'telefono!',
                    'direccion' => '',
                    'contacto_emergencia_nombre' => '',
                    'contacto_emergencia_telefono' => 'telefono!',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'dni',
                'fecha_nacimiento',
                'sexo',
                'telefono',
                'direccion',
                'contacto_emergencia_nombre',
                'contacto_emergencia_telefono',
            ]);
    }

    public function test_step_2_validation_accepts_valid_data_without_persisting(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-2'),
                [
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
                ]
            );

        $response->assertNoContent();

        $this->assertDatabaseCount(
            'declaraciones_salud_paciente',
            0
        );

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );

        $user->refresh();
        $user->paciente->refresh();

        $this->assertNull($user->dni);
        $this->assertNull($user->paciente->direccion);
    }

    public function test_step_2_validation_requires_detail_when_answer_is_yes(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-2'),
                [
                    'objetivo_salud' => 'Mejorar movilidad',
                    'peso_kg' => 72.50,
                    'altura_cm' => 175.00,
                    'ocupacion' => 'Docente',
                    'nivel_actividad_fisica' => 'MODERADO',
                    'detalle_actividad_fisica' =>
                    'Caminatas tres veces por semana.',

                    'antecedentes_respuesta' => 'si',
                    'antecedentes' => [
                        '   ',
                    ],

                    'cirugias_respuesta' => 'no',
                    'cirugias' => [],

                    'alergias_respuesta' => 'no',
                    'alergias' => [],

                    'medicacion_respuesta' => 'no',
                    'medicaciones' => [],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'antecedentes',
            ]);

        $this->assertDatabaseCount(
            'declaraciones_salud_paciente',
            0
        );
    }

    public function test_step_2_validation_rejects_details_when_answer_is_no(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-2'),
                [
                    'objetivo_salud' => 'Mejorar movilidad',
                    'peso_kg' => 72.50,
                    'altura_cm' => 175.00,
                    'ocupacion' => 'Docente',
                    'nivel_actividad_fisica' => 'MODERADO',
                    'detalle_actividad_fisica' =>
                    'Caminatas tres veces por semana.',

                    'antecedentes_respuesta' => 'no',
                    'antecedentes' => [
                        'Hipertensión',
                    ],

                    'cirugias_respuesta' => 'no',
                    'cirugias' => [],

                    'alergias_respuesta' => 'no',
                    'alergias' => [],

                    'medicacion_respuesta' => 'no',
                    'medicaciones' => [],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'antecedentes',
            ]);

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );
    }

    public function test_paciente_can_update_profile_with_valid_data(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->actingAs($user)->post(route('perfil.actualizar'), [
            'dni' => '43122015',
            'fecha_nacimiento' => '2001-02-06',
            'sexo' => 'masculino',
            'telefono' => '3758-409036',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', 'Perfil completado correctamente.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'dni' => '43122015',
            'fecha_nacimiento' => '2001-02-06',
            'sexo' => 'masculino',
            'telefono' => '3758-409036',
        ]);
    }

    public function test_update_requires_dni(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'masculino',
                'telefono' => '3758-409036',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('dni');
    }

    public function test_update_requires_fecha_nacimiento(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '43122015',
                'fecha_nacimiento' => '',
                'sexo' => 'masculino',
                'telefono' => '3758-409036',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('fecha_nacimiento');
    }

    public function test_update_requires_fecha_nacimiento_before_today(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '43122015',
                'fecha_nacimiento' => now()->addDay()->format('Y-m-d'),
                'sexo' => 'masculino',
                'telefono' => '3758-409036',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('fecha_nacimiento');
    }

    public function test_update_requires_valid_sexo(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '43122015',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'valor_invalido',
                'telefono' => '3758-409036',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('sexo');
    }

    public function test_update_requires_telefono(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '43122015',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'masculino',
                'telefono' => '',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('telefono');
    }

    public function test_update_rejects_invalid_telefono_format(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '43122015',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'masculino',
                'telefono' => 'telefono-invalido!',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('telefono');
    }

    public function test_update_requires_unique_dni_except_current_user(): void
    {
        $existingUser = User::factory()->create([
            'dni' => '43122015',
            'activo' => true,
        ]);

        $existingUser->assignRole(RoleEnum::PACIENTE->value);

        $user = $this->pacienteIncompleto();

        $response = $this->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '43122015',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'masculino',
                'telefono' => '3758-409036',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('dni');
    }

    public function test_paciente_incompleto_is_redirected_to_completar_perfil_when_accessing_dashboard(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('perfil.completar'));
    }

    public function test_paciente_completo_can_access_dashboard_without_redirection(): void
    {
        $user = $this->pacienteCompleto();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_paciente_can_keep_same_dni_when_updating_own_profile(): void
    {
        $user = $this->pacienteCompleto();

        $response = $this->actingAs($user)->post(route('perfil.actualizar'), [
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'masculino',
            'telefono' => '3764123456',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasNoErrors();
    }
}
