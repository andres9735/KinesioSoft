<?php

namespace Tests\Feature\Actions\Agenda;

use App\Actions\Agenda\ActualizarConfiguracionAgendaFuturaAction;
use App\Exceptions\Agenda\ConfiguracionAgendaNoElegibleException;
use App\Exceptions\Agenda\ConflictoTemporalAgendaException;
use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use Illuminate\Database\QueryException;

class ActualizarConfiguracionAgendaFuturaActionTest extends AgendaActionTestCase
{
    public function test_actualiza_periodo_duracion_y_matriz_y_audita(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $target = $this->configuracion($kinesiologa, '2026-11-01', '2026-11-30');
        $idsAnteriores = $target->franjasHorarias()->pluck('id')->all();

        $actualizada = app(ActualizarConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $target, [
            'snapshot' => $this->snapshot($target),
            'vigencia_desde' => '2026-11-02',
            'vigencia_hasta' => '2026-12-15',
            'duracion_turno_minutos' => 60,
            'franjas' => $this->matriz('09:00', '14:00'),
        ]);

        $this->assertSame('2026-11-02', $actualizada->vigencia_desde->toDateString());
        $this->assertSame(60, $actualizada->duracion_turno_minutos);
        $this->assertNotSame($idsAnteriores, $actualizada->franjasHorarias->pluck('id')->all());
        $this->assertEvento('configuracion_futura_actualizada');
    }

    public function test_update_semanticamente_identico_es_no_op_y_conserva_ids_y_updated_at(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $target = $this->configuracion($kinesiologa, '2026-11-01', '2026-11-30');
        $idFranja = $target->franjasHorarias()->value('id');
        $updatedAt = $target->updated_at->format('Y-m-d H:i:s.u');

        $resultado = app(ActualizarConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $target, [
            'snapshot' => $this->snapshot($target),
            'vigencia_desde' => '2026-11-01',
            'vigencia_hasta' => '2026-11-30',
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz(),
        ]);

        $this->assertSame($target->id, $resultado->id);
        $this->assertSame($idFranja, $target->franjasHorarias()->value('id'));
        $this->assertSame($updatedAt, $target->fresh()->updated_at->format('Y-m-d H:i:s.u'));
        $this->assertSame(0, \DB::table('auditorias')->count());
    }

    public function test_cambio_solo_de_matriz_reemplaza_franjas_y_matriz_igual_las_conserva(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $target = $this->configuracion($kinesiologa, '2026-11-01');
        $idAnterior = $target->franjasHorarias()->value('id');

        app(ActualizarConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $target, [
            'snapshot' => $this->snapshot($target),
            'vigencia_desde' => '2026-11-01',
            'vigencia_hasta' => null,
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz('10:00', '14:00'),
        ]);

        $idNuevo = $target->franjasHorarias()->value('id');
        $this->assertNotSame($idAnterior, $idNuevo);

        app(ActualizarConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $target->fresh(), [
            'snapshot' => $this->snapshot($target->fresh()),
            'vigencia_desde' => '2026-11-01',
            'vigencia_hasta' => null,
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz('10:00:00', '14:00:00'),
        ]);

        $this->assertSame($idNuevo, $target->franjasHorarias()->value('id'));
        $this->assertEvento('configuracion_futura_actualizada', 1);
    }

    public function test_rechaza_target_iniciada_anulada_ajena_y_snapshot_stale(): void
    {
        [$actorHoy, $kinesiologaHoy] = $this->actor();
        $hoy = $this->configuracion($kinesiologaHoy, '2026-10-06');
        [$actorAnulada, $kinesiologaAnulada] = $this->actor();
        $anulada = $this->configuracion($kinesiologaAnulada, '2026-11-01', null, 45, '2026-10-01 10:00:00');
        [$actorAjena] = $this->actor();
        [, $otraKinesiologa] = $this->actor();
        $ajena = $this->configuracion($otraKinesiologa, '2026-12-01');
        $action = app(ActualizarConfiguracionAgendaFuturaAction::class);

        foreach ([[$actorHoy, $hoy], [$actorAnulada, $anulada], [$actorAjena, $ajena]] as [$actorCaso, $target]) {
            try {
                $action->ejecutar($actorCaso, $target, [
                    'snapshot' => $this->snapshot($target),
                    'vigencia_desde' => '2027-01-01',
                    'vigencia_hasta' => null,
                    'duracion_turno_minutos' => 45,
                    'franjas' => $this->matriz(),
                ]);
                $this->fail('Se esperaba target no elegible.');
            } catch (ConfiguracionAgendaNoElegibleException) {
                $this->assertTrue(true);
            }
        }

        [$actor, $kinesiologa] = $this->actor();
        $futura = $this->configuracion($kinesiologa, '2027-02-01');
        $token = $this->snapshot($futura);
        $futura->franjasHorarias()->firstOrFail()->update(['hora_fin' => '13:00:00']);

        $this->expectException(SnapshotAgendaDesactualizadoException::class);
        $action->ejecutar($actor, $futura, [
            'snapshot' => $token,
            'vigencia_desde' => '2027-02-01',
            'vigencia_hasta' => null,
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz(),
        ]);
    }

