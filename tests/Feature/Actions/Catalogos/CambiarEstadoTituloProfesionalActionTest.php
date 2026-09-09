<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CambiarEstadoTituloProfesionalAction;
use App\Models\Auditoria;
use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CambiarEstadoTituloProfesionalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactivates_active_titulo_and_audits_change(): void
    {
        $actor = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Administradora',
            'email' => 'ana.admin@example.com',
        ]);

        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $this->actingAs($actor);

        $actualizado = app(
            CambiarEstadoTituloProfesionalAction::class
        )->ejecutar($titulo);

        $this->assertFalse((bool) $actualizado->activo);

        $this->assertDatabaseHas('titulos_profesionales', [
            'id' => $titulo->id,
            'activo' => false,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'titulo_profesional_inactivado',
            $auditoria->evento
        );
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame(
            $actualizado->getMorphClass(),
            $auditoria->auditable_type
        );
        $this->assertSame(
            $actualizado->id,
            $auditoria->auditable_id
        );
        $this->assertSame(
            ['activo' => true],
            $auditoria->valores_anteriores
        );
        $this->assertSame(
            ['activo' => false],
            $auditoria->valores_nuevos
        );
    }

    public function test_activates_inactive_titulo_and_audits_change(): void
    {
        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => false,
        ]);

        $actualizado = app(
            CambiarEstadoTituloProfesionalAction::class
        )->ejecutar($titulo);

        $this->assertTrue((bool) $actualizado->activo);

        $this->assertDatabaseHas('titulos_profesionales', [
            'id' => $titulo->id,
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'titulo_profesional_activado',
            $auditoria->evento
        );
        $this->assertSame(
            ['activo' => false],
            $auditoria->valores_anteriores
        );
        $this->assertSame(
            ['activo' => true],
            $auditoria->valores_nuevos
        );
    }

    public function test_uses_authenticated_user_as_audit_actor(): void
    {
        $actor = User::factory()->create([
            'name' => 'Carlos',
            'apellido' => 'Administrador',
            'email' => 'carlos.admin@example.com',
        ]);

        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $this->actingAs($actor);

        app(CambiarEstadoTituloProfesionalAction::class)
            ->ejecutar($titulo);

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

    public function test_rolls_back_state_change_when_audit_persistence_fails(): void
    {
        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
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
            app(CambiarEstadoTituloProfesionalAction::class)
                ->ejecutar($titulo);

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseHas('titulos_profesionales', [
                'id' => $titulo->id,
                'activo' => true,
            ]);

            $this->assertDatabaseCount('auditorias', 0);
        }
    }
}
