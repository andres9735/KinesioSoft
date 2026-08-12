<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\ActualizarColegioEmisorAction;
use App\Models\Auditoria;
use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActualizarColegioEmisorActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_normalizes_and_audits_changed_fields(): void
    {
        $actor = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Administradora',
            'email' => 'ana.admin@example.com',
        ]);

        $misiones = $this->crearJurisdiccion(
            'Misiones',
            'misiones'
        );
        $corrientes = $this->crearJurisdiccion(
            'Corrientes',
            'corrientes'
        );

        $colegio = $this->crearColegio($misiones);

        $this->actingAs($actor);

        $actualizado = app(
            ActualizarColegioEmisorAction::class
        )->ejecutar(
            $colegio,
            [
                'nombre' => '  Colegio   Profesional   de Kinesiología  ',
                'sigla' => '  cpk   cor  ',
                'jurisdiccion_id' => $corrientes->id,
            ]
        );

        $this->assertSame(
            'Colegio Profesional de Kinesiología',
            $actualizado->nombre
        );
        $this->assertSame('CPK COR', $actualizado->sigla);
        $this->assertSame(
            $corrientes->id,
            $actualizado->jurisdiccion_id
        );
        $this->assertTrue((bool) $actualizado->activo);

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'nombre' => 'Colegio Profesional de Kinesiología',
            'sigla' => 'CPK COR',
            'jurisdiccion_id' => $corrientes->id,
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'colegio_emisor_actualizado',
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
            [
                'nombre' => 'Colegio de Kinesiólogos de Misiones',
                'sigla' => 'COKIFIMI',
                'jurisdiccion_id' => $misiones->id,
            ],
            $auditoria->valores_anteriores
        );
        $this->assertSame(
            [
                'nombre' => 'Colegio Profesional de Kinesiología',
                'sigla' => 'CPK COR',
                'jurisdiccion_id' => $corrientes->id,
            ],
            $auditoria->valores_nuevos
        );
    }

    public function test_audits_only_the_field_that_really_changed(): void
    {
        $jurisdiccion = $this->crearJurisdiccion(
            'Misiones',
            'misiones'
        );

        $colegio = $this->crearColegio($jurisdiccion);

        app(ActualizarColegioEmisorAction::class)->ejecutar(
            $colegio,
            [
                'nombre' => $colegio->nombre,
                'sigla' => '  coki   mnes  ',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            ['sigla' => 'COKIFIMI'],
            $auditoria->valores_anteriores
        );
        $this->assertSame(
            ['sigla' => 'COKI MNES'],
            $auditoria->valores_nuevos
        );
    }

    public function test_does_not_update_or_audit_when_normalized_data_has_no_changes(): void
    {
        $jurisdiccion = $this->crearJurisdiccion(
            'Misiones',
            'misiones'
        );

        $colegio = $this->crearColegio($jurisdiccion);

        $actualizado = app(
            ActualizarColegioEmisorAction::class
        )->ejecutar(
            $colegio,
            [
                'nombre' => '  Colegio   de Kinesiólogos   de Misiones  ',
                'sigla' => '  cokifimi  ',
                'jurisdiccion_id' => (string) $jurisdiccion->id,
            ]
        );

        $this->assertSame($colegio->id, $actualizado->id);
        $this->assertSame(
            'Colegio de Kinesiólogos de Misiones',
            $actualizado->nombre
        );
        $this->assertSame('COKIFIMI', $actualizado->sigla);
        $this->assertSame(
            $jurisdiccion->id,
            $actualizado->jurisdiccion_id
        );
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

        $jurisdiccion = $this->crearJurisdiccion(
            'Misiones',
            'misiones'
        );

        $colegio = $this->crearColegio($jurisdiccion);

        $this->actingAs($actor);

        app(ActualizarColegioEmisorAction::class)->ejecutar(
            $colegio,
            [
                'nombre' => 'Colegio Profesional de Kinesiología',
                'sigla' => $colegio->sigla,
                'jurisdiccion_id' => $jurisdiccion->id,
            ]
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
        $jurisdiccion = $this->crearJurisdiccion(
            'Misiones',
            'misiones'
        );

        $colegio = $this->crearColegio($jurisdiccion);

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
            app(ActualizarColegioEmisorAction::class)->ejecutar(
                $colegio,
                [
                    'nombre' => 'Colegio Profesional de Kinesiología',
                    'sigla' => 'CPK',
                    'jurisdiccion_id' => $jurisdiccion->id,
                ]
            );

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseHas('colegios_emisores', [
                'id' => $colegio->id,
                'nombre' => 'Colegio de Kinesiólogos de Misiones',
                'sigla' => 'COKIFIMI',
                'jurisdiccion_id' => $jurisdiccion->id,
                'activo' => true,
            ]);

            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function crearJurisdiccion(
        string $nombre,
        string $slug
    ): Jurisdiccion {
        return Jurisdiccion::create([
            'nombre' => $nombre,
            'slug' => $slug,
            'activo' => true,
        ]);
    }

    private function crearColegio(
        Jurisdiccion $jurisdiccion
    ): ColegioEmisor {
        return ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);
    }
}
