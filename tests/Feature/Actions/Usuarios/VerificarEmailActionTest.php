<?php

namespace Tests\Feature\Actions\Usuarios;

use App\Actions\Usuarios\VerificarEmailAction;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VerificarEmailActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifies_email_and_audits_semantic_event(): void
    {
        $user = User::factory()->unverified()->create([
            'name' => 'Ana',
            'apellido' => 'Paciente',
            'email' => 'ana@example.com',
        ]);

        $this->actingAs($user);

        $resultado = app(
            VerificarEmailAction::class
        )->ejecutar($user);

        $this->assertTrue($resultado);

        $user->refresh();

        $this->assertTrue($user->hasVerifiedEmail());

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'email_verificado',
            $auditoria->evento
        );
        $this->assertSame('Cuenta', $auditoria->modulo);

        $this->assertSame(
            $user->id,
            $auditoria->actor_user_id
        );
        $this->assertSame(
            'Ana Paciente',
            $auditoria->actor_nombre
        );
        $this->assertSame(
            'ana@example.com',
            $auditoria->actor_email
        );

        $this->assertSame(
            $user->getMorphClass(),
            $auditoria->auditable_type
        );
        $this->assertSame(
            $user->id,
            $auditoria->auditable_id
        );

        $this->assertNull(
            $auditoria->valores_anteriores
        );
        $this->assertNull(
            $auditoria->valores_nuevos
        );
    }

    public function test_does_not_audit_when_email_is_already_verified(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $resultado = app(
            VerificarEmailAction::class
        )->ejecutar($user);

        $this->assertFalse($resultado);
        $this->assertTrue($user->hasVerifiedEmail());

        $this->assertDatabaseCount(
            'auditorias',
            0
        );
    }

    public function test_rolls_back_email_verification_when_audit_persistence_fails(): void
    {
        $user = User::factory()->unverified()->create();

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
            app(VerificarEmailAction::class)
                ->ejecutar($user);

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $user->refresh();

            $this->assertFalse(
                $user->hasVerifiedEmail()
            );

            $this->assertDatabaseCount(
                'auditorias',
                0
            );
        }
    }
}
