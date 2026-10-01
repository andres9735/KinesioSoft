<?php

namespace Tests\Feature;

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

class PerfilPacienteCompletitudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        Role::findOrCreate(
            RoleEnum::PACIENTE->value,
            'web'
        );
    }

    private function usuarioPacienteConDatosPersonales(): User
    {
        $user = User::factory()->create([
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'masculino',
            'telefono' => '3764123456',
            'activo' => true,
        ]);

        $user->assignRole(
            RoleEnum::PACIENTE->value
        );

        return $user;
    }

    private function crearPacienteAdministrativamenteCompleto(
        User $user
    ): Paciente {
        return Paciente::create([
            'user_id' => $user->id,
            'direccion' => null,
            'localidad_id' => $this->crearLocalidad()->id,
            'calle' => 'Avenida Mitre',
            'altura_domicilio' => '1234 A',
            'piso_departamento' => null,
            'referencia_domicilio' => null,
            'contacto_emergencia_nombre' => 'María Pérez',
            'contacto_emergencia_telefono' => '3764123457',
            'activo' => true,
        ]);
    }

    private function crearDeclaracionSalud(
        Paciente $paciente
    ): DeclaracionSaludPaciente {
        return DeclaracionSaludPaciente::create([
            'paciente_id' => $paciente->id,
            'objetivo_salud' => 'Mejorar movilidad',
            'peso_kg' => 72.50,
            'altura_cm' => 175.00,
            'ocupacion' => 'Docente',
            'nivel_actividad_fisica' => 'MODERADO',
            'detalle_actividad_fisica' => 'Caminatas tres veces por semana.',
            'estado' => DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION,
        ]);
    }

    private function pacienteConPerfilCompleto(): User
    {
        $user = $this
            ->usuarioPacienteConDatosPersonales();

        $paciente = $this
            ->crearPacienteAdministrativamenteCompleto(
                $user
            );

        $this->crearDeclaracionSalud(
            $paciente
        );

        return $user->fresh();
    }

    #[DataProvider('camposPersonalesRequeridos')]
    public function test_perfil_is_incomplete_when_required_user_field_is_missing(
        string $campo
    ): void {
        $user = $this->pacienteConPerfilCompleto();

        $user->forceFill([
            $campo => null,
        ])->save();

        $user = $user->fresh();

        $this->assertFalse(
            $user->perfilCompleto()
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(
                route('perfil.completar')
            );
    }

    public static function camposPersonalesRequeridos(): array
    {
        return [
            'dni' => [
                'dni',
            ],
            'fecha nacimiento' => [
                'fecha_nacimiento',
            ],
            'sexo' => [
                'sexo',
            ],
            'telefono' => [
                'telefono',
            ],
        ];
    }

    #[DataProvider('camposAdministrativosRequeridos')]
    public function test_perfil_is_incomplete_when_required_patient_field_is_missing(
        string $campo
    ): void {
        $user = $this->pacienteConPerfilCompleto();

        $paciente = $user->paciente;

        $paciente->forceFill([
            $campo => null,
        ])->save();

        $user = $user->fresh();

        $this->assertFalse(
            $user->perfilCompleto()
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(
                route('perfil.completar')
            );
    }

    public static function camposAdministrativosRequeridos(): array
    {
        return [
            'localidad' => [
                'localidad_id',
            ],
            'calle' => [
                'calle',
            ],
            'altura domicilio' => [
                'altura_domicilio',
            ],
            'contacto emergencia nombre' => [
                'contacto_emergencia_nombre',
            ],
            'contacto emergencia telefono' => [
                'contacto_emergencia_telefono',
            ],
        ];
    }

    public function test_perfil_is_incomplete_when_patient_record_does_not_exist(): void
    {
        $user = $this
            ->usuarioPacienteConDatosPersonales()
            ->fresh();

        $this->assertFalse(
            $user->perfilCompleto()
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(
                route('perfil.completar')
            );
    }

    public function test_perfil_is_incomplete_when_health_declaration_does_not_exist(): void
    {
        $user = $this
            ->usuarioPacienteConDatosPersonales();

        $this->crearPacienteAdministrativamenteCompleto(
            $user
        );

        $user = $user->fresh();

        $this->assertFalse(
            $user->perfilCompleto()
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(
                route('perfil.completar')
            );
    }

    public function test_perfil_is_complete_with_required_data_and_health_declaration_without_clinical_history(): void
    {
        $user = $this->pacienteConPerfilCompleto();

        $this->assertDatabaseCount(
            'historias_clinicas',
            0
        );

        $this->assertTrue(
            $user->perfilCompleto()
        );

        $this->assertNull($user->paciente->direccion);
        $this->assertNull($user->paciente->piso_departamento);
        $this->assertNull($user->paciente->referencia_domicilio);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
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
