<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CambiarEstadoJurisdiccionAction;
use App\Models\Auditoria;
use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use App\Models\User;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CambiarEstadoJurisdiccionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactivates_active_jurisdiccion_and_audits_change(): void
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

        $actualizada = app(
            CambiarEstadoJurisdiccionAction::class
        )->ejecutar($jurisdiccion, false);

        $this->assertFalse((bool) $actualizada->activo);

        $this->assertDatabaseHas('jurisdicciones', [
            'id' => $jurisdiccion->id,
            'activo' => false,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'jurisdiccion_inactivada',
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

    public function test_activates_inactive_jurisdiccion_and_audits_change(): void
    {
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => false,
        ]);

        $actualizada = app(
            CambiarEstadoJurisdiccionAction::class
        )->ejecutar($jurisdiccion, true);

        $this->assertTrue((bool) $actualizada->activo);

        $this->assertDatabaseHas('jurisdicciones', [
            'id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'jurisdiccion_reactivada',
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

    public function test_inactivating_an_already_inactive_jurisdiccion_is_idempotent(): void
    {
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => false,
        ]);

        $actualizada = app(CambiarEstadoJurisdiccionAction::class)
            ->ejecutar($jurisdiccion, false);

        $this->assertFalse((bool) $actualizada->activo);
        $this->assertFalse($actualizada->wasChanged());
        $this->assertDatabaseHas('jurisdicciones', [
            'id' => $jurisdiccion->id,
            'activo' => false,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_reactivating_an_already_active_jurisdiccion_is_idempotent(): void
    {
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $actualizada = app(CambiarEstadoJurisdiccionAction::class)
            ->ejecutar($jurisdiccion, true);

        $this->assertTrue((bool) $actualizada->activo);
        $this->assertFalse($actualizada->wasChanged());
        $this->assertDatabaseHas('jurisdicciones', [
            'id' => $jurisdiccion->id,
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

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $this->actingAs($actor);

        app(CambiarEstadoJurisdiccionAction::class)
            ->ejecutar($jurisdiccion, false);

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
            app(CambiarEstadoJurisdiccionAction::class)
                ->ejecutar($jurisdiccion, false);

            $this->fail(
                'Se esperaba una QueryException al persistir la auditoría.'
            );
        } catch (QueryException) {
            $this->assertDatabaseHas('jurisdicciones', [
                'id' => $jurisdiccion->id,
                'activo' => true,
            ]);

            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public function test_active_colegio_blocks_inactivation_without_state_change_or_audit(): void
    {
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones', 'slug' => 'misiones', 'activo' => true,
        ]);
        ColegioEmisor::create([
            'nombre' => 'Colegio de Misiones',
            'sigla' => 'CM',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        try {
            app(CambiarEstadoJurisdiccionAction::class)->ejecutar($jurisdiccion, false);
            $this->fail('Se esperaba el rechazo por colegios activos.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'No se puede inactivar la jurisdicción porque tiene colegios emisores activos asociados.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('jurisdicciones', ['id' => $jurisdiccion->id, 'activo' => true]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_inactive_colegio_does_not_block_inactivation(): void
    {
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones', 'slug' => 'misiones', 'activo' => true,
        ]);
        ColegioEmisor::create([
            'nombre' => 'Colegio de Misiones',
            'sigla' => 'CM',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => false,
        ]);

        $actualizada = app(CambiarEstadoJurisdiccionAction::class)->ejecutar($jurisdiccion, false);

        $this->assertFalse((bool) $actualizada->activo);
        $this->assertDatabaseHas('jurisdicciones', ['id' => $jurisdiccion->id, 'activo' => false]);
        $this->assertSame('jurisdiccion_inactivada', Auditoria::query()->sole()->evento);
    }

    public function test_already_inactive_jurisdiccion_remains_idempotent_even_with_active_colegio(): void
    {
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones', 'slug' => 'misiones', 'activo' => false,
        ]);
        ColegioEmisor::create([
            'nombre' => 'Colegio histórico',
            'sigla' => 'CH',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $actualizada = app(CambiarEstadoJurisdiccionAction::class)->ejecutar($jurisdiccion, false);

        $this->assertFalse((bool) $actualizada->activo);
        $this->assertFalse($actualizada->wasChanged());
        $this->assertDatabaseCount('auditorias', 0);
    }
}
