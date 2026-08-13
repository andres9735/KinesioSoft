<?php

namespace Tests\Feature\Actions\Usuarios;

use App\Actions\Usuarios\ActualizarCuentaPropiaAction;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActualizarCuentaPropiaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_name_and_email_and_audits_changed_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Paciente',
            'email' => 'ana@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        $actualizado = app(
            ActualizarCuentaPropiaAction::class
        )->ejecutar(
            $user,
            [
                'name' => 'Ana María',
                'email' => 'ana.nueva@example.com',
            ]
        );

        $actualizado->refresh();

        $this->assertSame(
            'Ana María',
            $actualizado->name
        );

        $this->assertSame(
            'ana.nueva@example.com',
            $actualizado->email
        );

        $this->assertNull(
            $actualizado->email_verified_at
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'cuenta_actualizada',
            $auditoria->evento
        );

        $this->assertSame(
            'Cuenta',
            $auditoria->modulo
        );

        $this->assertSame(
            $user->id,
            $auditoria->actor_user_id
        );

        $this->assertSame(
            $actualizado->getMorphClass(),
            $auditoria->auditable_type
        );

        $this->assertSame(
            $actualizado->id,
            $auditoria->auditable_id
        );

        $this->assertSame(
            [
                'name' => 'Ana',
                'email' => 'ana@example.com',
            ],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            [
                'name' => 'Ana María',
                'email' => 'ana.nueva@example.com',
            ],
            $auditoria->valores_nuevos
        );
    }

    public function test_audits_only_field_that_really_changed(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        app(ActualizarCuentaPropiaAction::class)
            ->ejecutar(
                $user,
                [
                    'name' => 'Ana Actualizada',
                    'email' => 'ana@example.com',
                ]
            );

        $user->refresh();

        $this->assertSame(
            'Ana Actualizada',
            $user->name
        );

        $this->assertTrue(
            $user->hasVerifiedEmail()
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            ['name' => 'Ana'],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            ['name' => 'Ana Actualizada'],
            $auditoria->valores_nuevos
        );
    }

    public function test_does_not_update_or_audit_when_account_has_no_changes(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
        ]);

        $this->actingAs($user);

        $resultado = app(
            ActualizarCuentaPropiaAction::class
        )->ejecutar(
            $user,
            [
                'name' => 'Ana',
                'email' => 'ana@example.com',
            ]
        );

        $this->assertSame(
            $user->id,
            $resultado->id
        );

        $this->assertDatabaseCount(
            'auditorias',
            0
        );
    }

    public function test_rolls_back_account_update_when_audit_persistence_fails(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'email_verified_at' => now(),
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
            app(ActualizarCuentaPropiaAction::class)
                ->ejecutar(
                    $user,
                    [
                        'name' => 'Ana Modificada',
                        'email' => 'ana.modificada@example.com',
                    ]
                );

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $user->refresh();

            $this->assertSame(
                'Ana',
                $user->name
            );

            $this->assertSame(
                'ana@example.com',
                $user->email
            );

            $this->assertTrue(
                $user->hasVerifiedEmail()
            );

            $this->assertDatabaseCount(
                'auditorias',
                0
            );
        }
    }
}
