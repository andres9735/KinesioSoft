<?php

namespace Tests\Feature\Services\Agenda;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Services\Agenda\PrepararJornadaAgendaService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class PrepararJornadaAgendaServiceTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Kinesiologa $profesional;

    private Paciente $paciente;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->profesional = $this->profesionalUc07();
        $this->paciente = $this->pacienteUc07();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 01:30:00 UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_hoy_es_la_fecha_local_no_la_fecha_utc_y_no_requiere_sesion(): void
    {
        $temprano = $this->turno('2026-11-05 06:00:00');
        $this->turno('2026-11-06 06:00:00');
        $this->assertGuest();
        $jornada = app(PrepararJornadaAgendaService::class)->preparar($this->profesional);
        $this->assertSame('2026-11-05', $jornada->fechaLocal->toDateString());
        $this->assertSame(config('app.timezone'), $jornada->zonaHoraria);
        $this->assertSame([$temprano->id], array_column($jornada->turnos, 'id'));
    }

    #[DataProvider('fechas')]
    public function test_fecha_civil_explicita_permite_historia_recuperacion_y_futuro_sin_mutar_entrada(string $fecha): void
    {
        config(['app.timezone' => 'Pacific/Auckland']);
        $entrada = Carbon::parse($fecha.' 23:30:00', 'America/Los_Angeles');
        $original = $entrada->copy();
        $turno = $this->turno($fecha.' 00:00:00');
        $resultado = app(PrepararJornadaAgendaService::class)->preparar($this->profesional, $entrada);
        $this->assertSame($fecha, $resultado->fechaLocal->toDateString());
        $this->assertSame('Pacific/Auckland', $resultado->fechaLocal->timezoneName);
        $this->assertSame([$turno->id], array_column($resultado->turnos, 'id'));
        $this->assertTrue($entrada->equalTo($original));
        $this->assertSame($original->timezoneName, $entrada->timezoneName);
    }

    public static function fechas(): array
    {
        return [['2001-02-03'], ['2026-11-05'], ['2099-12-31']];
    }

    public function test_ambos_tipos_y_seis_estados_conservan_identidad_y_clasificacion(): void
    {
        $esperados = [];
        foreach (TipoTurnoEnum::cases() as $tipo) {
            foreach (EstadoTurnoEnum::cases() as $estado) {
                $esperados[] = $this->turno('2026-11-05 06:15:00', ['tipo' => $tipo, 'estado' => $estado]);
            }
        }
        $resultado = app(PrepararJornadaAgendaService::class)->preparar($this->profesional);
        $this->assertSame(array_map(fn ($t) => $t->id, $esperados), array_column($resultado->turnos, 'id'));
        $this->assertSame(['total' => 12, 'operativos' => 4, 'en_atencion' => 2, 'otros' => 6], $resultado->cantidades());
        foreach ($esperados as $i => $turno) {
            $this->assertSame($turno->tipo, $resultado->turnos[$i]['tipo']);
            $this->assertSame($turno->estado, $resultado->turnos[$i]['estado']);
        }
    }

    public function test_limites_por_inicio_y_orden_estable_sin_recortar_intervalos(): void
    {
        $this->turno('2026-11-04 23:59:59');
        $this->turno('2026-11-06 00:00:00');
        $ultimo = $this->turno('2026-11-05 23:59:59');
        $empate1 = $this->turno('2026-11-05 06:00:00');
        $empate2 = $this->turno('2026-11-05 06:00:00');
        $primero = $this->turno('2026-11-05 00:00:00');
        $ajena = $this->profesionalUc07();
        $this->turno('2026-11-05 09:00:00', ['kinesiologa_id' => $ajena->id]);
        $resultado = app(PrepararJornadaAgendaService::class)->preparar($this->profesional);
        $this->assertSame([$primero->id, $empate1->id, $empate2->id, $ultimo->id], array_column($resultado->turnos, 'id'));
        $this->assertSame('2026-11-06 00:29:59', $resultado->turnos[3]['fin_at']->toDateTimeString());
    }

    public function test_jornada_vacia_es_valida_y_releer_muestra_programacion_actual(): void
    {
        $servicio = app(PrepararJornadaAgendaService::class);
        $this->assertSame(['total' => 0, 'operativos' => 0, 'en_atencion' => 0, 'otros' => 0], $servicio->preparar($this->profesional)->cantidades());
        $turno = $this->turno('2026-11-05 08:00:00');
        $this->assertCount(1, $servicio->preparar($this->profesional)->turnos);
        $turno->forceFill(['estado' => EstadoTurnoEnum::CANCELADO])->save();
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $servicio->preparar($this->profesional)->turnos[0]['estado']);
        $turno->forceFill(['inicio_at' => '2026-11-06 08:00:00', 'fin_at' => '2026-11-06 08:30:00'])->save();
        $this->assertSame([], $servicio->preparar($this->profesional)->turnos);
    }

    public function test_seleccion_canonica_incluye_profesional_sin_turnos_y_excluye_no_habilitada(): void
    {
        $otra = $this->profesionalUc07();
        $inactiva = $this->profesionalUc07();
        $inactiva->user->update(['activo' => false]);
        $servicio = app(PrepararJornadaAgendaService::class);
        $this->assertSame([$this->profesional->id, $otra->id], $servicio->profesionalesHabilitadas()->modelKeys());
        $this->profesional->user->update(['activo' => false]);
        $otra->user->update(['activo' => false]);
        $this->assertTrue($servicio->profesionalesHabilitadas()->isEmpty());
    }

    #[DataProvider('inhabilitaciones')]
    public function test_revalida_habilitacion_persistida_antes_de_leer_turnos(string $condicion): void
    {
        $this->profesional->load('user.roles', 'user.kinesiologa.especialidades');
        match ($condicion) {
            'cuenta' => $this->profesional->user->update(['activo' => false]),
            'correo' => $this->profesional->user->forceFill(['email_verified_at' => null])->save(),
            'rol' => $this->profesional->user->syncRoles([]),
            'perfil' => $this->profesional->update(['titulo_profesional_id' => null]),
        };
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            app(PrepararJornadaAgendaService::class)->preparar($this->profesional);
            $this->fail('La profesional no habilitada debía rechazarse.');
        } catch (DomainException) {
            foreach (DB::getQueryLog() as $consulta) {
                $this->assertDoesNotMatchRegularExpression('/\b(turnos|pacientes|documentos_clinicos)\b/i', $consulta['query']);
            }
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public static function inhabilitaciones(): array
    {
        return [['cuenta'], ['correo'], ['rol'], ['perfil']];
    }

    public function test_no_hay_n_mas_uno_datos_clinicos_ni_escrituras_al_preparar(): void
    {
        $medidas = [];
        foreach ([1, 10] as $cantidad) {
            while (Turno::count() < $cantidad) {
                $this->turno('2026-11-05 08:00:00', ['motivo_solicitud' => 'Secreto clínico']);
            }
            $antes = DB::table('turnos')->get()->toJson();
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $resultado = app(PrepararJornadaAgendaService::class)->preparar($this->profesional);
                $consultas = DB::getQueryLog();
            } finally {
                DB::disableQueryLog();
                DB::flushQueryLog();
            }
            $medidas[] = count($consultas);
            foreach ($consultas as $consulta) {
                $this->assertMatchesRegularExpression('/^select\b/i', ltrim($consulta['query']));
                $this->assertStringNotContainsString('documentos_clinicos', $consulta['query']);
            }
            $this->assertSame($antes, DB::table('turnos')->get()->toJson());
            $this->assertStringNotContainsString('Secreto clínico', json_encode($resultado, JSON_UNESCAPED_UNICODE));
            $this->assertSame(['id', 'paciente', 'kinesiologa', 'tipo', 'estado', 'inicio_at', 'fin_at'], array_keys($resultado->turnos[0]));
        }
        $this->assertSame($medidas[0], $medidas[1]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_fallo_de_lectura_se_propaga_y_no_se_convierte_en_agenda_vacia(): void
    {
        // Inyectar una falla de conexión al leer turnos, sin sustituir la lectura compartida.
        DB::connection()->beforeExecuting(function (string $query): void {
            if (str_contains($query, '"turnos"')) {
                throw new RuntimeException('Fallo de lectura simulado');
            }
        });
        $this->expectException(RuntimeException::class);
        app(PrepararJornadaAgendaService::class)->preparar($this->profesional);
    }

    private function turno(string $inicio, array $datos = []): Turno
    {
        return Turno::factory()->for($this->paciente)->for($this->profesional)->create(array_merge([
            'tipo' => TipoTurnoEnum::SEGUIMIENTO, 'inicio_at' => $inicio,
            'fin_at' => CarbonImmutable::parse($inicio)->addMinutes(30)->toDateTimeString(),
        ], $datos));
    }
}
