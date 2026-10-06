<?php

namespace Tests\Feature\Services\Agenda;

use App\Enums\EstadoTurnoEnum;
use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use App\Models\BloqueoAgenda;
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

    public function test_snapshot_de_bloqueo_verifica_estado_semantico_completo(): void
    {
        $bloqueo = $this->bloqueo();
        $service = app(AgendaSnapshotService::class);
        $token = $service->generarParaBloqueo($bloqueo);

        $service->verificarBloqueo($token, $bloqueo->fresh());
        $this->assertNotSame('', $token);

        foreach ([
            ['inicio_at', '2026-10-10 07:00:00'],
            ['fin_at', '2026-10-10 11:00:00'],
            ['motivo', 'Motivo concurrente'],
            ['anulado_at', '2026-10-07 10:00:00'],
            ['motivo_anulacion', 'Anulación concurrente'],
        ] as [$campo, $valor]) {
            $cambiado = $bloqueo->fresh();
            $cambiado->forceFill([$campo => $valor]);
            $this->expectaSnapshotDesactualizado(
                fn () => $service->verificarBloqueo($token, $cambiado)
            );
        }
    }

    public function test_snapshot_de_bloqueo_rechaza_token_manipulado_malformado_y_de_otro_bloqueo(): void
    {
        $service = app(AgendaSnapshotService::class);
        $bloqueo = $this->bloqueo();
        $otro = $this->bloqueo();
        $token = $service->generarParaBloqueo($bloqueo);
        $manipulado = substr($token, 0, 20)
            .($token[20] === 'a' ? 'b' : 'a')
            .substr($token, 21);

        foreach (['token-invalido', $manipulado, $service->generarParaBloqueo($otro)] as $invalido) {
            $this->expectaSnapshotDesactualizado(
                fn () => $service->verificarBloqueo($invalido, $bloqueo)
            );
        }
    }

    public function test_snapshot_de_revision_es_canonico_y_ligado_a_kinesiologa(): void
    {
        $service = app(AgendaSnapshotService::class);
        $candidato = [
            'inicio_at' => '2026-10-10 10:00:00',
            'fin_at' => '2026-10-10 12:00:00',
            'motivo' => ' Congreso ',
        ];
        $turnos = [
            ['id' => 2, 'estado' => EstadoTurnoEnum::CONFIRMADO, 'inicio_at' => '2026-10-10 11:00:00', 'fin_at' => '2026-10-10 11:45:00'],
            ['id' => 1, 'estado' => EstadoTurnoEnum::PENDIENTE, 'inicio_at' => '2026-10-10 10:00:00', 'fin_at' => '2026-10-10 10:45:00'],
        ];
        $bloqueos = [
            ['id' => 2, 'inicio_at' => '2026-10-10 11:00:00', 'fin_at' => '2026-10-10 13:00:00', 'anulado_at' => null, 'motivo' => 'Segundo'],
            ['id' => 1, 'inicio_at' => '2026-10-10 09:00:00', 'fin_at' => '2026-10-10 10:30:00', 'anulado_at' => null, 'motivo' => 'Primero'],
        ];
        $token = $service->generarRevisionBloqueo(10, $candidato, $turnos, $bloqueos);

        $service->verificarRevisionBloqueo(
            $token,
            10,
            [...$candidato, 'motivo' => 'Congreso'],
            array_reverse($turnos),
            array_reverse($bloqueos)
        );
        $this->assertNotSame('', $token);

        $this->expectaSnapshotDesactualizado(fn () => $service->verificarRevisionBloqueo(
            $token,
            11,
            $candidato,
            $turnos,
            $bloqueos
        ));
    }

    public function test_snapshot_de_revision_detecta_cambios_en_candidato_turnos_y_bloqueos(): void
    {
        $service = app(AgendaSnapshotService::class);
        $candidato = [
            'inicio_at' => '2026-10-10 10:00:00',
            'fin_at' => '2026-10-10 12:00:00',
            'motivo' => 'Congreso',
        ];
        $turnos = [[
            'id' => 1,
            'estado' => EstadoTurnoEnum::PENDIENTE,
            'inicio_at' => '2026-10-10 10:00:00',
            'fin_at' => '2026-10-10 10:45:00',
        ]];
        $bloqueos = [[
            'id' => 1,
            'inicio_at' => '2026-10-10 09:00:00',
            'fin_at' => '2026-10-10 10:30:00',
            'anulado_at' => null,
            'motivo' => 'Licencia',
        ]];
        $token = $service->generarRevisionBloqueo(10, $candidato, $turnos, $bloqueos);

        foreach ([
            [[...$candidato, 'inicio_at' => '2026-10-10 10:01:00'], $turnos, $bloqueos],
            [[...$candidato, 'fin_at' => '2026-10-10 12:01:00'], $turnos, $bloqueos],
            [[...$candidato, 'motivo' => 'Otro'], $turnos, $bloqueos],
            [$candidato, [[...$turnos[0], 'estado' => EstadoTurnoEnum::CONFIRMADO]], $bloqueos],
            [$candidato, [[...$turnos[0], 'fin_at' => '2026-10-10 11:00:00']], $bloqueos],
            [$candidato, $turnos, [[...$bloqueos[0], 'motivo' => 'Otro motivo']]],
            [$candidato, $turnos, []],
        ] as [$candidatoActual, $turnosActuales, $bloqueosActuales]) {
            $this->expectaSnapshotDesactualizado(fn () => $service->verificarRevisionBloqueo(
                $token,
                10,
                $candidatoActual,
                $turnosActuales,
                $bloqueosActuales
            ));
        }
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

    private function bloqueo(): BloqueoAgenda
    {
        $kinesiologa = Kinesiologa::create(['user_id' => User::factory()->create()->id]);

        return BloqueoAgenda::factory()->for($kinesiologa)->create([
            'inicio_at' => '2026-10-10 08:00:00',
            'fin_at' => '2026-10-10 10:00:00',
            'motivo' => 'Congreso',
        ]);
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
