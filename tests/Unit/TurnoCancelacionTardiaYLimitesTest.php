<?php

namespace Tests\Unit;

use App\Models\Turno;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnoCancelacionTardiaYLimitesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Fijamos la hora actual (03/11/2025 10:00)
        Carbon::setTestNow(Carbon::create(2025, 11, 03, 10, 00, 00));
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
    public function ventana_de_cancelacion_tardia_se_detecta_por_debajo_del_deadline_pero_aun_en_futuro(): void
    {
        // 24h como deadline de cancelación "regular"
        Config::set('turnos.cancel_min_minutes', 1440);

        // Caso 1: falta 60 min -> NO puede cancelar "regular", pero está en FUTURO (elegible para cancelación tardía)
        $t1 = $this->makeTurnoIn(60, Turno::ESTADO_PENDIENTE);
        $this->assertFalse($t1->puedeCancelarAhora(), 'Con 60m restantes no debería poder cancelar regular (deadline=1440).');
        $this->assertTrue(Carbon::now()->lt($t1->inicio), 'El turno sigue en el futuro (candidata a cancelación tardía).');

        // Caso 2: falta 0 min -> borde (ya no es futuro a partir del inicio)
        $t2 = $this->makeTurnoIn(0, Turno::ESTADO_PENDIENTE);
        $this->assertFalse($t2->puedeCancelarAhora());
        $this->assertFalse(Carbon::now()->lt($t2->inicio), 'En el inicio o pasado, ya no es elegible para cancelación tardía.');

        // Caso 3: falta -1 min -> pasado
        $t3 = $this->makeTurnoIn(-1, Turno::ESTADO_PENDIENTE);
        $this->assertFalse($t3->puedeCancelarAhora());
        $this->assertTrue(Carbon::now()->gt($t3->inicio), 'Turno en el pasado (no corresponde cancelación).');
    }

    #[Test]
    public function limites_de_confirmacion_y_cancelacion_se_calculan_correctamente(): void
    {
        // confirm_min=180 (3h), cancel_min=1440 (24h)
        Config::set('turnos.confirm_min_minutes', 180);
        Config::set('turnos.cancel_min_minutes', 1440);

        // Turno dentro de 2 días a las 09:30
        $inicio = Carbon::now()->copy()->addDays(2)->setTime(9, 30, 0);
        $t = new Turno([
            'profesional_id' => 1,
            'paciente_id'    => 1,
            'fecha'          => $inicio->toDateString(),
            'hora_desde'     => $inicio->format('H:i'),
            'hora_hasta'     => $inicio->copy()->addMinutes(45)->format('H:i'),
            'estado'         => Turno::ESTADO_PENDIENTE,
        ]);

        $limConfirm = $t->limiteConfirmacion();
        $limCancel  = $t->limiteCancelacion();

        // Límite de confirmación = inicio - 180 min (3h)
        $this->assertEquals(
            $inicio->copy()->subMinutes(180)->toDateTimeString(),
            $limConfirm?->toDateTimeString(),
            'limiteConfirmacion() debe restar confirm_min_minutes al inicio.'
        );

        // Límite de cancelación = inicio - 1440 min (24h)
        $this->assertEquals(
            $inicio->copy()->subMinutes(1440)->toDateTimeString(),
            $limCancel?->toDateTimeString(),
            'limiteCancelacion() debe restar cancel_min_minutes al inicio.'
        );
    }
}
