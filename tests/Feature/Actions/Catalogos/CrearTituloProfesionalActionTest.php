<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CrearTituloProfesionalAction;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrearTituloProfesionalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_active_titulo_generates_slug_and_audits_creation(): void
    {
        $actor = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Administradora',
            'email' => 'ana.admin@example.com',
        ]);

        $this->actingAs($actor);

        $titulo = app(CrearTituloProfesionalAction::class)->ejecutar([
            'nombre' => 'Licenciado en Kinesiología',
        ]);

        $this->assertTrue($titulo->exists);

        $this->assertDatabaseHas('titulos_profesionales', [
            'id' => $titulo->id,
            'nombre' => 'Licenciado en Kinesiología',
            'slug' => 'licenciado-en-kinesiologia',
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'titulo_profesional_creado',
            $auditoria->evento
        );
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame(
            $titulo->getMorphClass(),
            $auditoria->auditable_type
        );
        $this->assertSame($titulo->id, $auditoria->auditable_id);
        $this->assertNull($auditoria->valores_anteriores);

        $this->assertSame(
            [
                'nombre' => 'Licenciado en Kinesiología',
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

    public function test_rolls_back_titulo_when_audit_persistence_fails(): void
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
            app(CrearTituloProfesionalAction::class)->ejecutar([
                'nombre' => 'Terapista Físico',
            ]);

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseMissing('titulos_profesionales', [
                'nombre' => 'Terapista Físico',
                'slug' => 'terapista-fisico',
            ]);

            $this->assertDatabaseCount('auditorias', 0);
        }
    }
}
