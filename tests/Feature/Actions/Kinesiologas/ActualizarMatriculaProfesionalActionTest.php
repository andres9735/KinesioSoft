<?php

namespace Tests\Feature\Actions\Kinesiologas;

use App\Actions\Kinesiologas\ActualizarMatriculaProfesionalAction;
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

class ActualizarMatriculaProfesionalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_only_license_number_preserves_immutable_fields_and_audits_change(): void
    {
        $contexto = $this->contexto();

        $this->actingAs($contexto['user']);

        /*
         * Se incluyen deliberadamente valores diferentes para
         * jurisdicción y colegio a fin de comprobar que la Action
         * no puede modificar esos datos aunque fueran recibidos
         * directamente por error desde otro punto de la aplicación.
         */
        $actualizada = app(
            ActualizarMatriculaProfesionalAction::class
        )->ejecutar(
            $contexto['matricula'],
            [
                'nro_matricula_profesional' => '0999',
                'jurisdiccion_id' => $contexto['jurisdiccion2']->id,
                'colegio_emisor_id' => $contexto['colegio2']->id,
            ]
        );

        $actualizada->refresh();

        $this->assertSame(
            '0999',
            $actualizada->nro_matricula_profesional
        );

        /*
         * Jurisdicción y Colegio emisor son inmutables.
         */
        $this->assertSame(
            $contexto['jurisdiccion1']->id,
            $actualizada->jurisdiccion_id
        );

        $this->assertSame(
            $contexto['colegio1']->id,
            $actualizada->colegio_emisor_id
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'matricula_actualizada',
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
            [
                'nro_matricula_profesional' => '0755',
            ],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            [
                'nro_matricula_profesional' => '0999',
            ],
            $auditoria->valores_nuevos
        );
    }

    public function test_audits_only_the_field_that_really_changed(): void
    {
        $contexto = $this->contexto();

        app(ActualizarMatriculaProfesionalAction::class)->ejecutar(
            $contexto['matricula'],
            [
                'nro_matricula_profesional' => '0777',
                'jurisdiccion_id' => $contexto['jurisdiccion1']->id,
                'colegio_emisor_id' => $contexto['colegio1']->id,
            ]
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            ['nro_matricula_profesional' => '0755'],
            $auditoria->valores_anteriores
        );

        $this->assertSame(
            ['nro_matricula_profesional' => '0777'],
            $auditoria->valores_nuevos
        );
    }

    public function test_does_not_audit_when_matricula_has_no_changes(): void
    {
        $contexto = $this->contexto();

        $actualizada = app(
            ActualizarMatriculaProfesionalAction::class
        )->ejecutar(
            $contexto['matricula'],
            [
                'nro_matricula_profesional' => '0755',
                'jurisdiccion_id' => $contexto['jurisdiccion1']->id,
                'colegio_emisor_id' => $contexto['colegio1']->id,
            ]
        );

        $this->assertSame(
            $contexto['matricula']->id,
            $actualizada->id
        );
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_uses_authenticated_kinesiologa_as_audit_actor(): void
    {
        $contexto = $this->contexto();

        $this->actingAs($contexto['user']);

        app(ActualizarMatriculaProfesionalAction::class)->ejecutar(
            $contexto['matricula'],
            [
                'nro_matricula_profesional' => '0777',
                'jurisdiccion_id' => $contexto['jurisdiccion1']->id,
                'colegio_emisor_id' => $contexto['colegio1']->id,
            ]
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            $contexto['user']->id,
            $auditoria->actor_user_id
        );
        $this->assertSame(
            'Silvia Prueba',
            $auditoria->actor_nombre_snapshot
        );
        $this->assertSame(
            'silvia.prueba@example.com',
            $auditoria->actor_email_snapshot
        );
    }

    public function test_rolls_back_update_when_audit_persistence_fails(): void
    {
        $contexto = $this->contexto();

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
            app(ActualizarMatriculaProfesionalAction::class)->ejecutar(
                $contexto['matricula'],
                [
                    'nro_matricula_profesional' => '0999',
                    'jurisdiccion_id' => $contexto['jurisdiccion2']->id,
                    'colegio_emisor_id' => $contexto['colegio2']->id,
                ]
            );

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseHas(
                'matriculas_profesionales_kinesiologia',
                [
                    'id' => $contexto['matricula']->id,
                    'nro_matricula_profesional' => '0755',
                    'jurisdiccion_id' => $contexto['jurisdiccion1']->id,
                    'colegio_emisor_id' => $contexto['colegio1']->id,
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
