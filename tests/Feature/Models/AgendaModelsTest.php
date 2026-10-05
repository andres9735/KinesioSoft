<?php

namespace Tests\Feature\Models;

use App\Enums\DiaSemanaEnum;
use App\Models\BloqueoAgenda;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuracion_agenda_uses_expected_table_fillable_casts_and_relations(): void
    {
        $kinesiologa = $this->kinesiologa();
        $configuracion = ConfiguracionAgenda::factory()
            ->for($kinesiologa)
            ->create([
                'vigencia_desde' => '2026-10-06',
                'vigencia_hasta' => '2026-12-31',
                'duracion_turno_minutos' => 45,
                'anulado_at' => '2026-10-05 10:30:00',
                'motivo_anulacion' => 'Cambio de planificación.',
            ])
            ->fresh();
        $franja = FranjaHorariaAgenda::factory()
            ->for($configuracion, 'configuracionAgenda')
            ->create();

        $this->assertSame('configuraciones_agenda', $configuracion->getTable());
        $this->assertSame([
            'vigencia_desde',
            'vigencia_hasta',
            'duracion_turno_minutos',
        ], $configuracion->getFillable());
        $this->assertInstanceOf(CarbonImmutable::class, $configuracion->vigencia_desde);
        $this->assertInstanceOf(CarbonImmutable::class, $configuracion->vigencia_hasta);
        $this->assertSame(45, $configuracion->duracion_turno_minutos);
        $this->assertInstanceOf(CarbonImmutable::class, $configuracion->anulado_at);
        $this->assertTrue($configuracion->kinesiologa->is($kinesiologa));
        $this->assertTrue($configuracion->fresh()->franjasHorarias->contains($franja));
    }

    public function test_franja_horaria_uses_expected_table_fillable_cast_and_time_strings(): void
    {
        $configuracion = ConfiguracionAgenda::factory()
            ->for($this->kinesiologa())
            ->create();
        $franja = FranjaHorariaAgenda::factory()
            ->for($configuracion, 'configuracionAgenda')
            ->create([
                'dia_semana' => DiaSemanaEnum::DOMINGO,
                'hora_inicio' => '09:15:00',
                'hora_fin' => '13:45:00',
            ])
            ->fresh();

        $this->assertSame('franjas_horarias_agenda', $franja->getTable());
        $this->assertSame([
            'dia_semana',
            'hora_inicio',
            'hora_fin',
        ], $franja->getFillable());
        $this->assertSame(DiaSemanaEnum::DOMINGO, $franja->dia_semana);
        $this->assertSame('09:15:00', $franja->hora_inicio);
        $this->assertSame('13:45:00', $franja->hora_fin);
        $this->assertTrue($franja->configuracionAgenda->is($configuracion));
    }

    public function test_bloqueo_agenda_uses_expected_table_fillable_casts_and_relation(): void
    {
        $kinesiologa = $this->kinesiologa();
        $bloqueo = BloqueoAgenda::factory()
            ->for($kinesiologa)
            ->create([
                'inicio_at' => '2026-10-10 08:00:00',
                'fin_at' => '2026-10-10 12:00:00',
                'motivo' => 'Congreso profesional.',
                'anulado_at' => '2026-10-05 11:00:00',
                'motivo_anulacion' => 'El congreso fue reprogramado.',
            ])
            ->fresh();

        $this->assertSame('bloqueos_agenda', $bloqueo->getTable());
        $this->assertSame([
            'inicio_at',
            'fin_at',
            'motivo',
        ], $bloqueo->getFillable());
        $this->assertInstanceOf(CarbonImmutable::class, $bloqueo->inicio_at);
        $this->assertInstanceOf(CarbonImmutable::class, $bloqueo->fin_at);
        $this->assertInstanceOf(CarbonImmutable::class, $bloqueo->anulado_at);
        $this->assertTrue($bloqueo->kinesiologa->is($kinesiologa));
    }

    public function test_kinesiologa_has_many_configuraciones_and_bloqueos(): void
    {
        $kinesiologa = $this->kinesiologa();
        $configuracion = ConfiguracionAgenda::factory()
            ->for($kinesiologa)
            ->create();
        $bloqueo = BloqueoAgenda::factory()
            ->for($kinesiologa)
            ->create();

        $kinesiologa->refresh();

        $this->assertTrue($kinesiologa->configuracionesAgenda->contains($configuracion));
        $this->assertTrue($kinesiologa->bloqueosAgenda->contains($bloqueo));
    }

    private function kinesiologa(): Kinesiologa
    {
        return Kinesiologa::create([
            'user_id' => User::factory()->create()->id,
            'titulo_profesional_id' => null,
            'consultorio_id' => null,
        ]);
    }
}