    public function test_preserva_hueco_original_y_reconcilia_encadenamiento_al_mover_b(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $aHueco = $this->configuracion($kinesiologa, '2026-09-01', '2026-09-30');
        $bHueco = $this->configuracion($kinesiologa, '2026-11-01', '2026-11-30');

        app(ActualizarConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $bHueco, [
            'snapshot' => $this->snapshot($bHueco),
            'vigencia_desde' => '2026-11-10',
            'vigencia_hasta' => '2026-11-30',
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz(),
        ]);
        $this->assertSame('2026-09-30', $aHueco->fresh()->vigencia_hasta->toDateString());

        [$otroActor, $otraKinesiologa] = $this->actor();
        $a = $this->configuracion($otraKinesiologa, '2026-09-01', '2026-10-31');
        $b = $this->configuracion($otraKinesiologa, '2026-11-01');
        app(ActualizarConfiguracionAgendaFuturaAction::class)->ejecutar($otroActor, $b, [
            'snapshot' => $this->snapshot($b),
            'vigencia_desde' => '2026-11-10',
            'vigencia_hasta' => null,
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz(),
        ]);

        $this->assertSame('2026-11-09', $a->fresh()->vigencia_hasta->toDateString());
        $this->assertEvento('configuracion_vigencia_reconciliada');
    }

    public function test_con_sucesora_exige_fin_compatible_y_no_modifica_c(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $a = $this->configuracion($kinesiologa, '2026-09-01', '2026-10-31');
        $b = $this->configuracion($kinesiologa, '2026-11-01', '2026-11-30');
        $c = $this->configuracion($kinesiologa, '2026-12-01');

        try {
            app(ActualizarConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $b, [
                'snapshot' => $this->snapshot($b),
                'vigencia_desde' => '2026-11-01',
                'vigencia_hasta' => null,
                'duracion_turno_minutos' => 45,
                'franjas' => $this->matriz(),
            ]);
            $this->fail('Se esperaba conflicto con C.');
        } catch (ConflictoTemporalAgendaException) {
            $this->assertSame('2026-10-31', $a->fresh()->vigencia_hasta->toDateString());
            $this->assertNull($c->fresh()->vigencia_hasta);
        }

        $actualizada = app(ActualizarConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $b, [
            'snapshot' => $this->snapshot($b),
            'vigencia_desde' => '2026-11-01',
            'vigencia_hasta' => '2026-11-30',
            'duracion_turno_minutos' => 60,
            'franjas' => $this->matriz(),
        ]);
        $this->assertSame('2026-11-30', $actualizada->vigencia_hasta->toDateString());
        $this->assertNull($c->fresh()->vigencia_hasta);
    }

    public function test_al_mover_b_despues_de_c_calcula_vecinos_finales_y_audita_cada_uno_una_vez(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $a = $this->configuracion($kinesiologa, '2026-09-01', '2026-10-31');
        $b = $this->configuracion($kinesiologa, '2026-11-01', '2026-11-30');
        $c = $this->configuracion($kinesiologa, '2026-12-01');

        app(ActualizarConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $b, [
            'snapshot' => $this->snapshot($b),
            'vigencia_desde' => '2026-12-15',
            'vigencia_hasta' => null,
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz(),
        ]);

        $this->assertSame('2026-11-30', $a->fresh()->vigencia_hasta->toDateString());
        $this->assertSame('2026-12-14', $c->fresh()->vigencia_hasta->toDateString());
        $this->assertSame('2026-12-15', $b->fresh()->vigencia_desde->toDateString());
        $this->assertEvento('configuracion_vigencia_reconciliada', 2);
    }

    public function test_fallo_de_auditoria_revierte_target_matriz_y_vecinas(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $a = $this->configuracion($kinesiologa, '2026-09-01', '2026-10-31');
        $b = $this->configuracion($kinesiologa, '2026-11-01');
        $idFranja = $b->franjasHorarias()->value('id');
        \DB::statement("CREATE TRIGGER fallar_auditoria BEFORE INSERT ON auditorias BEGIN SELECT RAISE(ABORT, 'fallo auditoria'); END");

        try {
            app(ActualizarConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $b, [
                'snapshot' => $this->snapshot($b),
                'vigencia_desde' => '2026-11-10',
                'vigencia_hasta' => null,
                'duracion_turno_minutos' => 60,
                'franjas' => $this->matriz('09:00', '14:00'),
            ]);
            $this->fail('Se esperaba fallo de auditoría.');
        } catch (QueryException) {
            $this->assertSame('2026-10-31', $a->fresh()->vigencia_hasta->toDateString());
            $this->assertSame('2026-11-01', $b->fresh()->vigencia_desde->toDateString());
            $this->assertSame($idFranja, $b->franjasHorarias()->value('id'));
        }
    }
}
