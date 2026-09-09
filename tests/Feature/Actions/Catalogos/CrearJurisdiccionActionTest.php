<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CrearJurisdiccionAction;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrearJurisdiccionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_active_jurisdiccion_generates_slug_and_audits_creation(): void
    {
        $actor = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Administradora',
            'email' => 'ana.admin@example.com',
        ]);

        $this->actingAs($actor);

        $jurisdiccion = app(CrearJurisdiccionAction::class)->ejecutar([
            'nombre' => 'Provincia de Misiones',
        ]);

        $this->assertTrue($jurisdiccion->exists);

        $this->assertDatabaseHas('jurisdicciones', [
            'id' => $jurisdiccion->id,
            'nombre' => 'Provincia de Misiones',
            'slug' => 'provincia-de-misiones',
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame('jurisdiccion_creada', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame(
            $jurisdiccion->getMorphClass(),
            $auditoria->auditable_type
        );
        $this->assertSame(
            $jurisdiccion->id,
            $auditoria->auditable_id
        );
        $this->assertNull($auditoria->valores_anteriores);

        $this->assertSame(
            [
                'nombre' => 'Provincia de Misiones',
                'activo' => true,
            ],
            $auditoria->valores_nuevos
        );

        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertSame(
            'Ana Administradora',
            $auditoria->actor_nombre_snapshot
        );
        $this->assertSame(
            'ana.admin@example.com',
            $auditoria->actor_email_snapshot
        );
    }

    public function test_rolls_back_jurisdiccion_when_audit_persistence_fails(): void
    {
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
            app(CrearJurisdiccionAction::class)->ejecutar([
                'nombre' => 'Corrientes',
            ]);

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseMissing('jurisdicciones', [
                'nombre' => 'Corrientes',
                'slug' => 'corrientes',
            ]);

            $this->assertDatabaseCount('auditorias', 0);
        }
    }
}
