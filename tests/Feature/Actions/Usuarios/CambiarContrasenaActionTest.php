<?php

namespace Tests\Feature\Actions\Usuarios;

use App\Actions\Usuarios\CambiarContrasenaAction;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CambiarContrasenaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_changes_password_and_audits_semantic_event_without_sensitive_snapshots(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Paciente',
            'email' => 'ana@example.com',
            'password' => Hash::make('ContrasenaAnterior123!'),
        ]);

        $this->actingAs($user);

        $actualizado = app(CambiarContrasenaAction::class)->ejecutar(
            $user,
            'ContrasenaNueva456!'
        );

        $actualizado->refresh();

        $this->assertTrue(
            Hash::check('ContrasenaNueva456!', $actualizado->password)
        );
        $this->assertFalse(
            Hash::check('ContrasenaAnterior123!', $actualizado->password)
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'contrasena_actualizada',
            $auditoria->evento
        );
        $this->assertSame('Cuenta', $auditoria->modulo);
        $this->assertSame($user->id, $auditoria->actor_user_id);
        $this->assertSame('Ana Paciente', $auditoria->actor_nombre_snapshot);
        $this->assertSame('ana@example.com', $auditoria->actor_email_snapshot);
        $this->assertSame(
            $actualizado->getMorphClass(),
            $auditoria->auditable_type
        );
        $this->assertSame(
            $actualizado->id,
            $auditoria->auditable_id
        );
        $this->assertNull($auditoria->valores_anteriores);
        $this->assertNull($auditoria->valores_nuevos);
    }

    public function test_rolls_back_password_change_when_audit_persistence_fails(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('ContrasenaAnterior123!'),
        ]);

        $actor = User::factory()->create([
            'name' => 'Actor',
            'apellido' => 'Eliminado',
            'email' => 'actor.eliminado@example.com',
        ]);

        $this->actingAs($actor);

        DB::table('users')
            ->where('id', $actor->id)
            ->delete();

        try {
            app(CambiarContrasenaAction::class)->ejecutar(
                $user,
                'ContrasenaNueva456!'
            );

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $user->refresh();

            $this->assertTrue(
                Hash::check('ContrasenaAnterior123!', $user->password)
            );
            $this->assertFalse(
                Hash::check('ContrasenaNueva456!', $user->password)
            );
            $this->assertDatabaseCount('auditorias', 0);
        }
    }
}
