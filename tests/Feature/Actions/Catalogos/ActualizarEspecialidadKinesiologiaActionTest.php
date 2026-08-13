<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\ActualizarEspecialidadKinesiologiaAction;
use App\Models\Auditoria;
use App\Models\EspecialidadKinesiologia;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActualizarEspecialidadKinesiologiaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_name_and_slug_and_audits_only_name(): void
    {
        $especialidad = $this->crearEspecialidad();

        $actualizada = app(
            ActualizarEspecialidadKinesiologiaAction::class
        )->ejecutar(
            $especialidad,
            [
                'nombre' => '  Kinesiología   Respiratoria  ',
            ]
        );

        $this->assertSame(
            'Kinesiología Respiratoria',
            $actualizada->nombre
        );
        $this->assertSame(
            'kinesiologia-respiratoria',
            $actualizada->slug
        );

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'id' => $especialidad->id,
            'nombre' => 'Kinesiología Respiratoria',
            'slug' => 'kinesiologia-respiratoria',
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'especialidad_kinesiologia_actualizada',
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
            ['nombre' => 'Kinesiología Deportiva'],
            $auditoria->valores_anteriores
        );
        $this->assertSame(
            ['nombre' => 'Kinesiología Respiratoria'],
            $auditoria->valores_nuevos
        );
        $this->assertArrayNotHasKey(
            'slug',
            $auditoria->valores_anteriores
        );
        $this->assertArrayNotHasKey(
            'slug',
            $auditoria->valores_nuevos
        );
    }

    public function test_does_not_update_or_audit_when_normalized_name_has_no_changes(): void
    {
        $especialidad = $this->crearEspecialidad();

        $actualizada = app(
            ActualizarEspecialidadKinesiologiaAction::class
        )->ejecutar(
            $especialidad,
            [
                'nombre' => '  Kinesiología   Deportiva  ',
            ]
        );

        $this->assertSame(
            'Kinesiología Deportiva',
            $actualizada->nombre
        );
        $this->assertSame(
            'kinesiologia-deportiva',
            $actualizada->slug
        );

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'id' => $especialidad->id,
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_uses_authenticated_user_as_audit_actor(): void
    {
        $actor = User::factory()->create([
            'name' => 'Carlos',
            'apellido' => 'Administrador',
            'email' => 'carlos.admin@example.com',
        ]);

        $especialidad = $this->crearEspecialidad();

        $this->actingAs($actor);

        app(ActualizarEspecialidadKinesiologiaAction::class)
            ->ejecutar(
                $especialidad,
                [
                    'nombre' => 'Kinesiología Respiratoria',
                ]
            );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            $actor->id,
            $auditoria->actor_user_id
        );
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
        $especialidad = $this->crearEspecialidad();

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
            app(ActualizarEspecialidadKinesiologiaAction::class)
                ->ejecutar(
                    $especialidad,
                    [
                        'nombre' => 'Kinesiología Respiratoria',
                    ]
                );

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseHas(
                'especialidades_kinesiologia',
                [
                    'id' => $especialidad->id,
                    'nombre' => 'Kinesiología Deportiva',
                    'slug' => 'kinesiologia-deportiva',
                    'activo' => true,
                ]
            );

            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function crearEspecialidad(): EspecialidadKinesiologia
    {
        return EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);
    }
}
