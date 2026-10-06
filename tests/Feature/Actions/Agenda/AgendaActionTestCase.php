<?php

namespace Tests\Feature\Actions\Agenda;

use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\Agenda\AgendaSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AgendaActionTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-06 10:30:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @return array{0: User, 1: Kinesiologa} */
    protected function actor(): array
    {
        $user = User::factory()->create();
        $kinesiologa = Kinesiologa::create(['user_id' => $user->id]);

        return [$user->fresh('kinesiologa'), $kinesiologa];
    }

    protected function configuracion(
        Kinesiologa $kinesiologa,
        string $desde,
        ?string $hasta = null,
        int $duracion = 45,
        ?string $anuladoAt = null
    ): ConfiguracionAgenda {
        $configuracion = ConfiguracionAgenda::factory()->for($kinesiologa)->create([
            'vigencia_desde' => $desde,
            'vigencia_hasta' => $hasta,
            'duracion_turno_minutos' => $duracion,
            'anulado_at' => $anuladoAt,
            'motivo_anulacion' => $anuladoAt === null ? null : 'Motivo anterior',
        ]);
        FranjaHorariaAgenda::factory()->for($configuracion, 'configuracionAgenda')->create([
            'dia_semana' => 1,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '12:00:00',
        ]);

        return $configuracion;
    }

    /** @return list<array{dia_semana: int, hora_inicio: string, hora_fin: string}> */
    protected function matriz(string $inicio = '08:00:00', string $fin = '12:00:00'): array
    {
        return [[
            'dia_semana' => 1,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
        ]];
    }

    protected function snapshot(ConfiguracionAgenda $configuracion): string
    {
        $configuracion->unsetRelation('franjasHorarias');

        return app(AgendaSnapshotService::class)->generarParaConfiguracion($configuracion->fresh());
    }

    protected function assertEvento(string $evento, int $cantidad = 1): void
    {
        $this->assertSame($cantidad, (int) \DB::table('auditorias')->where('evento', $evento)->count());
    }
}
