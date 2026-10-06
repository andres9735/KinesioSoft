<?php

namespace Tests\Feature\Actions\Agenda;

use App\Actions\Agenda\CambiarDuracionAgendaAction;
use App\Exceptions\Agenda\ConfiguracionAgendaNoElegibleException;
use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use App\Models\ConfiguracionAgenda;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class CambiarDuracionAgendaActionTest extends AgendaActionTestCase
{
    public function test_programa_cambio_sin_c_y_cierra_origen_sin_alterar_duracion_o_matriz(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $origen = $this->configuracion($kinesiologa, '2026-09-01');
        $idFranjaOrigen = $origen->franjasHorarias()->value('id');

        $nueva = app(CambiarDuracionAgendaAction::class)->ejecutar($actor, [
            'snapshot' => $this->snapshot($origen),
            'vigencia_desde' => '2026-10-07',
            'duracion_turno_minutos' => 60,
            'franjas' => $this->matriz('09:00', '14:00'),
        ]);

        $this->assertSame('2026-10-06', $origen->fresh()->vigencia_hasta->toDateString());
        $this->assertSame(45, $origen->fresh()->duracion_turno_minutos);
        $this->assertSame($idFranjaOrigen, $origen->franjasHorarias()->value('id'));
        $this->assertNull($nueva->vigencia_hasta);
        $this->assertSame(60, $nueva->duracion_turno_minutos);
        $this->assertEvento('duracion_agenda_programada');
        $this->assertEvento('configuracion_vigencia_reconciliada');
    }

    public function test_con_c_deriva_fin_nuevo_y_no_modifica_sucesora(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $origen = $this->configuracion($kinesiologa, '2026-09-01', '2026-11-30');
        $c = $this->configuracion($kinesiologa, '2026-12-01');

        $nueva = app(CambiarDuracionAgendaAction::class)->ejecutar($actor, [
            'snapshot' => $this->snapshot($origen),
            'vigencia_desde' => '2026-11-01',
            'duracion_turno_minutos' => 30,
            'franjas' => $this->matriz(),
        ]);

        $this->assertSame('2026-10-31', $origen->fresh()->vigencia_hasta->toDateString());
        $this->assertSame('2026-11-30', $nueva->vigencia_hasta->toDateString());
        $this->assertSame('2026-12-01', $c->fresh()->vigencia_desde->toDateString());
        $this->assertNull($c->fresh()->vigencia_hasta);
    }

    public function test_no_audita_reconciliacion_si_origen_ya_terminaba_el_dia_anterior(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $origen = $this->configuracion($kinesiologa, '2026-09-01', '2026-10-06');

        app(CambiarDuracionAgendaAction::class)->ejecutar($actor, [
            'snapshot' => $this->snapshot($origen),
            'vigencia_desde' => '2026-10-07',
            'duracion_turno_minutos' => 60,
            'franjas' => $this->matriz(),
        ]);

        $this->assertEvento('duracion_agenda_programada');
        $this->assertEvento('configuracion_vigencia_reconciliada', 0);
    }

    public function test_rechaza_hoy_pasado_ausencia_de_origen_y_duracion_o_matriz_invalidas(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $origen = $this->configuracion($kinesiologa, '2026-10-01');
        $action = app(CambiarDuracionAgendaAction::class);
        $base = [
            'snapshot' => $this->snapshot($origen),
            'vigencia_desde' => '2026-10-07',
            'duracion_turno_minutos' => 60,
            'franjas' => $this->matriz(),
        ];

        foreach ([
            [array_replace($base, ['vigencia_desde' => '2026-10-06']), ConfiguracionAgendaNoElegibleException::class],
            [array_replace($base, ['vigencia_desde' => '2026-10-05']), ConfiguracionAgendaNoElegibleException::class],
            [array_replace($base, ['duracion_turno_minutos' => 14]), InvalidArgumentException::class],
            [array_replace($base, ['duracion_turno_minutos' => 180, 'franjas' => $this->matriz('08:00', '09:00')]), InvalidArgumentException::class],
        ] as [$datos, $exception]) {
            try {
                $action->ejecutar($actor, $datos);
                $this->fail('Se esperaba rechazo del cambio de duración.');
            } catch (\Throwable $error) {
                $this->assertInstanceOf($exception, $error);
            }
        }

        [$sinAgenda] = $this->actor();
        $this->expectException(ConfiguracionAgendaNoElegibleException::class);
        $action->ejecutar($sinAgenda, $base);
    }

    public function test_rechaza_snapshot_stale_incluso_si_solo_cambia_una_franja(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $origen = $this->configuracion($kinesiologa, '2026-09-01');
        $token = $this->snapshot($origen);
        $origen->franjasHorarias()->firstOrFail()->update(['hora_fin' => '13:00:00']);

        $this->expectException(SnapshotAgendaDesactualizadoException::class);
        app(CambiarDuracionAgendaAction::class)->ejecutar($actor, [
            'snapshot' => $token,
            'vigencia_desde' => '2026-10-07',
            'duracion_turno_minutos' => 60,
            'franjas' => $this->matriz(),
        ]);
    }

    public function test_fallo_de_auditoria_revierte_origen_nueva_configuracion_y_franjas(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $origen = $this->configuracion($kinesiologa, '2026-09-01');
        \DB::statement("CREATE TRIGGER fallar_auditoria BEFORE INSERT ON auditorias BEGIN SELECT RAISE(ABORT, 'fallo auditoria'); END");

        try {
            app(CambiarDuracionAgendaAction::class)->ejecutar($actor, [
                'snapshot' => $this->snapshot($origen),
                'vigencia_desde' => '2026-10-07',
                'duracion_turno_minutos' => 60,
                'franjas' => $this->matriz(),
            ]);
            $this->fail('Se esperaba fallo de auditoría.');
        } catch (QueryException) {
            $this->assertNull($origen->fresh()->vigencia_hasta);
            $this->assertSame(1, ConfiguracionAgenda::query()->count());
        }
    }
}
