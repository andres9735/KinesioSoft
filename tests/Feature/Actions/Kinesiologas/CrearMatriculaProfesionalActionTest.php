<?php

namespace Tests\Feature\Actions\Kinesiologas;

use App\Actions\Kinesiologas\CrearMatriculaProfesionalAction;
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

class CrearMatriculaProfesionalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_active_matricula_and_audits_creation(): void
    {
        [$user, $kinesiologa, $jurisdiccion, $colegio] =
            $this->contexto();

        $this->actingAs($user);

        $matricula = app(CrearMatriculaProfesionalAction::class)->ejecutar(
            $kinesiologa,
            [
                'nro_matricula_profesional' => '0955',
                'jurisdiccion_id' => $jurisdiccion->id,
                'colegio_emisor_id' => $colegio->id,
            ]
        );

        $this->assertTrue($matricula->exists);

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'kinesiologa_id' => $kinesiologa->id,
                'nro_matricula_profesional' => '0955',
                'jurisdiccion_id' => $jurisdiccion->id,
                'colegio_emisor_id' => $colegio->id,
                'activa' => true,
            ]
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame('matricula_creada', $auditoria->evento);
        $this->assertSame(
            'Matrículas profesionales',
            $auditoria->modulo
        );
        $this->assertSame(
            $matricula->getMorphClass(),
            $auditoria->auditable_type
        );
        $this->assertSame($matricula->id, $auditoria->auditable_id);
        $this->assertNull($auditoria->valores_anteriores);

        $this->assertSame(
            [
                'nro_matricula_profesional' => '0955',
                'jurisdiccion_id' => $jurisdiccion->id,
                'colegio_emisor_id' => $colegio->id,
                'activa' => true,
            ],
            $auditoria->valores_nuevos
        );

        $this->assertSame($user->id, $auditoria->actor_user_id);
        $this->assertSame(
            trim($user->name . ' ' . $user->apellido),
            $auditoria->actor_nombre_snapshot
        );
        $this->assertSame($user->email, $auditoria->actor_email_snapshot);
    }

    public function test_rolls_back_matricula_when_audit_persistence_fails(): void
    {
        [, $kinesiologa, $jurisdiccion, $colegio] =
            $this->contexto();

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
            app(CrearMatriculaProfesionalAction::class)->ejecutar(
                $kinesiologa,
                [
                    'nro_matricula_profesional' => '0999',
                    'jurisdiccion_id' => $jurisdiccion->id,
                    'colegio_emisor_id' => $colegio->id,
                ]
            );

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseMissing(
                'matriculas_profesionales_kinesiologia',
                [
                    'kinesiologa_id' => $kinesiologa->id,
                    'nro_matricula_profesional' => '0999',
                ]
            );

            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    /**
     * @return array{
     *     0: User,
     *     1: Kinesiologa,
     *     2: Jurisdiccion,
     *     3: ColegioEmisor
     * }
     */
    private function contexto(): array
    {
        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $colegio = ColegioEmisor::create([
            'nombre' =>
            'Colegio de Kinesiólogos y Fisioterapeutas de Misiones',
            'sigla' => 'CKFM',
            'jurisdiccion_id' => $jurisdiccion->id,
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

        return [$user, $kinesiologa, $jurisdiccion, $colegio];
    }
}
