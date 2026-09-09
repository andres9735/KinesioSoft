<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\ActualizarJurisdiccionAction;
use App\Models\Auditoria;
use App\Models\Jurisdiccion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActualizarJurisdiccionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_nombre_and_slug_and_audits_only_nombre(): void
    {
        $actor = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Administradora',
            'email' => 'ana.admin@example.com',
        ]);

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $this->actingAs($actor);

        $actualizada = app(
            ActualizarJurisdiccionAction::class
        )->ejecutar(
            $jurisdiccion,
            [
                'nombre' => 'Provincia de Misiones',
            ]
        );

        $this->assertSame($jurisdiccion->id, $actualizada->id);

        $this->assertDatabaseHas('jurisdicciones', [
            'id' => $jurisdiccion->id,
            'nombre' => 'Provincia de Misiones',
            'slug' => 'provincia-de-misiones',
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'jurisdiccion_actualizada',
            $auditoria->evento
        );
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame(
            $actualizada->getMorphClass(),
            $auditoria->auditable_type
        );
        $this->assertSame(
            $actualizada->id,
            $auditoria->auditable_id
        );

        $this->assertSame(
            [
                'nombre' => 'Misiones',
            ],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            [
                'nombre' => 'Provincia de Misiones',
            ],
            $auditoria->valores_nuevos
        );
    }

    public function test_does_not_update_or_audit_when_nombre_has_no_changes(): void
    {
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $actualizada = app(
            ActualizarJurisdiccionAction::class
        )->ejecutar(
            $jurisdiccion,
            [
                'nombre' => 'Misiones',
            ]
        );

        $this->assertSame($jurisdiccion->id, $actualizada->id);
        $this->assertSame('Misiones', $actualizada->nombre);
        $this->assertSame('misiones', $actualizada->slug);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_uses_authenticated_user_as_audit_actor(): void
    {
        $actor = User::factory()->create([
            'name' => 'Carlos',
            'apellido' => 'Administrador',
            'email' => 'carlos.admin@example.com',
        ]);

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $this->actingAs($actor);

        app(ActualizarJurisdiccionAction::class)->ejecutar(
            $jurisdiccion,
            [
                'nombre' => 'Provincia de Misiones',
            ]
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertSame(
            'Carlos Administrador',
            $auditoria->actor_nombre_snapshot
        );
        $this->assertSame(
            'carlos.admin@example.com',
            $auditoria->actor_email_snapshot
        );
    }

    public function test_rolls_back_update_when_audit_persistence_fails(): void
    {
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
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
            app(ActualizarJurisdiccionAction::class)->ejecutar(
                $jurisdiccion,
                [
                    'nombre' => 'Provincia de Misiones',
                ]
            );

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseHas('jurisdicciones', [
                'id' => $jurisdiccion->id,
                'nombre' => 'Misiones',
                'slug' => 'misiones',
                'activo' => true,
            ]);

            $this->assertDatabaseMissing('jurisdicciones', [
                'id' => $jurisdiccion->id,
                'nombre' => 'Provincia de Misiones',
            ]);

            $this->assertDatabaseCount('auditorias', 0);
        }
    }
}
