<?php

namespace Tests\Unit;

use App\Models\Turno;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnoLeadTimesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2025, 11, 03, 10, 00, 00)); // 03/11/2025 10:00
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeTurnoIn(int $minutesFromNow, string $estado = Turno::ESTADO_PENDIENTE): Turno
    {
        $inicio = Carbon::now()->copy()->addMinutes($minutesFromNow);

        return new Turno([
            'profesional_id' => 1,
            'paciente_id'    => 1,
            'id_consultorio' => null,
            'fecha'          => $inicio->toDateString(),
            'hora_desde'     => $inicio->format('H:i'),
            'hora_hasta'     => $inicio->copy()->addMinutes(45)->format('H:i'),
            'estado'         => $estado,
            'motivo'         => null,
        ]);
    }

    #[Test]
    public function puede_confirmar_ahora_respeta_confirm_min_minutes(): void
    {
        Config::set('turnos.confirm_min_minutes', 180);

        $t1 = $this->makeTurnoIn(200, Turno::ESTADO_PENDIENTE);
        $this->assertTrue($t1->puedeConfirmarAhora());

        $t2 = $this->makeTurnoIn(180, Turno::ESTADO_PENDIENTE);
        $this->assertTrue($t2->puedeConfirmarAhora());

        $t3 = $this->makeTurnoIn(179, Turno::ESTADO_PENDIENTE);
        $this->assertFalse($t3->puedeConfirmarAhora());

        $t4 = $this->makeTurnoIn(300, Turno::ESTADO_CONFIRMADO);
        $this->assertFalse($t4->puedeConfirmarAhora());
    }

    #[Test]
    public function puede_cancelar_ahora_respeta_cancel_min_minutes(): void
    {
        Config::set('turnos.cancel_min_minutes', 1440);

        $t1 = $this->makeTurnoIn(1500, Turno::ESTADO_PENDIENTE);
        $this->assertTrue($t1->puedeCancelarAhora());

        $t2 = $this->makeTurnoIn(1440, Turno::ESTADO_PENDIENTE);
        $this->assertTrue($t2->puedeCancelarAhora());

        $t3 = $this->makeTurnoIn(1000, Turno::ESTADO_PENDIENTE);
        $this->assertFalse($t3->puedeCancelarAhora());

        $t4 = $this->makeTurnoIn(2000, Turno::ESTADO_CANCELADO);
        $this->assertFalse($t4->puedeCancelarAhora());
    }
}
