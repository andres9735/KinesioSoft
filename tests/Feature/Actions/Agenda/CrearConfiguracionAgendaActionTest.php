<?php

namespace Tests\Feature\Actions\Agenda;

use App\Actions\Agenda\CrearConfiguracionAgendaAction;
use App\Exceptions\Agenda\ConfiguracionAgendaNoElegibleException;
use App\Exceptions\Agenda\ConflictoTemporalAgendaException;
use App\Models\ConfiguracionAgenda;
use App\Models\User;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class CrearConfiguracionAgendaActionTest extends AgendaActionTestCase
{
    public function test_crea_primera_configuracion_hoy_atomicamente_y_audita(): void
    {
        [$actor, $kinesiologa] = $this->actor();

        $creada = app(CrearConfiguracionAgendaAction::class)->ejecutar($actor, [
            'vigencia_desde' => '2026-10-06',
            'vigencia_hasta' => null,
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz(),
            'kinesiologa_id' => 999,
        ]);

        $this->assertSame($kinesiologa->id, $creada->kinesiologa_id);
        $this->assertCount(1, $creada->franjasHorarias);
        $this->assertEvento('configuracion_agenda_creada');
    }

    public function test_crea_primera_configuracion_futura(): void
    {
        [$actor] = $this->actor();
        $creada = app(CrearConfiguracionAgendaAction::class)->ejecutar($actor, [
            'vigencia_desde' => '2026-10-20',
            'vigencia_hasta' => '2026-12-31',
            'duracion_turno_minutos' => '60',
            'franjas' => $this->matriz('09:00', '13:00'),
        ]);

        $this->assertSame('2026-10-20', $creada->vigencia_desde->toDateString());
        $this->assertSame(60, $creada->duracion_turno_minutos);
    }

    public function test_rechaza_fecha_pasada_duracion_matriz_y_actor_sin_kinesiologa(): void
    {
        [$actor] = $this->actor();
        $action = app(CrearConfiguracionAgendaAction::class);
        $base = [
            'vigencia_desde' => '2026-10-07',
            'vigencia_hasta' => null,
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz(),
        ];

        foreach ([
            [$actor, array_replace($base, ['vigencia_desde' => '2026-10-05']), ConfiguracionAgendaNoElegibleException::class],
            [$actor, array_replace($base, ['duracion_turno_minutos' => 44]), InvalidArgumentException::class],
            [$actor, array_replace($base, ['franjas' => []]), InvalidArgumentException::class],
            [User::factory()->create(), $base, ConfiguracionAgendaNoElegibleException::class],
        ] as [$usuario, $datos, $exception]) {
            try {
                $action->ejecutar($usuario, $datos);
                $this->fail('Se esperaba rechazar la creación directa inválida.');
            } catch (\Throwable $error) {
                $this->assertInstanceOf($exception, $error);
            }
        }

        $this->assertSame(0, ConfiguracionAgenda::query()->count());
    }

    public function test_cierra_predecesora_abierta_y_preserva_huecos_deliberados(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $abierta = $this->configuracion($kinesiologa, '2026-09-01');

        app(CrearConfiguracionAgendaAction::class)->ejecutar($actor, [
            'vigencia_desde' => '2026-10-07',
            'vigencia_hasta' => '2026-10-31',
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz(),
        ]);

        $this->assertSame('2026-10-06', $abierta->fresh()->vigencia_hasta->toDateString());
        $this->assertEvento('configuracion_vigencia_reconciliada');

        [$otroActor, $otraKinesiologa] = $this->actor();
        $conHueco = $this->configuracion($otraKinesiologa, '2026-09-01', '2026-09-30');
        app(CrearConfiguracionAgendaAction::class)->ejecutar($otroActor, [
            'vigencia_desde' => '2026-10-20',
            'vigencia_hasta' => null,
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz(),
        ]);

        $this->assertSame('2026-09-30', $conHueco->fresh()->vigencia_hasta->toDateString());
    }

    public function test_permite_hoy_en_hueco_real_pero_no_si_hay_vigente_hoy(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $this->configuracion($kinesiologa, '2026-09-01', '2026-09-30');

        $creada = app(CrearConfiguracionAgendaAction::class)->ejecutar($actor, [
            'vigencia_desde' => '2026-10-06',
            'vigencia_hasta' => '2026-10-10',
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz(),
        ]);
        $this->assertSame('2026-10-06', $creada->vigencia_desde->toDateString());

        $this->expectException(ConfiguracionAgendaNoElegibleException::class);
        app(CrearConfiguracionAgendaAction::class)->ejecutar($actor, [
            'vigencia_desde' => '2026-10-06',
            'vigencia_hasta' => '2026-10-08',
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz(),
        ]);
    }

    public function test_respeta_sucesora_y_rechaza_fin_invasivo_o_abierto_sin_modificar_c(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $a = $this->configuracion($kinesiologa, '2026-09-01', '2026-10-09');
        $c = $this->configuracion($kinesiologa, '2026-11-01');

        $b = app(CrearConfiguracionAgendaAction::class)->ejecutar($actor, [
            'vigencia_desde' => '2026-10-10',
            'vigencia_hasta' => '2026-10-31',
            'duracion_turno_minutos' => 45,
            'franjas' => $this->matriz(),
        ]);
        $this->assertSame('2026-10-31', $b->vigencia_hasta->toDateString());
        $this->assertNull($c->fresh()->vigencia_hasta);

        [$otroActor, $otraKinesiologa] = $this->actor();
        $otraA = $this->configuracion($otraKinesiologa, '2026-09-01', '2026-10-09');
        $otraC = $this->configuracion($otraKinesiologa, '2026-11-01');

        foreach ([null, '2026-11-01'] as $fin) {
            try {
                app(CrearConfiguracionAgendaAction::class)->ejecutar($otroActor, [
                    'vigencia_desde' => '2026-10-10',
                    'vigencia_hasta' => $fin,
                    'duracion_turno_minutos' => 45,
                    'franjas' => $this->matriz(),
                ]);
                $this->fail('Se esperaba conflicto con C.');
            } catch (ConflictoTemporalAgendaException) {
                $this->assertSame('2026-10-09', $otraA->fresh()->vigencia_hasta->toDateString());
                $this->assertNull($otraC->fresh()->vigencia_hasta);
            }
        }
    }

    public function test_fallo_de_auditoria_revierte_reconciliacion_configuracion_y_franjas(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $a = $this->configuracion($kinesiologa, '2026-09-01');
        \DB::statement("CREATE TRIGGER fallar_auditoria BEFORE INSERT ON auditorias BEGIN SELECT RAISE(ABORT, 'fallo auditoria'); END");

        try {
            app(CrearConfiguracionAgendaAction::class)->ejecutar($actor, [
                'vigencia_desde' => '2026-10-07',
                'vigencia_hasta' => null,
                'duracion_turno_minutos' => 45,
                'franjas' => $this->matriz(),
            ]);
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (QueryException) {
            $this->assertNull($a->fresh()->vigencia_hasta);
            $this->assertSame(1, ConfiguracionAgenda::query()->count());
        }
    }

    public function test_detecta_integridad_preexistente_corrupta_y_aisla_otras_kinesiologas(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $this->configuracion($kinesiologa, '2026-09-01', '2026-11-01');
        $this->configuracion($kinesiologa, '2026-10-01', '2026-12-01');
        [, $otraKinesiologa] = $this->actor();
        $ajena = $this->configuracion($otraKinesiologa, '2026-01-01');

        try {
            app(CrearConfiguracionAgendaAction::class)->ejecutar($actor, [
                'vigencia_desde' => '2027-01-01',
                'vigencia_hasta' => null,
                'duracion_turno_minutos' => 45,
                'franjas' => $this->matriz(),
            ]);
            $this->fail('Se esperaba detectar la corrupción temporal.');
        } catch (\LogicException) {
            $this->assertNull($ajena->fresh()->vigencia_hasta);
            $this->assertDatabaseMissing('auditorias', ['auditable_id' => $ajena->id]);
        }
    }
}
