<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CrearColegioEmisorAction;
use App\Models\Auditoria;
use App\Models\Jurisdiccion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrearColegioEmisorActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_active_colegio_normalizes_data_and_audits_creation(): void
    {
        $actor = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Administradora',
            'email' => 'ana.admin@example.com',
        ]);

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $this->actingAs($actor);

        $colegio = app(CrearColegioEmisorAction::class)->ejecutar([
            'nombre' => '  Colegio   de Kinesiólogos   de Misiones  ',
            'sigla' => '  coki   mi  ',
            'jurisdiccion_id' => $jurisdiccion->id,
        ]);

        $this->assertSame(
            'Colegio de Kinesiólogos de Misiones',
            $colegio->nombre
        );
        $this->assertSame('COKI MI', $colegio->sigla);
        $this->assertSame(
            $jurisdiccion->id,
            $colegio->jurisdiccion_id
        );
        $this->assertTrue((bool) $colegio->activo);

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKI MI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'colegio_emisor_creado',
            $auditoria->evento
        );
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame(
            $colegio->getMorphClass(),
            $auditoria->auditable_type
        );
        $this->assertSame(
            $colegio->id,
            $auditoria->auditable_id
        );
        $this->assertNull($auditoria->valores_anteriores);
        $this->assertSame(
            [
                'nombre' => 'Colegio de Kinesiólogos de Misiones',
                'sigla' => 'COKI MI',
                'jurisdiccion_id' => $jurisdiccion->id,
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

    public function test_rolls_back_colegio_when_audit_persistence_fails(): void
    {
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
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
            app(CrearColegioEmisorAction::class)->ejecutar([
                'nombre' => 'Colegio de Kinesiólogos de Misiones',
                'sigla' => 'COKIFIMI',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseCount('colegios_emisores', 0);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }
}
