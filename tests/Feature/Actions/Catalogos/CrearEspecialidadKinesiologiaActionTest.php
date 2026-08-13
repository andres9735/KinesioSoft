<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CrearEspecialidadKinesiologiaAction;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrearEspecialidadKinesiologiaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_active_specialty_normalizes_name_generates_slug_and_audits_creation(): void
    {
        $actor = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Administradora',
            'email' => 'ana.admin@example.com',
        ]);

        $this->actingAs($actor);

        $especialidad = app(
            CrearEspecialidadKinesiologiaAction::class
        )->ejecutar([
            'nombre' => '  Kinesiología   Deportiva  ',
        ]);

        $this->assertSame(
            'Kinesiología Deportiva',
            $especialidad->nombre
        );
        $this->assertSame(
            'kinesiologia-deportiva',
            $especialidad->slug
        );
        $this->assertTrue((bool) $especialidad->activo);

        $this->assertDatabaseHas('especialidades_kinesiologia', [
            'id' => $especialidad->id,
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'especialidad_kinesiologia_creada',
            $auditoria->evento
        );
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame(
            $especialidad->getMorphClass(),
            $auditoria->auditable_type
        );
        $this->assertSame(
            $especialidad->id,
            $auditoria->auditable_id
        );
        $this->assertNull($auditoria->valores_anteriores);
        $this->assertSame(
            [
                'nombre' => 'Kinesiología Deportiva',
                'activo' => true,
            ],
            $auditoria->valores_nuevos
        );
        $this->assertArrayNotHasKey(
            'slug',
            $auditoria->valores_nuevos
        );
        $this->assertSame(
            $actor->id,
            $auditoria->actor_user_id
        );
        $this->assertSame(
            'Ana Administradora',
            $auditoria->actor_nombre
        );
        $this->assertSame(
            'ana.admin@example.com',
            $auditoria->actor_email
        );
    }

    public function test_rolls_back_specialty_when_audit_persistence_fails(): void
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
            app(CrearEspecialidadKinesiologiaAction::class)
                ->ejecutar([
                    'nombre' => 'Kinesiología Deportiva',
                ]);

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseCount(
                'especialidades_kinesiologia',
                0
            );
            $this->assertDatabaseCount('auditorias', 0);
        }
    }
}
