<?php

namespace Tests\Feature\Models;

use App\Enums\RoleEnum;
use App\Models\DeclaracionSaludPaciente;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserPerfilPacienteCompletoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]
            ->forgetCachedPermissions();

        Role::findOrCreate(
            RoleEnum::PACIENTE->value,
            'web'
        );
    }

    public function test_paciente_without_personal_data_is_incomplete(): void
    {
        $user = $this->crearPacienteCompleto([
            'dni' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'telefono' => null,
        ]);

        $this->assertFalse($user->perfilCompleto());
    }

    public function test_paciente_without_paciente_record_is_incomplete(): void
    {
        $user = User::factory()->create([
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'masculino',
            'telefono' => '3764123456',
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::PACIENTE->value);

        $this->assertFalse($user->perfilCompleto());
    }

    public function test_paciente_without_direccion_is_incomplete(): void
    {
        $user = $this->crearPacienteCompleto(
            paciente: ['direccion' => null]
        );

        $this->assertFalse($user->perfilCompleto());
    }

    public function test_paciente_without_emergency_contact_name_is_incomplete(): void
    {
        $user = $this->crearPacienteCompleto(
            paciente: ['contacto_emergencia_nombre' => null]
        );

        $this->assertFalse($user->perfilCompleto());
    }

    public function test_paciente_without_emergency_contact_phone_is_incomplete(): void
    {
        $user = $this->crearPacienteCompleto(
            paciente: ['contacto_emergencia_telefono' => null]
        );

        $this->assertFalse($user->perfilCompleto());
    }

    public function test_paciente_without_health_declaration_is_incomplete(): void
    {
        $user = $this->crearPacienteCompleto(
            conDeclaracion: false
        );

        $this->assertFalse($user->perfilCompleto());
    }

    public function test_paciente_with_required_data_and_declaration_is_complete(): void
    {
        $user = $this->crearPacienteCompleto();

        $this->assertTrue($user->perfilCompleto());
    }

    public function test_declaration_state_does_not_affect_profile_completeness(): void
    {
        $user = $this->crearPacienteCompleto(
            estadoDeclaracion: 'DESCARTADA'
        );

        $this->assertTrue($user->perfilCompleto());
    }

    public function test_historia_clinica_is_not_required_for_profile_completeness(): void
    {
        $user = $this->crearPacienteCompleto();

        $this->assertDatabaseCount('historias_clinicas', 0);
        $this->assertTrue($user->perfilCompleto());
    }

    public function test_user_active_state_is_not_part_of_profile_completeness(): void
    {
        $user = $this->crearPacienteCompleto([
            'activo' => false,
        ]);

        $this->assertTrue($user->perfilCompleto());
    }

    private function crearPacienteCompleto(
        array $user = [],
        array $paciente = [],
        bool $conDeclaracion = true,
        string $estadoDeclaracion = 'PENDIENTE_REVISION'
    ): User {
        $userModel = User::factory()->create(array_merge([
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'masculino',
            'telefono' => '3764123456',
            'activo' => true,
        ], $user));

        $userModel->assignRole(RoleEnum::PACIENTE->value);

        $pacienteModel = Paciente::create(array_merge([
            'user_id' => $userModel->id,
            'direccion' => 'Av. Mitre 1234',
            'contacto_emergencia_nombre' => 'María Pérez',
            'contacto_emergencia_telefono' => '3764123457',
            'activo' => true,
        ], $paciente));

        if ($conDeclaracion) {
            DeclaracionSaludPaciente::create([
                'paciente_id' => $pacienteModel->id,
                'objetivo_salud' => 'Mejorar movilidad',
                'peso_kg' => 72.50,
                'altura_cm' => 175.00,
                'ocupacion' => 'Docente',
                'nivel_actividad_fisica' => 'MODERADO',
                'detalle_actividad_fisica' =>
                    'Caminatas tres veces por semana.',
                'estado' => $estadoDeclaracion,
            ]);
        }

        return $userModel->fresh();
    }
}
