<?php

namespace Tests\Feature\Actions\Agenda;

use App\Actions\Agenda\AnularConfiguracionAgendaFuturaAction;
use App\Exceptions\Agenda\ConfiguracionAgendaNoElegibleException;
use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class AnularConfiguracionAgendaFuturaActionTest extends AgendaActionTestCase
{
    public function test_anula_b_vinculada_sin_c_y_reabre_a_preservando_datos(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $a = $this->configuracion($kinesiologa, '2026-09-01', '2026-10-31', 30);
        $b = $this->configuracion($kinesiologa, '2026-11-01', null, 60);
        $idFranjaA = $a->franjasHorarias()->value('id');
        $idFranjaB = $b->franjasHorarias()->value('id');

        $anulada = app(AnularConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $b, [
            'snapshot' => $this->snapshot($b),
            'motivo_anulacion' => '  Cambio de planificación  ',
            'anulado_at' => '2000-01-01',
        ]);

        $this->assertSame('2026-10-06 10:30:00', $anulada->anulado_at->format('Y-m-d H:i:s'));
        $this->assertSame('Cambio de planificación', $anulada->motivo_anulacion);
        $this->assertNull($a->fresh()->vigencia_hasta);
        $this->assertSame(30, $a->fresh()->duracion_turno_minutos);
        $this->assertSame($idFranjaA, $a->franjasHorarias()->value('id'));
        $this->assertSame($idFranjaB, $b->franjasHorarias()->value('id'));
        $this->assertEvento('configuracion_futura_anulada');
        $this->assertEvento('configuracion_vigencia_reconciliada');
    }

    public function test_anula_b_vinculada_con_c_y_extiende_a_hasta_c_menos_un_dia(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $a = $this->configuracion($kinesiologa, '2026-09-01', '2026-10-31');
        $b = $this->configuracion($kinesiologa, '2026-11-01', '2026-11-30');
        $c = $this->configuracion($kinesiologa, '2026-12-01');

        app(AnularConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $b, [
            'snapshot' => $this->snapshot($b),
            'motivo_anulacion' => 'Ya no será necesaria',
        ]);

        $this->assertSame('2026-11-30', $a->fresh()->vigencia_hasta->toDateString());
        $this->assertSame('2026-12-01', $c->fresh()->vigencia_desde->toDateString());
        $this->assertNull($c->fresh()->vigencia_hasta);
        $this->assertNotNull($b->fresh()->anulado_at);
    }

    public function test_preserva_hueco_y_tambien_permite_anular_sin_predecesora(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $a = $this->configuracion($kinesiologa, '2026-09-01', '2026-09-30');
        $b = $this->configuracion($kinesiologa, '2026-11-01');

        app(AnularConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $b, [
            'snapshot' => $this->snapshot($b),
            'motivo_anulacion' => 'Conservar hueco',
        ]);
        $this->assertSame('2026-09-30', $a->fresh()->vigencia_hasta->toDateString());
        $this->assertEvento('configuracion_vigencia_reconciliada', 0);

        [$otroActor, $otraKinesiologa] = $this->actor();
        $sinA = $this->configuracion($otraKinesiologa, '2026-12-01');
        app(AnularConfiguracionAgendaFuturaAction::class)->ejecutar($otroActor, $sinA, [
            'snapshot' => $this->snapshot($sinA),
            'motivo_anulacion' => 'Sin predecesora',
        ]);
        $this->assertNotNull($sinA->fresh()->anulado_at);
    }

    public function test_rechaza_iniciada_anulada_ajena_snapshot_stale_y_motivo_invalido(): void
    {
        [$actorHistorica, $kinesiologaHistorica] = $this->actor();
        $historica = $this->configuracion($kinesiologaHistorica, '2026-09-01', '2026-09-30');
        [$actorHoy, $kinesiologaHoy] = $this->actor();
        $hoy = $this->configuracion($kinesiologaHoy, '2026-10-06');
        [$actorAnulada, $kinesiologaAnulada] = $this->actor();
        $anulada = $this->configuracion($kinesiologaAnulada, '2026-11-01', null, 45, '2026-10-01 10:00:00');
        [$actorAjena] = $this->actor();
        [, $otraKinesiologa] = $this->actor();
        $ajena = $this->configuracion($otraKinesiologa, '2026-12-01');
        $action = app(AnularConfiguracionAgendaFuturaAction::class);

        foreach ([
            [$actorHistorica, $historica],
            [$actorHoy, $hoy],
            [$actorAnulada, $anulada],
            [$actorAjena, $ajena],
        ] as [$actorCaso, $target]) {
            try {
                $action->ejecutar($actorCaso, $target, [
                    'snapshot' => $this->snapshot($target),
                    'motivo_anulacion' => 'Motivo válido',
                ]);
                $this->fail('Se esperaba configuración no elegible.');
            } catch (ConfiguracionAgendaNoElegibleException) {
                $this->assertTrue(true);
            }
        }

        [$actor, $kinesiologa] = $this->actor();
        $futura = $this->configuracion($kinesiologa, '2027-01-01');
        foreach (['   ', str_repeat('x', 501)] as $motivo) {
            try {
                $action->ejecutar($actor, $futura, [
                    'snapshot' => $this->snapshot($futura),
                    'motivo_anulacion' => $motivo,
                ]);
                $this->fail('Se esperaba motivo inválido.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $token = $this->snapshot($futura);
        $futura->franjasHorarias()->firstOrFail()->update(['hora_fin' => '13:00:00']);
        $this->expectException(SnapshotAgendaDesactualizadoException::class);
        $action->ejecutar($actor, $futura, [
            'snapshot' => $token,
            'motivo_anulacion' => 'Snapshot viejo',
        ]);
    }

    public function test_segunda_anulacion_es_error_no_sobrescribe_motivo_ni_audita_de_nuevo(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $b = $this->configuracion($kinesiologa, '2026-11-01');
        $token = $this->snapshot($b);
        $action = app(AnularConfiguracionAgendaFuturaAction::class);
        $action->ejecutar($actor, $b, [
            'snapshot' => $token,
            'motivo_anulacion' => 'Motivo original',
        ]);

        try {
            $action->ejecutar($actor, $b, [
                'snapshot' => $token,
                'motivo_anulacion' => 'Motivo reemplazado',
            ]);
            $this->fail('Se esperaba rechazo de la segunda anulación.');
        } catch (ConfiguracionAgendaNoElegibleException) {
            $this->assertSame('Motivo original', $b->fresh()->motivo_anulacion);
            $this->assertEvento('configuracion_futura_anulada', 1);
        }
    }

    public function test_fallo_de_auditoria_revierte_anulacion_y_reconciliacion(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $a = $this->configuracion($kinesiologa, '2026-09-01', '2026-10-31');
        $b = $this->configuracion($kinesiologa, '2026-11-01');
        \DB::statement("CREATE TRIGGER fallar_auditoria BEFORE INSERT ON auditorias BEGIN SELECT RAISE(ABORT, 'fallo auditoria'); END");

        try {
            app(AnularConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $b, [
                'snapshot' => $this->snapshot($b),
                'motivo_anulacion' => 'Debe hacer rollback',
            ]);
            $this->fail('Se esperaba fallo de auditoría.');
        } catch (QueryException) {
            $this->assertSame('2026-10-31', $a->fresh()->vigencia_hasta->toDateString());
            $this->assertNull($b->fresh()->anulado_at);
            $this->assertNull($b->fresh()->motivo_anulacion);
        }
    }
}
