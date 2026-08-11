<?php

namespace Tests\Feature\Actions\Kinesiologas;

use App\Actions\Kinesiologas\CambiarEstadoMatriculaProfesionalAction;
use App\Exceptions\Kinesiologas\NoSePuedeInactivarUltimaMatriculaActivaException;
use App\Models\Auditoria;
use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CambiarEstadoMatriculaProfesionalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactivates_matricula_when_another_active_exists_and_audits_change(): void
    {
        $contexto = $this->contexto();

        $segunda = MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $contexto['kinesiologa']->id,
            'nro_matricula_profesional' => '0955',
            'jurisdiccion_id' => $contexto['jurisdiccion2']->id,
            'colegio_emisor_id' => $contexto['colegio2']->id,
            'activa' => true,
        ]);

        $this->actingAs($contexto['user']);

        $actualizada = app(
            CambiarEstadoMatriculaProfesionalAction::class
        )->ejecutar($contexto['matricula']);

        $this->assertFalse((bool) $actualizada->activa);

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $contexto['matricula']->id,
                'activa' => false,
            ]
        );

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $segunda->id,
                'activa' => true,
            ]
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'matricula_inactivada',
            $auditoria->evento
        );
        $this->assertSame(
            'Matrículas profesionales',
            $auditoria->modulo
        );
        $this->assertSame(
            $actualizada->getMorphClass(),
            $auditoria->auditable_type
        );
        $this->assertSame(
            $actualizada->id,
            $auditoria->auditable_id
        );
        $this->assertSame(
            ['activa' => true],
            $auditoria->valores_anteriores
        );
        $this->assertSame(
            ['activa' => false],
            $auditoria->valores_nuevos
        );
    }

    public function test_reactivates_inactive_matricula_and_audits_change(): void
    {
        $contexto = $this->contexto();

        $contexto['matricula']->update([
            'activa' => false,
        ]);

        $this->actingAs($contexto['user']);

        $actualizada = app(
            CambiarEstadoMatriculaProfesionalAction::class
        )->ejecutar($contexto['matricula']);

        $this->assertTrue((bool) $actualizada->activa);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'matricula_activada',
            $auditoria->evento
        );
        $this->assertSame(
            ['activa' => false],
            $auditoria->valores_anteriores
        );
        $this->assertSame(
            ['activa' => true],
            $auditoria->valores_nuevos
        );
    }

    public function test_prevents_inactivating_last_active_matricula_without_auditing(): void
    {
        $contexto = $this->contexto();

        try {
            app(CambiarEstadoMatriculaProfesionalAction::class)
                ->ejecutar($contexto['matricula']);

            $this->fail(
                'Se esperaba que se impidiera inactivar la última matrícula activa.'
            );
        } catch (NoSePuedeInactivarUltimaMatriculaActivaException $e) {
            $this->assertSame(
                'No puede inactivar su última matrícula activa.',
                $e->getMessage()
            );

            $this->assertDatabaseHas(
                'matriculas_profesionales_kinesiologia',
                [
                    'id' => $contexto['matricula']->id,
                    'activa' => true,
                ]
            );

            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public function test_uses_authenticated_kinesiologa_as_audit_actor(): void
    {
        $contexto = $this->contexto();

        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $contexto['kinesiologa']->id,
            'nro_matricula_profesional' => '0955',
            'jurisdiccion_id' => $contexto['jurisdiccion2']->id,
            'colegio_emisor_id' => $contexto['colegio2']->id,
            'activa' => true,
        ]);

        $this->actingAs($contexto['user']);

        app(CambiarEstadoMatriculaProfesionalAction::class)
            ->ejecutar($contexto['matricula']);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            $contexto['user']->id,
            $auditoria->actor_user_id
        );
        $this->assertSame(
            'Silvia Prueba',
            $auditoria->actor_nombre
        );
        $this->assertSame(
            'silvia.prueba@example.com',
            $auditoria->actor_email
        );
    }

    public function test_rolls_back_state_change_when_audit_persistence_fails(): void
    {
        $contexto = $this->contexto();

        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $contexto['kinesiologa']->id,
            'nro_matricula_profesional' => '0955',
            'jurisdiccion_id' => $contexto['jurisdiccion2']->id,
            'colegio_emisor_id' => $contexto['colegio2']->id,
            'activa' => true,
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
            app(CambiarEstadoMatriculaProfesionalAction::class)
                ->ejecutar($contexto['matricula']);

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseHas(
                'matriculas_profesionales_kinesiologia',
                [
                    'id' => $contexto['matricula']->id,
                    'activa' => true,
                ]
            );

            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    /**
     * @return array{
     *     user: User,
     *     kinesiologa: Kinesiologa,
     *     matricula: MatriculaProfesionalKinesiologia,
     *     jurisdiccion1: Jurisdiccion,
     *     jurisdiccion2: Jurisdiccion,
     *     colegio1: ColegioEmisor,
     *     colegio2: ColegioEmisor
     * }
     */
    private function contexto(): array
    {
        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $jurisdiccion1 = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $jurisdiccion2 = Jurisdiccion::create([
            'nombre' => 'Corrientes',
            'slug' => 'corrientes',
            'activo' => true,
        ]);

        $colegio1 = ColegioEmisor::create([
            'nombre' =>
            'Colegio de Kinesiólogos y Fisioterapeutas de Misiones',
            'sigla' => 'CKFM',
            'jurisdiccion_id' => $jurisdiccion1->id,
            'activo' => true,
        ]);

        $colegio2 = ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Corrientes',
            'sigla' => 'CKC',
            'jurisdiccion_id' => $jurisdiccion2->id,
            'activo' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Silvia',
            'apellido' => 'Prueba',
            'email' => 'silvia.prueba@example.com',
        ]);

        $kinesiologa = Kinesiologa::create([
            'user_id' => $user->id,
            'titulo_profesional_id' => $titulo->id,
            'consultorio_id' => null,
        ]);

        $matricula = MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $kinesiologa->id,
            'nro_matricula_profesional' => '0755',
            'jurisdiccion_id' => $jurisdiccion1->id,
            'colegio_emisor_id' => $colegio1->id,
            'activa' => true,
        ]);

        return compact(
            'user',
            'kinesiologa',
            'matricula',
            'jurisdiccion1',
            'jurisdiccion2',
            'colegio1',
            'colegio2'
        );
    }
}
