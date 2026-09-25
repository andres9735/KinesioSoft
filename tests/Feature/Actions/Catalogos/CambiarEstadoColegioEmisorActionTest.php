<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CambiarEstadoColegioEmisorAction;
use App\Models\Auditoria;
use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use App\Models\User;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CambiarEstadoColegioEmisorActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactivates_active_colegio_and_audits_change(): void
    {
        $actor = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Administradora',
            'email' => 'ana.admin@example.com',
        ]);

        $colegio = $this->crearColegio(activo: true);

        $this->actingAs($actor);

        $actualizado = app(
            CambiarEstadoColegioEmisorAction::class
        )->ejecutar($colegio, false);

        $this->assertFalse((bool) $actualizado->activo);

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'activo' => false,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'colegio_emisor_inactivado',
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
            ['activo' => true],
            $auditoria->valores_anteriores
        );
        $this->assertSame(
            ['activo' => false],
            $auditoria->valores_nuevos
        );
    }

    public function test_activates_inactive_colegio_and_audits_change(): void
    {
        $colegio = $this->crearColegio(activo: false);

        $actualizado = app(
            CambiarEstadoColegioEmisorAction::class
        )->ejecutar($colegio, true);

        $this->assertTrue((bool) $actualizado->activo);

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'colegio_emisor_reactivado',
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

    public function test_inactivating_an_already_inactive_colegio_is_idempotent(): void
    {
        $colegio = $this->crearColegio(activo: false);

        $actualizado = app(CambiarEstadoColegioEmisorAction::class)
            ->ejecutar($colegio, false);

        $this->assertFalse((bool) $actualizado->activo);
        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'activo' => false,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_reactivating_an_already_active_colegio_is_idempotent(): void
    {
        $colegio = $this->crearColegio(activo: true);

        $actualizado = app(CambiarEstadoColegioEmisorAction::class)
            ->ejecutar($colegio, true);

        $this->assertTrue((bool) $actualizado->activo);
        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_reactivation_rejects_inactive_jurisdiction_without_change_or_audit(): void
    {
        $colegio = $this->crearColegio(activo: false);
        $colegio->jurisdiccion->update(['activo' => false]);

        try {
            app(CambiarEstadoColegioEmisorAction::class)->ejecutar($colegio, true);
            $this->fail('Se esperaba el rechazo de la reactivación.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'No se puede reactivar el colegio emisor porque su jurisdicción está inactiva.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'jurisdiccion_id' => $colegio->jurisdiccion_id,
            'activo' => false,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_reactivation_of_already_active_historical_colegio_is_idempotent(): void
    {
        $colegio = $this->crearColegio(activo: true);
        $colegio->jurisdiccion->update(['activo' => false]);

        $actualizado = app(CambiarEstadoColegioEmisorAction::class)
            ->ejecutar($colegio, true);

        $this->assertTrue((bool) $actualizado->activo);
        $this->assertFalse($actualizado->wasChanged());
        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
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

        $colegio = $this->crearColegio(activo: true);

        $this->actingAs($actor);

        app(CambiarEstadoColegioEmisorAction::class)
            ->ejecutar($colegio, false);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame($actor->id, $auditoria->actor_user_id);
        $this->assertSame(
            'Carlos Administrador',
            $auditoria->actor_nombre_snapshot
        );
        $this->assertSame(
            'carlos.admin@example.com',
            $auditoria->actor_email_snapshot
        );
    }

    public function test_rolls_back_state_change_when_audit_persistence_fails(): void
    {
        $colegio = $this->crearColegio(activo: true);

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
            app(CambiarEstadoColegioEmisorAction::class)
                ->ejecutar($colegio, false);

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseHas('colegios_emisores', [
                'id' => $colegio->id,
                'activo' => true,
            ]);

            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function crearColegio(bool $activo): ColegioEmisor
    {
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        return ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => $activo,
        ]);
    }
}
