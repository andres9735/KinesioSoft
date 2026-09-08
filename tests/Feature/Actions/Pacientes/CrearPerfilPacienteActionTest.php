<?php

namespace Tests\Feature\Actions\Pacientes;

use App\Actions\Pacientes\CrearPerfilPacienteAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CrearPerfilPacienteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_administrative_patient_profile_without_historia_clinica(): void
    {
        $user = User::factory()->create();

        $paciente = app(CrearPerfilPacienteAction::class)->ejecutar($user);

        $this->assertTrue($paciente->exists);
        $this->assertSame($user->id, $paciente->user_id);
        $this->assertNull($paciente->direccion);
        $this->assertNull($paciente->contacto_emergencia_nombre);
        $this->assertNull($paciente->contacto_emergencia_telefono);
        $this->assertTrue($paciente->activo);

        $this->assertDatabaseHas('pacientes', [
            'id' => $paciente->id,
            'user_id' => $user->id,
            'direccion' => null,
            'contacto_emergencia_nombre' => null,
            'contacto_emergencia_telefono' => null,
            'activo' => true,
        ]);

        $this->assertDatabaseCount('historias_clinicas', 0);
    }

    public function test_throws_when_user_is_not_persisted(): void
    {
        $user = new User();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'El usuario debe estar persistido antes de crear su perfil de paciente.'
        );

        app(CrearPerfilPacienteAction::class)->ejecutar($user);
    }

    public function test_throws_when_user_already_has_paciente_profile(): void
    {
        $user = User::factory()->create();

        app(CrearPerfilPacienteAction::class)->ejecutar($user);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'El usuario ya tiene un perfil de paciente.'
        );

        app(CrearPerfilPacienteAction::class)->ejecutar($user);
    }
}
