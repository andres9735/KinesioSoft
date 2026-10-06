<?php

namespace Tests\Feature\Actions\Agenda;

use App\Actions\Agenda\AnularBloqueoAgendaAction;
use App\Actions\Agenda\RegistrarBloqueoAgendaAction;
use App\Actions\Agenda\RevisarBloqueoAgendaAction;
use App\Enums\EstadoTurnoEnum;
use App\Exceptions\Agenda\BloqueoAgendaNoElegibleException;
use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use App\Models\BloqueoAgenda;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\AgendaSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BloqueoAgendaActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::create(
            2026,
            10,
            6,
            12,
            0,
            0,
            config('app.timezone')
        ));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_review_devuelve_advertencias_canonicas_sin_escribir_ni_auditar(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $turnoTarde = $this->turno(
            $kinesiologa,
            '2026-10-07 11:00:00',
            '2026-10-07 11:45:00',
            EstadoTurnoEnum::CONFIRMADO
        );
        $turnoTemprano = $this->turno(
            $kinesiologa,
            '2026-10-07 10:00:00',
            '2026-10-07 10:45:00',
            EstadoTurnoEnum::PENDIENTE
        );
        $bloqueoTarde = $this->bloqueo(
            $kinesiologa,
            '2026-10-07 11:00:00',
            '2026-10-07 13:00:00',
            'Congreso previo'
        );
        $bloqueoTemprano = $this->bloqueo(
            $kinesiologa,
            '2026-10-07 09:00:00',
            '2026-10-07 10:30:00',
            'Licencia'
        );
        $antesTurnos = Turno::query()->get()->map->getAttributes()->all();

        $resultado = app(RevisarBloqueoAgendaAction::class)->ejecutar($actor, $this->candidato([
            'motivo' => '  Vacaciones  ',
        ]));

        $this->assertSame('Vacaciones', $resultado['motivo']);
        $this->assertSame('2026-10-07 10:00:00-03:00', $resultado['inicio_at']);
        $this->assertTrue($resultado['hay_turnos_afectados']);
        $this->assertSame(2, $resultado['cantidad_turnos_afectados']);
        $this->assertSame([$turnoTemprano->id, $turnoTarde->id], array_column($resultado['turnos_afectados'], 'id'));
        $this->assertSame(2, $resultado['cantidad_bloqueos_superpuestos']);
        $this->assertSame([$bloqueoTemprano->id, $bloqueoTarde->id], array_column($resultado['bloqueos_superpuestos'], 'id'));
        $this->assertNotSame('', $resultado['snapshot']);
        $this->assertSame(2, BloqueoAgenda::query()->count());
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertSame($antesTurnos, Turno::query()->get()->map->getAttributes()->all());
    }

    public function test_review_sin_advertencias_genera_snapshot_y_arrays_vacios(): void
    {
        [$actor] = $this->actor();

        $resultado = app(RevisarBloqueoAgendaAction::class)->ejecutar($actor, $this->candidato());

        $this->assertFalse($resultado['hay_turnos_afectados']);
        $this->assertSame(0, $resultado['cantidad_turnos_afectados']);
        $this->assertSame([], $resultado['turnos_afectados']);
        $this->assertSame(0, $resultado['cantidad_bloqueos_superpuestos']);
        $this->assertSame([], $resultado['bloqueos_superpuestos']);
        $this->assertNotSame('', $resultado['snapshot']);
        $this->assertDatabaseCount('bloqueos_agenda', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_review_es_defensiva_ante_actor_y_candidato_invalidos(): void
    {
        $actorSinPerfil = User::factory()->create();

        try {
            app(RevisarBloqueoAgendaAction::class)->ejecutar($actorSinPerfil, $this->candidato());
            $this->fail('Se esperaba actor sin perfil.');
        } catch (BloqueoAgendaNoElegibleException) {
            $this->assertDatabaseCount('bloqueos_agenda', 0);
        }

        [$actor] = $this->actor();

        foreach ([
            ['inicio_at' => '2026-10-07 12:00:00', 'fin_at' => '2026-10-07 12:00:00'],
            ['inicio_at' => '2026-10-07 13:00:00', 'fin_at' => '2026-10-07 12:00:00'],
            ['inicio_at' => '2026-10-06 10:00:00', 'fin_at' => '2026-10-06 12:00:00'],
            ['motivo' => '   '],
            ['motivo' => str_repeat('a', 501)],
        ] as $cambio) {
            try {
                app(RevisarBloqueoAgendaAction::class)->ejecutar($actor, $this->candidato($cambio));
                $this->fail('Se esperaba candidato inválido.');
            } catch (InvalidArgumentException) {
                $this->assertDatabaseCount('bloqueos_agenda', 0);
            }
        }
    }

    public function test_registro_acepta_turnos_y_doble_bloqueo_sin_modificarlos(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $turno = $this->turno(
            $kinesiologa,
            '2026-10-07 10:30:00',
            '2026-10-07 11:15:00',
            EstadoTurnoEnum::PENDIENTE
        );
        $existente = $this->bloqueo(
            $kinesiologa,
            '2026-10-07 09:00:00',
            '2026-10-07 10:30:01',
            'Licencia'
        );
        $atributosTurno = $turno->getAttributes();
        $revision = $this->review($actor);

        $creado = app(RegistrarBloqueoAgendaAction::class)->ejecutar($actor, $this->candidato([
            'snapshot' => $revision['snapshot'],
            'kinesiologa_id' => 999999,
            'anulado_at' => '2026-10-01 00:00:00',
        ]));

        $this->assertSame($kinesiologa->id, $creado->kinesiologa_id);
        $this->assertNull($creado->anulado_at);
        $this->assertNull($creado->motivo_anulacion);
        $this->assertEquals($atributosTurno, $turno->fresh()->getAttributes());
        $this->assertNull($existente->fresh()->anulado_at);
        $this->assertDatabaseCount('bloqueos_agenda', 2);
        $this->assertDatabaseHas('auditorias', [
            'evento' => 'bloqueo_agenda_registrado',
            'modulo' => 'Agenda',
            'auditable_id' => $creado->id,
        ]);
        $this->assertDatabaseCount('auditorias', 1);
    }

    #[DataProvider('cambiosCandidato')]
    public function test_registro_rechaza_candidato_distinto_del_review(string $campo, string $valor): void
    {
        [$actor] = $this->actor();
        $revision = $this->review($actor);

        $this->esperarSnapshotDesactualizado(fn () => app(RegistrarBloqueoAgendaAction::class)
            ->ejecutar($actor, $this->candidato([
                'snapshot' => $revision['snapshot'],
                $campo => $valor,
            ])));

        $this->assertDatabaseCount('bloqueos_agenda', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function cambiosCandidato(): array
    {
        return [
            'inicio' => ['inicio_at', '2026-10-07 10:01:00'],
            'fin' => ['fin_at', '2026-10-07 12:01:00'],
            'motivo' => ['motivo', 'Motivo cambiado'],
        ];
    }

    #[DataProvider('cambiosTurno')]
    public function test_registro_detecta_nuevo_turno_y_cambios_semanticos_del_turno(
        string $escenario
    ): void {
        [$actor, $kinesiologa] = $this->actor();
        $turno = null;

        if ($escenario !== 'nuevo') {
            $turno = $this->turno(
                $kinesiologa,
                '2026-10-07 10:30:00',
                '2026-10-07 11:15:00',
                EstadoTurnoEnum::PENDIENTE
            );
        }
        $revision = $this->review($actor);

        match ($escenario) {
            'nuevo' => $this->turno(
                $kinesiologa,
                '2026-10-07 10:30:00',
                '2026-10-07 11:15:00',
                EstadoTurnoEnum::CONFIRMADO
            ),
            'estado' => $turno->forceFill(['estado' => EstadoTurnoEnum::CONFIRMADO])->save(),
            'inicio' => $turno->forceFill(['inicio_at' => '2026-10-07 10:31:00'])->save(),
            'fin' => $turno->forceFill(['fin_at' => '2026-10-07 11:16:00'])->save(),
            'desaparece' => $turno->forceFill(['estado' => EstadoTurnoEnum::CANCELADO])->save(),
            'eliminado' => $turno->delete(),
        };

        $this->esperarSnapshotDesactualizado(fn () => app(RegistrarBloqueoAgendaAction::class)
            ->ejecutar($actor, $this->candidato(['snapshot' => $revision['snapshot']])));
        $this->assertDatabaseCount('bloqueos_agenda', 0);
    }

    public static function cambiosTurno(): array
    {
        return [
            'nuevo confirmado' => ['nuevo'],
            'estado pendiente a confirmado' => ['estado'],
            'inicio' => ['inicio'],
            'fin' => ['fin'],
            'deja de ser afectable' => ['desaparece'],
            'eliminado concurrentemente' => ['eliminado'],
        ];
    }

    public function test_registro_detecta_aparicion_y_anulacion_de_bloqueos_superpuestos(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $revisionSinBloqueo = $this->review($actor);
        $nuevo = $this->bloqueo(
            $kinesiologa,
            '2026-10-07 11:00:00',
            '2026-10-07 13:00:00',
            'Congreso'
        );

        $this->esperarSnapshotDesactualizado(fn () => app(RegistrarBloqueoAgendaAction::class)
            ->ejecutar($actor, $this->candidato(['snapshot' => $revisionSinBloqueo['snapshot']])));

        $revisionConBloqueo = $this->review($actor);
        $nuevo->anulado_at = '2026-10-06 12:00:00';
        $nuevo->motivo_anulacion = 'Corrección';
        $nuevo->save();

        $this->esperarSnapshotDesactualizado(fn () => app(RegistrarBloqueoAgendaAction::class)
            ->ejecutar($actor, $this->candidato(['snapshot' => $revisionConBloqueo['snapshot']])));
        $this->assertDatabaseCount('bloqueos_agenda', 1);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_registro_revalida_el_reloj_antes_de_escribir(): void
    {
        [$actor] = $this->actor();
        $candidato = $this->candidato([
            'inicio_at' => '2026-10-06 11:00:00',
            'fin_at' => '2026-10-06 12:01:00',
        ]);
        $revision = $this->review($actor, $candidato);
        CarbonImmutable::setTestNow('2026-10-06 12:01:00');

        try {
            app(RegistrarBloqueoAgendaAction::class)->ejecutar($actor, [
                ...$candidato,
                'snapshot' => $revision['snapshot'],
            ]);
            $this->fail('Se esperaba rechazar el bloqueo histórico.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('bloqueos_agenda', 0);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public function test_snapshot_de_review_no_puede_reutilizarse_entre_kinesiologas(): void
    {
        [$actorA] = $this->actor();
        [$actorB] = $this->actor();
        $revision = $this->review($actorA);

        $this->esperarSnapshotDesactualizado(fn () => app(RegistrarBloqueoAgendaAction::class)
            ->ejecutar($actorB, $this->candidato(['snapshot' => $revision['snapshot']])));

        $this->assertDatabaseCount('bloqueos_agenda', 0);
    }

    #[DataProvider('posicionesTemporalesAnulables')]
    public function test_anula_bloqueo_propio_sin_restriccion_temporal(string $inicio, string $fin): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $bloqueo = $this->bloqueo($kinesiologa, $inicio, $fin, 'Motivo original');
        $originales = $bloqueo->only(['kinesiologa_id', 'inicio_at', 'fin_at', 'motivo']);
        $snapshot = app(AgendaSnapshotService::class)->generarParaBloqueo($bloqueo);

        $resultado = app(AnularBloqueoAgendaAction::class)->ejecutar($actor, $bloqueo, [
            'motivo_anulacion' => '  Corrección administrativa  ',
            'snapshot' => $snapshot,
            'anulado_at' => '2000-01-01 00:00:00',
        ]);

        $this->assertTrue($resultado->anulado_at->equalTo(CarbonImmutable::now(config('app.timezone'))));
        $this->assertSame('Corrección administrativa', $resultado->motivo_anulacion);
        $this->assertEquals($originales, $resultado->only(['kinesiologa_id', 'inicio_at', 'fin_at', 'motivo']));
        $this->assertDatabaseHas('auditorias', [
            'evento' => 'bloqueo_agenda_anulado',
            'modulo' => 'Agenda',
            'auditable_id' => $bloqueo->id,
        ]);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public static function posicionesTemporalesAnulables(): array
    {
        return [
            'futuro' => ['2026-10-07 10:00:00', '2026-10-07 12:00:00'],
            'en curso' => ['2026-10-06 10:00:00', '2026-10-06 13:00:00'],
            'pasado obligatorio' => ['2026-10-05 10:00:00', '2026-10-05 12:00:00'],
        ];
    }

    public function test_segunda_anulacion_se_rechaza_sin_sobrescribir_ni_auditar(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $bloqueo = $this->bloqueo(
            $kinesiologa,
            '2026-10-07 10:00:00',
            '2026-10-07 12:00:00',
            'Motivo original'
        );
        $snapshot = app(AgendaSnapshotService::class)->generarParaBloqueo($bloqueo);
        $primero = app(AnularBloqueoAgendaAction::class)->ejecutar($actor, $bloqueo, [
            'motivo_anulacion' => 'Primera anulación',
            'snapshot' => $snapshot,
        ]);
        $anuladoAt = $primero->anulado_at->format('Y-m-d H:i:s');

        try {
            app(AnularBloqueoAgendaAction::class)->ejecutar($actor, $bloqueo, [
                'motivo_anulacion' => 'Intento de sobrescritura',
                'snapshot' => app(AgendaSnapshotService::class)->generarParaBloqueo($primero),
            ]);
            $this->fail('Se esperaba rechazo de segunda anulación.');
        } catch (BloqueoAgendaNoElegibleException) {
            $actual = $bloqueo->fresh();
            $this->assertSame($anuladoAt, $actual->anulado_at->format('Y-m-d H:i:s'));
            $this->assertSame('Primera anulación', $actual->motivo_anulacion);
            $this->assertSame(1, DB::table('auditorias')->where('evento', 'bloqueo_agenda_anulado')->count());
        }
    }

    public function test_anulacion_rechaza_snapshot_stale_y_ownership_directo(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        [, $otra] = $this->actor();
        $bloqueo = $this->bloqueo($kinesiologa, '2026-10-07 10:00:00', '2026-10-07 12:00:00', 'Original');
        $snapshot = app(AgendaSnapshotService::class)->generarParaBloqueo($bloqueo);
        $bloqueo->motivo = 'Cambio concurrente';
        $bloqueo->save();

        $this->esperarSnapshotDesactualizado(fn () => app(AnularBloqueoAgendaAction::class)
            ->ejecutar($actor, $bloqueo, [
                'motivo_anulacion' => 'Corrección',
                'snapshot' => $snapshot,
            ]));

        $ajeno = $this->bloqueo($otra, '2026-10-07 13:00:00', '2026-10-07 14:00:00', 'Ajeno');
        try {
            app(AnularBloqueoAgendaAction::class)->ejecutar($actor, $ajeno, [
                'motivo_anulacion' => 'No autorizado',
                'snapshot' => app(AgendaSnapshotService::class)->generarParaBloqueo($ajeno),
            ]);
            $this->fail('Se esperaba rechazo por ownership.');
        } catch (BloqueoAgendaNoElegibleException) {
            $this->assertNull($ajeno->fresh()->anulado_at);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public function test_actor_sin_perfil_falla_controladamente_en_las_tres_actions(): void
    {
        $actor = User::factory()->create();
        [, $kinesiologa] = $this->actor();
        $bloqueo = $this->bloqueo($kinesiologa, '2026-10-07 10:00:00', '2026-10-07 12:00:00', 'Ajeno');

        foreach ([
            fn () => app(RevisarBloqueoAgendaAction::class)->ejecutar($actor, $this->candidato()),
            fn () => app(RegistrarBloqueoAgendaAction::class)->ejecutar($actor, $this->candidato(['snapshot' => 'token'])),
            fn () => app(AnularBloqueoAgendaAction::class)->ejecutar($actor, $bloqueo, [
                'motivo_anulacion' => 'Motivo',
                'snapshot' => 'token',
            ]),
        ] as $operacion) {
            try {
                $operacion();
                $this->fail('Se esperaba actor sin perfil.');
            } catch (BloqueoAgendaNoElegibleException) {
                $this->assertTrue(true);
            }
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_fallo_de_auditoria_revierte_alta_sin_tocar_dependencias(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $turno = $this->turno(
            $kinesiologa,
            '2026-10-07 10:30:00',
            '2026-10-07 11:15:00',
            EstadoTurnoEnum::PENDIENTE
        );
        $existente = $this->bloqueo($kinesiologa, '2026-10-07 09:00:00', '2026-10-07 10:30:01', 'Previo');
        $revision = $this->review($actor);
        $turnoAntes = $turno->getAttributes();
        DB::statement("CREATE TRIGGER fallar_auditoria BEFORE INSERT ON auditorias BEGIN SELECT RAISE(ABORT, 'fallo auditoria'); END");

        try {
            app(RegistrarBloqueoAgendaAction::class)->ejecutar($actor, $this->candidato([
                'snapshot' => $revision['snapshot'],
            ]));
            $this->fail('Se esperaba fallo de auditoría.');
        } catch (QueryException) {
            $this->assertDatabaseCount('bloqueos_agenda', 1);
            $this->assertNull($existente->fresh()->anulado_at);
            $this->assertEquals($turnoAntes, $turno->fresh()->getAttributes());
        }
    }

    public function test_fallo_de_auditoria_revierte_anulacion(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $bloqueo = $this->bloqueo($kinesiologa, '2026-10-05 10:00:00', '2026-10-05 12:00:00', 'Original');
        $snapshot = app(AgendaSnapshotService::class)->generarParaBloqueo($bloqueo);
        DB::statement("CREATE TRIGGER fallar_auditoria BEFORE INSERT ON auditorias BEGIN SELECT RAISE(ABORT, 'fallo auditoria'); END");

        try {
            app(AnularBloqueoAgendaAction::class)->ejecutar($actor, $bloqueo, [
                'motivo_anulacion' => 'Debe revertirse',
                'snapshot' => $snapshot,
            ]);
            $this->fail('Se esperaba fallo de auditoría.');
        } catch (QueryException) {
            $actual = $bloqueo->fresh();
            $this->assertNull($actual->anulado_at);
            $this->assertNull($actual->motivo_anulacion);
            $this->assertSame('Original', $actual->motivo);
        }
    }

    public function test_registro_emite_consultas_en_orden_logico_de_locks(): void
    {
        [$actor] = $this->actor();
        $revision = $this->review($actor);
        $consultas = [];
        DB::listen(function (QueryExecuted $query) use (&$consultas): void {
            $sql = strtolower($query->sql);
            if (str_starts_with($sql, 'select')) {
                $consultas[] = $sql;
            }
        });

        app(RegistrarBloqueoAgendaAction::class)->ejecutar($actor, $this->candidato([
            'snapshot' => $revision['snapshot'],
        ]));

        $indiceKinesiologa = $this->indiceConsulta($consultas, 'from "kinesiologas"');
        $indiceBloqueos = $this->indiceConsulta($consultas, 'from "bloqueos_agenda"');
        $indiceTurnos = $this->indiceConsulta($consultas, 'from "turnos"');
        $this->assertLessThan($indiceBloqueos, $indiceKinesiologa);
        $this->assertLessThan($indiceTurnos, $indiceBloqueos);
    }

    public function test_anulacion_bloquea_kinesiologa_antes_del_target(): void
    {
        [$actor, $kinesiologa] = $this->actor();
        $bloqueo = $this->bloqueo(
            $kinesiologa,
            '2026-10-07 10:00:00',
            '2026-10-07 12:00:00',
            'Congreso'
        );
        $snapshot = app(AgendaSnapshotService::class)->generarParaBloqueo($bloqueo);
        $consultas = [];
        DB::listen(function (QueryExecuted $query) use (&$consultas): void {
            $sql = strtolower($query->sql);
            if (str_starts_with($sql, 'select')) {
                $consultas[] = $sql;
            }
        });

        app(AnularBloqueoAgendaAction::class)->ejecutar($actor, $bloqueo, [
            'motivo_anulacion' => 'Corrección',
            'snapshot' => $snapshot,
        ]);

        $this->assertLessThan(
            $this->indiceConsulta($consultas, 'from "bloqueos_agenda"'),
            $this->indiceConsulta($consultas, 'from "kinesiologas"')
        );
    }

    /** @return array{0: User, 1: Kinesiologa} */
    private function actor(): array
    {
        $user = User::factory()->create();
        $kinesiologa = Kinesiologa::create(['user_id' => $user->id]);

        return [$user->fresh('kinesiologa'), $kinesiologa];
    }

    private function turno(
        Kinesiologa $kinesiologa,
        string $inicio,
        string $fin,
        EstadoTurnoEnum $estado
    ): Turno {
        $paciente = Paciente::create([
            'user_id' => User::factory()->create()->id,
            'activo' => true,
        ]);

        return Turno::factory()->for($paciente)->for($kinesiologa)->create([
            'estado' => $estado,
            'inicio_at' => $inicio,
            'fin_at' => $fin,
        ]);
    }

    private function bloqueo(
        Kinesiologa $kinesiologa,
        string $inicio,
        string $fin,
        string $motivo
    ): BloqueoAgenda {
        return BloqueoAgenda::factory()->for($kinesiologa)->create([
            'inicio_at' => $inicio,
            'fin_at' => $fin,
            'motivo' => $motivo,
        ]);
    }

    /** @return array<string, mixed> */
    private function candidato(array $cambios = []): array
    {
        return array_replace([
            'inicio_at' => '2026-10-07 10:00:00',
            'fin_at' => '2026-10-07 12:00:00',
            'motivo' => 'Vacaciones',
        ], $cambios);
    }

    /** @return array<string, mixed> */
    private function review(User $actor, ?array $candidato = null): array
    {
        return app(RevisarBloqueoAgendaAction::class)->ejecutar(
            $actor,
            $candidato ?? $this->candidato()
        );
    }

    private function esperarSnapshotDesactualizado(callable $operacion): void
    {
        try {
            $operacion();
            $this->fail('Se esperaba SnapshotAgendaDesactualizadoException.');
        } catch (SnapshotAgendaDesactualizadoException) {
            $this->assertTrue(true);
        }
    }

    /** @param list<string> $consultas */
    private function indiceConsulta(array $consultas, string $fragmento): int
    {
        foreach ($consultas as $indice => $consulta) {
            if (str_contains($consulta, $fragmento)) {
                return $indice;
            }
        }

        $this->fail("No se encontró la consulta {$fragmento}.");
    }
}
