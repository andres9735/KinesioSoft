<?php

namespace Tests\Unit\Models;

use App\Models\BloqueoAgenda;
use App\Models\ConfiguracionAgenda;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AgendaTemporalRulesTest extends TestCase
{
    public function test_estados_de_configuracion_respetan_precedencia_y_bordes_inclusivos(): void
    {
        $hoy = CarbonImmutable::parse('2026-10-05');

        $this->assertSame(
            ConfiguracionAgenda::ESTADO_ANULADA,
            $this->configuracion('2026-10-10', null, '2026-10-01 10:00:00')->estadoEn($hoy)
        );
        $this->assertSame(
            ConfiguracionAgenda::ESTADO_FUTURA,
            $this->configuracion('2026-10-06')->estadoEn($hoy)
        );
        $this->assertSame(
            ConfiguracionAgenda::ESTADO_VIGENTE,
            $this->configuracion('2026-10-05')->estadoEn($hoy)
        );
        $this->assertSame(
            ConfiguracionAgenda::ESTADO_VIGENTE,
            $this->configuracion('2026-09-01', '2026-10-05')->estadoEn($hoy)
        );
        $this->assertSame(
            ConfiguracionAgenda::ESTADO_HISTORICA,
            $this->configuracion('2026-09-01', '2026-10-04')->estadoEn($hoy)
        );
    }

    public function test_aplicabilidad_y_editabilidad_son_puras_y_reciben_fecha_explicita(): void
    {
        $configuracion = $this->configuracion('2026-10-05', '2026-10-10');

        $this->assertFalse($configuracion->aplicaEn(CarbonImmutable::parse('2026-10-04')));
        $this->assertTrue($configuracion->aplicaEn(CarbonImmutable::parse('2026-10-05')));
        $this->assertTrue($configuracion->aplicaEn(CarbonImmutable::parse('2026-10-10')));
        $this->assertFalse($configuracion->aplicaEn(CarbonImmutable::parse('2026-10-11')));
        $this->assertTrue($configuracion->puedeEditarseEn(CarbonImmutable::parse('2026-10-04')));
        $this->assertFalse($configuracion->puedeEditarseEn(CarbonImmutable::parse('2026-10-05')));

        $abierta = $this->configuracion('2026-10-05');
        $this->assertTrue($abierta->aplicaEn(CarbonImmutable::parse('2099-12-31')));

        $anulada = $this->configuracion('2026-10-06', null, '2026-10-01 10:00:00');
        $this->assertFalse($anulada->aplicaEn(CarbonImmutable::parse('2026-10-06')));
        $this->assertFalse($anulada->puedeEditarseEn(CarbonImmutable::parse('2026-10-05')));
    }

    public function test_estados_de_bloqueo_respetan_intervalo_semiabierto(): void
    {
        $ahora = CarbonImmutable::parse('2026-10-05 10:00:00');

        $this->assertSame(
            BloqueoAgenda::ESTADO_ANULADO,
            $this->bloqueo('2026-10-06 08:00:00', '2026-10-06 10:00:00', '2026-10-01 10:00:00')
                ->estadoEn($ahora)
        );
        $this->assertSame(
            BloqueoAgenda::ESTADO_PROXIMO,
            $this->bloqueo('2026-10-05 10:00:01', '2026-10-05 11:00:00')->estadoEn($ahora)
        );
        $this->assertSame(
            BloqueoAgenda::ESTADO_EN_CURSO,
            $this->bloqueo('2026-10-05 10:00:00', '2026-10-05 11:00:00')->estadoEn($ahora)
        );
        $this->assertSame(
            BloqueoAgenda::ESTADO_EN_CURSO,
            $this->bloqueo('2026-10-05 09:00:00', '2026-10-05 11:00:00')->estadoEn($ahora)
        );
        $this->assertSame(
            BloqueoAgenda::ESTADO_PASADO,
            $this->bloqueo('2026-10-05 09:00:00', '2026-10-05 10:00:00')->estadoEn($ahora)
        );
        $this->assertSame(
            BloqueoAgenda::ESTADO_PASADO,
            $this->bloqueo('2026-10-05 08:00:00', '2026-10-05 09:59:59')->estadoEn($ahora)
        );
    }

    private function configuracion(
        string $desde,
        ?string $hasta = null,
        ?string $anuladoAt = null
    ): ConfiguracionAgenda {
        return (new ConfiguracionAgenda)->forceFill([
            'vigencia_desde' => $desde,
            'vigencia_hasta' => $hasta,
            'duracion_turno_minutos' => 45,
            'anulado_at' => $anuladoAt,
        ]);
    }

    private function bloqueo(
        string $inicio,
        string $fin,
        ?string $anuladoAt = null
    ): BloqueoAgenda {
        return (new BloqueoAgenda)->forceFill([
            'inicio_at' => $inicio,
            'fin_at' => $fin,
            'motivo' => 'Prueba temporal',
            'anulado_at' => $anuladoAt,
        ]);
    }
}
