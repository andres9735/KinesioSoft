<?php

namespace Tests\Feature\Models;

use App\Enums\RoleEnum;
use App\Models\DeclaracionSaludPaciente;
use App\Models\Localidad;
use App\Models\Paciente;
use App\Models\Provincia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_integral_patient_profile_is_complete_without_legacy_address_or_optional_address_fields(): void
    {
        $user = $this->crearPacienteCompleto();

        $this->assertNull($user->paciente->direccion);
        $this->assertNull($user->paciente->piso_departamento);
        $this->assertNull($user->paciente->referencia_domicilio);
        $this->assertTrue($user->tienePerfilPacienteCompleto());
        $this->assertTrue($user->perfilCompleto());
    }

    public function test_legacy_address_does_not_replace_locality(): void
    {
        $user = $this->crearPacienteCompleto(
            paciente: [
                'direccion' => 'Dirección histórica',
                'localidad_id' => null,
            ]
        );

        $this->assertFalse($user->tienePerfilPacienteCompleto());
        $this->assertFalse($user->perfilCompleto());
    }

    public function test_legacy_address_does_not_replace_street(): void
    {
        $user = $this->crearPacienteCompleto(
            paciente: [
                'direccion' => 'Dirección histórica',
                'calle' => null,
            ]
        );

        $this->assertFalse($user->tienePerfilPacienteCompleto());
        $this->assertFalse($user->perfilCompleto());
    }

    public function test_legacy_address_does_not_replace_street_number(): void
    {
        $user = $this->crearPacienteCompleto(
            paciente: [
                'direccion' => 'Dirección histórica',
                'altura_domicilio' => null,
            ]
        );

        $this->assertFalse($user->tienePerfilPacienteCompleto());
        $this->assertFalse($user->perfilCompleto());
    }

    #[DataProvider('emptyStructuredAddressTexts')]
    public function test_empty_or_whitespace_structured_address_text_is_incomplete(
        string $field,
        string $value
    ): void {
        $user = $this->crearPacienteCompleto(
            paciente: [
                $field => $value,
            ]
        );

        $this->assertFalse($user->tienePerfilPacienteCompleto());
        $this->assertFalse($user->perfilCompleto());
    }

    public static function emptyStructuredAddressTexts(): array
    {
        return [
            'empty street' => ['calle', ''],
            'whitespace street' => ['calle', '   '],
            'empty street number' => ['altura_domicilio', ''],
            'whitespace street number' => ['altura_domicilio', '   '],
        ];
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
            'direccion' => null,
            'localidad_id' => $this->crearLocalidad()->id,
            'calle' => 'Avenida Mitre',
            'altura_domicilio' => '1234 A',
            'piso_departamento' => null,
            'referencia_domicilio' => null,
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
                'detalle_actividad_fisica' => 'Caminatas tres veces por semana.',
                'estado' => $estadoDeclaracion,
            ]);
        }

        return $userModel->fresh();
    }

    private function crearLocalidad(): Localidad
    {
        $provincia = Provincia::create([
            'georef_id' => '54',
            'nombre' => 'Misiones',
        ]);

        return Localidad::create([
            'georef_id' => '54098010',
            'provincia_id' => $provincia->id,
            'nombre' => 'Posadas',
            'departamento_nombre' => 'Capital',
        ]);
    }
}
