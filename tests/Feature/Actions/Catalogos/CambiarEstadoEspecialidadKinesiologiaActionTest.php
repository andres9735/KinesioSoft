<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CambiarEstadoEspecialidadKinesiologiaAction;
use App\Models\Auditoria;
use App\Models\EspecialidadKinesiologia;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CambiarEstadoEspecialidadKinesiologiaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactivates_active_specialty_and_audits_change(): void
    {
        $actor = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Administradora',
            'email' => 'ana.admin@example.com',
        ]);

        $especialidad = $this->crearEspecialidad(activo: true);

        $this->actingAs($actor);

        $actualizada = app(
            CambiarEstadoEspecialidadKinesiologiaAction::class
        )->ejecutar($especialidad);

        $this->assertFalse((bool) $actualizada->activo);

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'id' => $especialidad->id,
            'activo' => false,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'especialidad_kinesiologia_inactivada',
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
            ['activo' => true],
            $auditoria->valores_anteriores
        );
        $this->assertSame(
            ['activo' => false],
            $auditoria->valores_nuevos
        );
    }

    public function test_activates_inactive_specialty_and_audits_change(): void
    {
        $especialidad = $this->crearEspecialidad(activo: false);

        $actualizada = app(
            CambiarEstadoEspecialidadKinesiologiaAction::class
        )->ejecutar($especialidad);

        $this->assertTrue((bool) $actualizada->activo);

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'id' => $especialidad->id,
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'especialidad_kinesiologia_activada',
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

        $especialidad = $this->crearEspecialidad(activo: true);

        $this->actingAs($actor);

        app(CambiarEstadoEspecialidadKinesiologiaAction::class)
            ->ejecutar($especialidad);

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

    public function test_rolls_back_state_change_when_audit_persistence_fails(): void
    {
        $especialidad = $this->crearEspecialidad(activo: true);

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
            app(CambiarEstadoEspecialidadKinesiologiaAction::class)
                ->ejecutar($especialidad);

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseHas('especialidades_kinesiologia', [
                'id' => $especialidad->id,
                'activo' => true,
            ]);

            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function crearEspecialidad(
        bool $activo
    ): EspecialidadKinesiologia {
        return EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => $activo,
        ]);
    }
}
