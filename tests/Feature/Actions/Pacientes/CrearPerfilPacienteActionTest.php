<?php

namespace Tests\Feature\Actions\Pacientes;

use App\Actions\Pacientes\CrearPerfilPacienteAction;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CrearPerfilPacienteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_paciente_profile_with_generated_historia_clinica(): void
    {
        $user = User::factory()->create();

        $paciente = app(CrearPerfilPacienteAction::class)->ejecutar($user);

        $this->assertTrue($paciente->exists);
        $this->assertSame($user->id, $paciente->user_id);
        $this->assertNull($paciente->observaciones);
        $this->assertMatchesRegularExpression(
            '/^HC-\d{6}$/',
            $paciente->nro_historia_clinica
        );
        $this->assertSame(
            'HC-' . str_pad(
                (string) $paciente->id,
                6,
                '0',
                STR_PAD_LEFT
            ),
            $paciente->nro_historia_clinica
        );

        $this->assertDatabaseHas('pacientes', [
            'id' => $paciente->id,
            'user_id' => $user->id,
            'nro_historia_clinica' => $paciente->nro_historia_clinica,
            'observaciones' => null,
        ]);
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
