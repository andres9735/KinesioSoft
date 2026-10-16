<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\DiaSemanaEnum;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\Turnos\DisponibilidadPrimeraConsultaService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GrillaPrimeraConsultaTest extends TestCase
{
    use RefreshDatabase;

    private DisponibilidadPrimeraConsultaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DisponibilidadPrimeraConsultaService::class);
    }

    public function test_without_applicable_configuration_returns_empty_grid(): void
    {
        $this->assertSame([], $this->grilla($this->kinesiologa()));
    }

    public function test_without_a_band_for_the_requested_day_returns_empty_grid(): void
    {
        $kinesiologa = $this->kinesiologa();
        $configuracion = $this->configuracion($kinesiologa);
        $this->franja($configuracion, '08:00:00', '10:00:00', DiaSemanaEnum::MARTES);

        $this->assertSame([], $this->grilla($kinesiologa));
    }

    #[DataProvider('geometrias')]
    public function test_grid_follows_each_band_independently(int $duracion, array $franjas, array $esperado): void
    {
        $kinesiologa = $this->kinesiologa();
        $configuracion = $this->configuracion($kinesiologa, duracion: $duracion);
        foreach ($franjas as [$inicio, $fin]) {
            $this->franja($configuracion, $inicio, $fin);
        }

        $this->assertSame($esperado, $this->horarios($this->grilla($kinesiologa)));
    }

    public static function geometrias(): array
    {
        return [
            'una duracion exacta' => [60, [['08:00:00', '09:00:00']], [['08:00:00', '09:00:00']]],
            'cierre exacto incluido' => [60, [['08:00:00', '10:00:00']], [['08:00:00', '09:00:00'], ['09:00:00', '10:00:00']]],
            'remanente descartado' => [45, [['08:00:00', '10:00:00']], [['08:00:00', '08:45:00'], ['08:45:00', '09:30:00']]],
            'inicio sin redondeo' => [30, [['08:10:00', '09:40:00']], [['08:10:00', '08:40:00'], ['08:40:00', '09:10:00'], ['09:10:00', '09:40:00']]],
            'separadas sin cruzar gap' => [60, [['08:00:00', '09:30:00'], ['10:00:00', '12:00:00']], [['08:00:00', '09:00:00'], ['10:00:00', '11:00:00'], ['11:00:00', '12:00:00']]],
            'contiguas con cierre exacto' => [60, [['08:00:00', '10:00:00'], ['10:00:00', '12:00:00']], [['08:00:00', '09:00:00'], ['09:00:00', '10:00:00'], ['10:00:00', '11:00:00'], ['11:00:00', '12:00:00']]],
            'contiguas no arrastran remanente' => [60, [['08:00:00', '09:20:00'], ['09:20:00', '10:40:00']], [['08:00:00', '09:00:00'], ['09:20:00', '10:20:00']]],
            'tres franjas insertadas fuera de orden' => [60, [['16:15:00', '17:15:00'], ['08:10:00', '09:10:00'], ['12:20:00', '13:20:00']], [['08:10:00', '09:10:00'], ['12:20:00', '13:20:00'], ['16:15:00', '17:15:00']]],
            'segundos persistidos conservados' => [30, [['08:10:15', '09:10:15']], [['08:10:15', '08:40:15'], ['08:40:15', '09:10:15']]],
            'duracion minima' => [15, [['08:00:00', '08:30:00']], [['08:00:00', '08:15:00'], ['08:15:00', '08:30:00']]],
            'duracion maxima' => [180, [['08:00:00', '11:00:00']], [['08:00:00', '11:00:00']]],
        ];
    }

    public function test_uses_only_the_requested_weekday_and_professional(): void
    {
        $kinesiologa = $this->kinesiologa();
        $configuracion = $this->configuracion($kinesiologa);
        $this->franja($configuracion, '10:00:00', '10:30:00', DiaSemanaEnum::MARTES);
        $this->franja($configuracion, '08:00:00', '08:30:00');
        $this->franja($configuracion, '16:00:00', '16:30:00', DiaSemanaEnum::DOMINGO);
        $this->franja($this->configuracion($this->kinesiologa()), '12:00:00', '12:30:00');

        $this->assertSame([['08:00:00', '08:30:00']], $this->horarios($this->grilla($kinesiologa)));
        $this->assertSame([['10:00:00', '10:30:00']], $this->horarios($this->grilla($kinesiologa, '2026-10-06')));
        $this->assertSame([['16:00:00', '16:30:00']], $this->horarios($this->grilla($kinesiologa, '2026-10-11')));
    }

    public function test_each_date_uses_its_own_configuration_duration_and_weekly_matrix(): void
    {
        $kinesiologa = $this->kinesiologa();
        $anterior = $this->configuracion($kinesiologa, '2026-10-01', '2026-10-11', 60);
        $posterior = $this->configuracion($kinesiologa, '2026-10-12', null, 30);
        $this->franja($anterior, '08:00:00', '10:00:00');
        $this->franja($posterior, '10:10:00', '11:10:00');

        $this->assertSame([['08:00:00', '09:00:00'], ['09:00:00', '10:00:00']], $this->horarios($this->grilla($kinesiologa, '2026-10-05')));
        $this->assertSame([['10:10:00', '10:40:00'], ['10:40:00', '11:10:00']], $this->horarios($this->grilla($kinesiologa, '2026-10-12')));
    }

    public function test_gap_does_not_extrapolate_previous_or_next_configuration(): void
    {
        $kinesiologa = $this->kinesiologa();
        $anterior = $this->configuracion($kinesiologa, '2026-10-01', '2026-10-05');
        $posterior = $this->configuracion($kinesiologa, '2026-10-19');
        $this->franja($anterior, '08:00:00', '08:30:00');
        $this->franja($posterior, '10:00:00', '10:30:00');

        $this->assertSame([['08:00:00', '08:30:00']], $this->horarios($this->grilla($kinesiologa, '2026-10-05')));
        $this->assertSame([], $this->grilla($kinesiologa, '2026-10-12'));
        $this->assertSame([['10:00:00', '10:30:00']], $this->horarios($this->grilla($kinesiologa, '2026-10-19')));
        $this->assertSame([], $this->grilla($kinesiologa, '2026-09-28'));
    }

    public function test_annulled_configuration_produces_no_grid(): void
    {
        $kinesiologa = $this->kinesiologa();
        $configuracion = $this->configuracion($kinesiologa);
        $this->franja($configuracion, '08:00:00', '08:30:00');
        $configuracion->forceFill(['anulado_at' => '2026-09-30 10:00:00', 'motivo_anulacion' => 'Prueba'])->save();

        $this->assertSame([], $this->grilla($kinesiologa));
    }

    public function test_open_configuration_remains_applicable_without_an_advance_horizon(): void
    {
        $kinesiologa = $this->kinesiologa();
        $configuracion = $this->configuracion($kinesiologa);
        $fecha = CarbonImmutable::parse('2099-12-31', config('app.timezone'));
        $this->franja($configuracion, '08:00:00', '08:30:00', DiaSemanaEnum::from($fecha->dayOfWeekIso));

        $this->assertSame([['08:00:00', '08:30:00']], $this->horarios($this->service->generarGrillaParaFecha($kinesiologa, $fecha)));
    }

    public function test_dates_and_candidates_are_immutable_use_application_timezone_and_have_no_residual_seconds(): void
    {
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        $kinesiologa = $this->kinesiologa();
        $this->franja($this->configuracion($kinesiologa), '08:10:00', '09:10:00');
        $fecha = Carbon::parse('2026-10-05 23:59:47.123456', 'UTC');
        $original = $fecha->copy();

        $grilla = $this->service->generarGrillaParaFecha($kinesiologa, $fecha);

        $this->assertTrue($fecha->equalTo($original));
        $this->assertSame('UTC', $fecha->timezoneName);
        $this->assertSame([['08:10:00', '08:40:00'], ['08:40:00', '09:10:00']], $this->horarios($grilla));
        foreach ($grilla as $candidato) {
            $this->assertSame(['inicio_at', 'fin_at'], array_keys($candidato));
            foreach ($candidato as $instante) {
                $this->assertInstanceOf(CarbonImmutable::class, $instante);
                $this->assertSame(config('app.timezone'), $instante->timezoneName);
                $this->assertSame('2026-10-05', $instante->toDateString());
                $this->assertSame('000000', $instante->format('u'));
            }
        }
        $grilla[0]['inicio_at']->addDay();
        $this->assertSame('2026-10-05 08:10:00', $grilla[0]['inicio_at']->toDateTimeString());
        $this->assertSame('2026-10-05 08:40:00', $grilla[1]['inicio_at']->toDateTimeString());
    }

    public function test_grid_is_read_only_uses_only_configuration_and_bands_and_does_not_filter_by_now(): void
    {
        $kinesiologa = $this->kinesiologa();
        $this->franja($this->configuracion($kinesiologa), '08:00:00', '10:00:00');
        DB::enableQueryLog();
        DB::flushQueryLog();

        $grilla = $this->grilla($kinesiologa);
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(4, $grilla);
        $this->assertCount(2, $consultas);
        $this->assertStringContainsString('configuraciones_agenda', $consultas[0]['query']);
        $this->assertStringContainsString('franjas_horarias_agenda', $consultas[1]['query']);
        foreach ($consultas as $consulta) {
            $this->assertStringStartsWith('select', strtolower($consulta['query']));
            $this->assertStringNotContainsString('turnos', $consulta['query']);
            $this->assertStringNotContainsString('bloqueos_agenda', $consulta['query']);
        }
        $this->assertFalse(class_exists('App\\Models\\Slot'));
        $this->assertFalse(Schema::hasTable('slots'));
        $this->assertFalse(Schema::hasTable('disponibilidades'));
    }

    public function test_grid_remains_identical_before_at_and_after_its_start_time(): void
    {
        $kinesiologa = $this->kinesiologa();
        $this->franja($this->configuracion($kinesiologa), '08:00:00', '09:00:00');
        $esperado = [['08:00:00', '08:30:00'], ['08:30:00', '09:00:00']];

        try {
            foreach (['2026-10-04 12:00:00', '2026-10-05 08:00:00', '2026-10-06 12:00:00'] as $ahora) {
                CarbonImmutable::setTestNow(CarbonImmutable::parse($ahora, config('app.timezone')));
                $this->assertSame($esperado, $this->horarios($this->grilla($kinesiologa)));
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[DataProvider('duracionesCorruptas')]
    public function test_invalid_persisted_duration_is_not_silently_repaired(int $duracion): void
    {
        $kinesiologa = $this->kinesiologa();
        $this->franja($this->configuracion($kinesiologa, duracion: $duracion), '08:00:00', '12:00:00');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('duración persistida');

        $this->grilla($kinesiologa);
    }

    public static function duracionesCorruptas(): array
    {
        return [[0], [-5], [10], [31], [185]];
    }

    #[DataProvider('matricesCorruptas')]
    public function test_invalid_persisted_bands_follow_existing_agenda_validation(array $franjas): void
    {
        $kinesiologa = $this->kinesiologa();
        $configuracion = $this->configuracion($kinesiologa, duracion: 60);
        foreach ($franjas as [$inicio, $fin]) {
            $this->franja($configuracion, $inicio, $fin);
        }
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('matriz persistida');

        $this->grilla($kinesiologa);
    }

    public static function matricesCorruptas(): array
    {
        return [
            'fin igual a inicio' => [[['08:00:00', '08:00:00']]],
            'fin anterior a inicio' => [[['09:00:00', '08:00:00']]],
            'menor que duracion' => [[['08:00:00', '08:30:00']]],
            'superpuestas' => [[['08:00:00', '10:00:00'], ['09:00:00', '11:00:00']]],
        ];
    }

    public function test_ambiguous_applicable_configuration_propagates_existing_agenda_integrity_error(): void
    {
        $kinesiologa = $this->kinesiologa();
        $this->configuracion($kinesiologa);
        $this->configuracion($kinesiologa);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('más de una configuración');

        $this->grilla($kinesiologa);
    }

    private function kinesiologa(): Kinesiologa
    {
        return Kinesiologa::create(['user_id' => User::factory()->create()->id]);
    }

    private function configuracion(Kinesiologa $kinesiologa, string $desde = '2026-10-01', ?string $hasta = null, int $duracion = 30): ConfiguracionAgenda
    {
        return ConfiguracionAgenda::factory()->for($kinesiologa)->create([
            'vigencia_desde' => $desde,
            'vigencia_hasta' => $hasta,
            'duracion_turno_minutos' => $duracion,
        ]);
    }

    private function franja(ConfiguracionAgenda $configuracion, string $inicio, string $fin, DiaSemanaEnum $dia = DiaSemanaEnum::LUNES): void
    {
        FranjaHorariaAgenda::factory()->for($configuracion, 'configuracionAgenda')->create([
            'dia_semana' => $dia,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
        ]);
    }

    private function grilla(Kinesiologa $kinesiologa, string $fecha = '2026-10-05'): array
    {
        return $this->service->generarGrillaParaFecha($kinesiologa, CarbonImmutable::parse($fecha, config('app.timezone')));
    }

    private function horarios(array $grilla): array
    {
        return array_map(fn (array $candidato): array => [
            $candidato['inicio_at']->format('H:i:s'),
            $candidato['fin_at']->format('H:i:s'),
        ], $grilla);
    }
}
