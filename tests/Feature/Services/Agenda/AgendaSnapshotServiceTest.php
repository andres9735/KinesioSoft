<?php

namespace Tests\Feature\Services\Agenda;

use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\Agenda\AgendaSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_genera_y_verifica_un_snapshot_sin_cambios_y_con_orden_canonico(): void
    {
        $configuracion = $this->configuracion();
        $segunda = $this->franja($configuracion, 1, '13:00:00', '17:00:00');
        $primera = $configuracion->franjasHorarias()->firstOrFail();
        $configuracion->setRelation('franjasHorarias', collect([$segunda, $primera]));

        $service = app(AgendaSnapshotService::class);
        $token = $service->generarParaConfiguracion($configuracion);

        $configuracion->setRelation('franjasHorarias', collect([$primera, $segunda]));
        $service->verificarConfiguracion($token, $configuracion);

        $this->assertNotSame('', $token);
    }

    public function test_rechaza_tokens_manipulados_malformados_y_de_otra_configuracion(): void
    {
        $service = app(AgendaSnapshotService::class);
        $configuracion = $this->configuracion();
        $otra = $this->configuracion();
        $token = $service->generarParaConfiguracion($configuracion);

        $manipulado = substr($token, 0, 20)
            .($token[20] === 'a' ? 'b' : 'a')
            .substr($token, 21);

        foreach (['token-invalido', $token.'x', $manipulado, $service->generarParaConfiguracion($otra)] as $invalido) {
            try {
                $service->verificarConfiguracion($invalido, $configuracion);
                $this->fail('Se esperaba rechazar el snapshot.');
            } catch (SnapshotAgendaDesactualizadoException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_invalida_cambios_semanticos_de_cabecera_y_anulacion(): void
    {
        foreach ([
            ['vigencia_desde', '2026-10-11'],
            ['vigencia_hasta', '2026-12-30'],
            ['duracion_turno_minutos', 60],
            ['anulado_at', '2026-10-07 10:00:00'],
            ['motivo_anulacion', 'Cambio concurrente'],
        ] as [$campo, $valor]) {
            $configuracion = $this->configuracion();
            $service = app(AgendaSnapshotService::class);
            $token = $service->generarParaConfiguracion($configuracion);
            $configuracion->forceFill([$campo => $valor]);

            $this->expectaSnapshotDesactualizado(
                fn () => $service->verificarConfiguracion($token, $configuracion)
            );
        }
    }

    public function test_invalida_un_cambio_solo_en_una_franja(): void
    {
        $configuracion = $this->configuracion();
        $service = app(AgendaSnapshotService::class);
        $token = $service->generarParaConfiguracion($configuracion);
        $configuracion->franjasHorarias()->firstOrFail()->update(['hora_fin' => '13:00:00']);
        $configuracion->unsetRelation('franjasHorarias');

        $this->expectaSnapshotDesactualizado(
            fn () => $service->verificarConfiguracion($token, $configuracion)
        );
    }

    public function test_invalida_borrar_y_recrear_una_franja_semanticamente_identica(): void
    {
        $configuracion = $this->configuracion();
        $service = app(AgendaSnapshotService::class);
        $token = $service->generarParaConfiguracion($configuracion);
        $anterior = $configuracion->franjasHorarias()->firstOrFail();
        $datos = $anterior->only(['dia_semana', 'hora_inicio', 'hora_fin']);
        $anterior->delete();
        $configuracion->franjasHorarias()->create($datos);
        $configuracion->unsetRelation('franjasHorarias');

        $this->expectaSnapshotDesactualizado(
            fn () => $service->verificarConfiguracion($token, $configuracion)
        );
    }

    private function configuracion(): ConfiguracionAgenda
    {
        $user = User::factory()->create();
        $kinesiologa = Kinesiologa::create(['user_id' => $user->id]);
        $configuracion = ConfiguracionAgenda::factory()->for($kinesiologa)->create([
            'vigencia_desde' => '2026-10-10',
            'vigencia_hasta' => '2026-12-31',
            'duracion_turno_minutos' => 45,
        ]);
        $this->franja($configuracion, 1, '08:00:00', '12:00:00');

        return $configuracion;
    }

    private function franja(
        ConfiguracionAgenda $configuracion,
        int $dia,
        string $inicio,
        string $fin
    ): FranjaHorariaAgenda {
        return FranjaHorariaAgenda::factory()->for($configuracion, 'configuracionAgenda')->create([
            'dia_semana' => $dia,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
        ]);
    }

    private function expectaSnapshotDesactualizado(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Se esperaba SnapshotAgendaDesactualizadoException.');
        } catch (SnapshotAgendaDesactualizadoException) {
            $this->assertTrue(true);
        }
    }
}
