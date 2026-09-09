<?php

namespace Tests\Feature\Actions\Usuarios;

use App\Actions\Usuarios\RestablecerContrasenaAction;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RestablecerContrasenaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resets_password_and_audits_without_actor_or_sensitive_snapshots(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Paciente',
            'email' => 'ana@example.com',
            'password' => Hash::make('ContrasenaAnterior123!'),
            'remember_token' => 'token-anterior',
        ]);

        $actualizado = app(
            RestablecerContrasenaAction::class
        )->ejecutar(
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
        $this->assertNotSame(
            'token-anterior',
            $actualizado->remember_token
        );
        $this->assertSame(
            60,
            strlen((string) $actualizado->remember_token)
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'contrasena_restablecida',
            $auditoria->evento
        );
        $this->assertSame('Cuenta', $auditoria->modulo);
        $this->assertNull($auditoria->actor_user_id);
        $this->assertNull($auditoria->actor_nombre_snapshot);
        $this->assertNull($auditoria->actor_email_snapshot);
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

    public function test_rolls_back_password_and_remember_token_when_audit_persistence_fails(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('ContrasenaAnterior123!'),
            'remember_token' => 'token-anterior',
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
            app(RestablecerContrasenaAction::class)->ejecutar(
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
            $this->assertSame(
                'token-anterior',
                $user->remember_token
            );
            $this->assertDatabaseCount('auditorias', 0);
        }
    }
}
