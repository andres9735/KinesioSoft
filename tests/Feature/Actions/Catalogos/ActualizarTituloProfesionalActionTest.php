<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\ActualizarTituloProfesionalAction;
use App\Models\Auditoria;
use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActualizarTituloProfesionalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_nombre_and_slug_and_audits_only_nombre(): void
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
            ActualizarTituloProfesionalAction::class
        )->ejecutar(
            $titulo,
            ['nombre' => 'Licenciado en Kinesiología']
        );

        $this->assertSame(
            'Licenciado en Kinesiología',
            $actualizado->nombre
        );
        $this->assertSame(
            'licenciado-en-kinesiologia',
            $actualizado->slug
        );
        $this->assertTrue((bool) $actualizado->activo);

        $this->assertDatabaseHas('titulos_profesionales', [
            'id' => $titulo->id,
            'nombre' => 'Licenciado en Kinesiología',
            'slug' => 'licenciado-en-kinesiologia',
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'titulo_profesional_actualizado',
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
            ['nombre' => 'Kinesiólogo'],
            $auditoria->valores_anteriores
        );
        $this->assertSame(
            ['nombre' => 'Licenciado en Kinesiología'],
            $auditoria->valores_nuevos
        );
    }

    public function test_does_not_update_or_audit_when_nombre_has_no_changes(): void
    {
        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $actualizado = app(
            ActualizarTituloProfesionalAction::class
        )->ejecutar(
            $titulo,
            ['nombre' => 'Kinesiólogo']
        );

        $this->assertSame($titulo->id, $actualizado->id);
        $this->assertSame('Kinesiólogo', $actualizado->nombre);
        $this->assertSame('kinesiologo', $actualizado->slug);
        $this->assertTrue((bool) $actualizado->activo);

        $this->assertDatabaseCount('auditorias', 0);
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

        app(ActualizarTituloProfesionalAction::class)->ejecutar(
            $titulo,
            ['nombre' => 'Licenciado en Kinesiología']
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertSame(
            'Carlos Administrador',
            $auditoria->actor_nombre
        );
        $this->assertSame(
            'carlos.admin@example.com',
            $auditoria->actor_email
        );
    }

    public function test_rolls_back_update_when_audit_persistence_fails(): void
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
            app(ActualizarTituloProfesionalAction::class)->ejecutar(
                $titulo,
                ['nombre' => 'Licenciado en Kinesiología']
            );

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseHas('titulos_profesionales', [
                'id' => $titulo->id,
                'nombre' => 'Kinesiólogo',
                'slug' => 'kinesiologo',
                'activo' => true,
            ]);

            $this->assertDatabaseCount('auditorias', 0);
        }
    }
}
