<?php

namespace Tests\Unit\Services\Agenda;

use App\Services\Agenda\RelojAplicacion;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class RelojAplicacionTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_devuelve_carbon_immutable_en_la_zona_configurada(): void
    {
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 01:30:00 UTC'));

        $ahora = (new RelojAplicacion)->ahora();

        $this->assertInstanceOf(CarbonImmutable::class, $ahora);
        $this->assertSame('America/Argentina/Buenos_Aires', $ahora->getTimezone()->getName());
        $this->assertSame('2026-10-05 22:30:00', $ahora->format('Y-m-d H:i:s'));
    }

    public function test_hoy_procede_del_mismo_contexto_temporal_que_ahora(): void
    {
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 01:30:00 UTC'));
        $reloj = new RelojAplicacion;

        $ahora = $reloj->ahora();
        $hoy = $reloj->hoy($ahora);

        $this->assertSame('2026-10-05', $hoy->toDateString());
        $this->assertSame('00:00:00', $hoy->format('H:i:s'));
        $this->assertSame($ahora->getTimezone()->getName(), $hoy->getTimezone()->getName());
    }
}
