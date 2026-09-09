<?php

namespace Tests\Feature\Actions\Pacientes;

use App\Actions\Pacientes\GuardarPerfilPacienteAction;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardarPerfilPacienteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completes_incomplete_profile_and_audits_changes(): void
    {
        $user = User::factory()->create([
            'name' => 'Paciente',
            'apellido' => 'Prueba',
            'email' => 'paciente.prueba@example.com',
            'dni' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'telefono' => null,
        ]);

        $this->actingAs($user);

        $actualizado = app(GuardarPerfilPacienteAction::class)->ejecutar(
            $user,
            [
                'dni' => '43122015',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'masculino',
                'telefono' => '3758-409036',
            ]
        );

        $actualizado->refresh();

        $this->assertSame('43122015', $actualizado->dni);
        $this->assertSame(
            '2001-02-06',
            $actualizado->getRawOriginal('fecha_nacimiento')
        );
        $this->assertSame('masculino', $actualizado->sexo);
        $this->assertSame('3758-409036', $actualizado->telefono);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'perfil_paciente_completado',
            $auditoria->evento
        );
        $this->assertSame('Perfiles', $auditoria->modulo);
        $this->assertSame($user->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($user->id, $auditoria->auditable_id);

        $this->assertSame(
            [
                'dni' => null,
                'fecha_nacimiento' => null,
                'sexo' => null,
                'telefono' => null,
            ],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            [
                'dni' => '43122015',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'masculino',
                'telefono' => '3758-409036',
            ],
            $auditoria->valores_nuevos
        );

        $this->assertSame($user->id, $auditoria->actor_user_id);
        $this->assertSame('Paciente Prueba', $auditoria->actor_nombre_snapshot);
        $this->assertSame(
            'paciente.prueba@example.com',
            $auditoria->actor_email_snapshot
        );
    }

    public function test_updates_complete_profile_and_audits_only_changed_fields(): void
    {
        $user = User::factory()->create([
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'masculino',
            'telefono' => '3764123456',
        ]);

        $this->actingAs($user);

        app(GuardarPerfilPacienteAction::class)->ejecutar(
            $user,
            [
                'dni' => '30111222',
                'fecha_nacimiento' => '1990-01-01',
                'sexo' => 'masculino',
                'telefono' => '3764999999',
            ]
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'perfil_paciente_actualizado',
            $auditoria->evento
        );

        $this->assertSame(
            ['telefono' => '3764123456'],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            ['telefono' => '3764999999'],
            $auditoria->valores_nuevos
        );
    }

    public function test_completing_partially_filled_profile_audits_only_fields_that_changed(): void
    {
        $user = User::factory()->create([
            'dni' => '30111222',
            'fecha_nacimiento' => null,
            'sexo' => 'femenino',
            'telefono' => null,
        ]);

        $this->actingAs($user);

        app(GuardarPerfilPacienteAction::class)->ejecutar(
            $user,
            [
                'dni' => '30111222',
                'fecha_nacimiento' => '1995-04-12',
                'sexo' => 'femenino',
                'telefono' => '3764555555',
            ]
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'perfil_paciente_completado',
            $auditoria->evento
        );

        $this->assertSame(
            [
                'fecha_nacimiento' => null,
                'telefono' => null,
            ],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            [
                'fecha_nacimiento' => '1995-04-12',
                'telefono' => '3764555555',
            ],
            $auditoria->valores_nuevos
        );
    }

    public function test_does_not_audit_when_profile_has_no_changes(): void
    {
        $user = User::factory()->create([
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'masculino',
            'telefono' => '3764123456',
        ]);

        $this->actingAs($user);

        $actualizado = app(GuardarPerfilPacienteAction::class)->ejecutar(
            $user,
            [
                'dni' => '30111222',
                'fecha_nacimiento' => '1990-01-01',
                'sexo' => 'masculino',
                'telefono' => '3764123456',
            ]
        );

        $actualizado->refresh();

        $this->assertSame('30111222', $actualizado->dni);
        $this->assertSame('3764123456', $actualizado->telefono);
        $this->assertDatabaseCount('auditorias', 0);
    }
}
