<?php

namespace Tests\Feature\Services\Agenda;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\AgendaAtencionQueryService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AgendaAtencionQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private AgendaAtencionQueryService $service;

    private Kinesiologa $kinesiologa;

    private Paciente $paciente;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        $this->service = new AgendaAtencionQueryService;
        $this->kinesiologa = $this->crearKinesiologa();
        $this->paciente = Paciente::create(['user_id' => User::factory()->create()->id, 'activo' => true]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_jornada_sin_turnos_devuelve_una_coleccion_vacia(): void
    {
        $this->assertTrue($this->service->turnosDeJornada(
            $this->kinesiologa, CarbonImmutable::parse('2026-11-05')
        )->isEmpty());
    }

    public function test_incluye_ambos_tipos_en_los_seis_estados_sin_reclasificarlos(): void
    {
        $esperados = [];
        foreach (TipoTurnoEnum::cases() as $tipo) {
            foreach (EstadoTurnoEnum::cases() as $estado) {
                $esperados[] = $this->turno('2026-11-05 08:00:00', '2026-11-05 08:45:00', $tipo, $estado);
            }
        }

        $resultado = $this->service->turnosDeJornada($this->kinesiologa, CarbonImmutable::parse('2026-11-05'));

        $this->assertSame(array_map(fn (Turno $turno) => $turno->id, $esperados), $resultado->modelKeys());
        foreach ($esperados as $indice => $turno) {
            $this->assertSame($turno->tipo, $resultado[$indice]->tipo);
            $this->assertSame($turno->estado, $resultado[$indice]->estado);
        }
    }

    public function test_excluye_turnos_de_otra_profesional_en_la_misma_fecha(): void
    {
        $propio = $this->turno('2026-11-05 10:00:00', '2026-11-05 11:00:00');
        $ajeno = $this->turno('2026-11-05 10:00:00', '2026-11-05 11:00:00');
        $otra = $this->crearKinesiologa();
        $ajeno->kinesiologa()->associate($otra);
        $ajeno->save();

        $fecha = CarbonImmutable::parse('2026-11-05');
        $this->assertSame([$propio->id], $this->service->turnosDeJornada($this->kinesiologa, $fecha)->modelKeys());
        $this->assertSame([$ajeno->id], $this->service->turnosDeJornada($otra, $fecha)->modelKeys());
    }

    public function test_pertenencia_por_inicio_incluye_medianoche_y_excluye_la_siguiente_sin_recortar_intervalos(): void
    {
        $this->turno('2026-11-04 23:15:00', '2026-11-05 00:00:00');
        $this->turno('2026-11-04 23:59:59', '2026-11-05 00:30:00');
        $primero = $this->turno('2026-11-05 00:00:00', '2026-11-05 00:25:00');
        $ultimo = $this->turno('2026-11-05 23:59:59', '2026-11-06 00:44:59');
        $this->turno('2026-11-06 00:00:00', '2026-11-06 01:00:00');

        $resultado = $this->service->turnosDeJornada($this->kinesiologa, CarbonImmutable::parse('2026-11-05'));

        $this->assertSame([$primero->id, $ultimo->id], $resultado->modelKeys());
        foreach ([$primero, $ultimo] as $indice => $turno) {
            $this->assertSame($turno->inicio_at->toDateTimeString(), $resultado[$indice]->inicio_at->toDateTimeString());
            $this->assertSame($turno->fin_at->toDateTimeString(), $resultado[$indice]->fin_at->toDateTimeString());
        }
    }

    public function test_ordena_por_inicio_e_id_incluyendo_turnos_anteriores_a_las_siete(): void
    {
        $tarde = $this->turno('2026-11-05 18:00:00', '2026-11-05 18:30:00');
        $empatePrimero = $this->turno('2026-11-05 09:00:00', '2026-11-05 10:00:00');
        $empateSegundo = $this->turno('2026-11-05 09:00:00', '2026-11-05 09:20:00');
        $temprano = $this->turno('2026-11-05 06:15:00', '2026-11-05 06:50:00');
        $fecha = CarbonImmutable::parse('2026-11-05');
        $esperados = [$temprano->id, $empatePrimero->id, $empateSegundo->id, $tarde->id];

        $this->assertSame($esperados, $this->service->turnosDeJornada($this->kinesiologa, $fecha)->modelKeys());
        $this->assertSame($esperados, $this->service->turnosDeJornada($this->kinesiologa, $fecha)->modelKeys());
    }

    #[DataProvider('fechasDeConsulta')]
    public function test_consulta_historia_presente_y_futuro_sin_filtrar_por_ahora(string $fecha): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-05 12:00:00'));
        $esperados = [];
        foreach (['06:00:00', '12:00:00', '18:00:00'] as $hora) {
            $inicio = CarbonImmutable::parse($fecha.' '.$hora);
            $esperados[] = $this->turno($inicio->toDateTimeString(), $inicio->addMinutes(35)->toDateTimeString())->id;
        }

        $jornada = CarbonImmutable::parse($fecha);
        $this->assertSame($esperados, $this->service->turnosDeJornada($this->kinesiologa, $jornada)->modelKeys());
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2100-01-01 23:00:00'));
        $this->assertSame($esperados, $this->service->turnosDeJornada($this->kinesiologa, $jornada)->modelKeys());
    }

    public static function fechasDeConsulta(): array
    {
        return [
            'historia' => ['2001-02-03'],
            'jornada actual' => ['2026-11-05'],
            'futuro' => ['2099-12-31'],
        ];
    }

    public function test_interpreta_fecha_civil_en_zona_configurada_sin_mutar_la_entrada(): void
    {
        config(['app.timezone' => 'Pacific/Auckland']);
        $turno = $this->turno('2026-11-05 06:00:00', '2026-11-05 06:25:00');
        $this->turno('2026-11-06 06:00:00', '2026-11-06 06:25:00');
        // Como en disponibilidad, se recibe una fecha civil, no un instante a convertir.
        $fecha = Carbon::parse('2026-11-05 23:30:00', 'America/Los_Angeles');
        $original = $fecha->copy();

        $resultado = $this->service->turnosDeJornada($this->kinesiologa, $fecha);

        $this->assertSame([$turno->id], $resultado->modelKeys());
        $this->assertTrue($fecha->equalTo($original));
        $this->assertSame($original->timezoneName, $fecha->timezoneName);
    }

    public function test_lee_cambios_consolidados_sin_preparacion_uc29_ni_cache_de_programacion(): void
    {
        $fecha = CarbonImmutable::parse('2026-11-05');
        $anterior = $this->turno('2026-11-05 09:00:00', '2026-11-05 09:45:00');
        $this->assertSame([$anterior->id], $this->service->turnosDeJornada($this->kinesiologa, $fecha)->modelKeys());

        $anterior->estado = EstadoTurnoEnum::CANCELADO;
        $anterior->save();
        $nuevo = $this->turno('2026-11-05 10:00:00', '2026-11-05 10:30:00');
        $actualizados = $this->service->turnosDeJornada($this->kinesiologa, $fecha);
        $this->assertSame([$anterior->id, $nuevo->id], $actualizados->modelKeys());
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $actualizados->first()->estado);

        $anterior->inicio_at = '2026-11-06 09:00:00';
        $anterior->fin_at = '2026-11-06 09:45:00';
        $anterior->save();
        $this->assertSame([$nuevo->id], $this->service->turnosDeJornada($this->kinesiologa, $fecha)->modelKeys());
    }

    public function test_consultar_solo_ejecuta_lecturas_y_conserva_todas_las_tablas(): void
    {
        $this->turno('2026-11-05 09:00:00', '2026-11-05 09:45:00');
        $antes = $this->contenidoBase();
        $conexion = DB::connection();
        $conexion->enableQueryLog();
        $conexion->flushQueryLog();
        try {
            $this->service->turnosDeJornada($this->kinesiologa, CarbonImmutable::parse('2026-11-05'));
            $consultas = $conexion->getQueryLog();
        } finally {
            $conexion->disableQueryLog();
            $conexion->flushQueryLog();
        }

        $this->assertNotEmpty($consultas);
        foreach ($consultas as $consulta) {
            $this->assertMatchesRegularExpression('/^select\b/i', ltrim($consulta['query']));
        }
        $this->assertSame($antes, $this->contenidoBase());
    }

    public function test_periodo_incluye_fechas_extremas_y_comparte_resultados_con_las_jornadas(): void
    {
        $ultimo = $this->turno('2028-03-01 23:59:59', '2028-03-02 00:29:59');
        $empatePrimero = $this->turno('2028-02-29 06:30:00', '2028-02-29 07:15:00');
        $primero = $this->turno('2028-02-28 00:00:00', '2028-02-28 00:25:00');
        $empateSegundo = $this->turno('2028-02-29 06:30:00', '2028-02-29 06:50:00');
        $this->turno('2028-02-27 23:59:59', '2028-02-28 00:30:00');
        $this->turno('2028-03-02 00:00:00', '2028-03-02 00:45:00');
        $ajeno = $this->turno('2028-02-29 06:30:00', '2028-02-29 07:15:00');
        $ajeno->kinesiologa()->associate($this->crearKinesiologa());
        $ajeno->save();
        $desde = Carbon::parse('2028-02-28 22:00:00', 'America/Los_Angeles');
        $hasta = Carbon::parse('2028-03-01 01:00:00', 'Pacific/Auckland');
        $entradas = [$desde->toIso8601String(), $hasta->toIso8601String()];

        $resultado = $this->service->turnosDePeriodo($this->kinesiologa, $desde, $hasta);

        $this->assertSame([$primero->id, $empatePrimero->id, $empateSegundo->id, $ultimo->id], $resultado->modelKeys());
        $porJornadas = [];
        foreach (['2028-02-28', '2028-02-29', '2028-03-01'] as $fecha) {
            array_push($porJornadas, ...$this->service->turnosDeJornada(
                $this->kinesiologa, CarbonImmutable::parse($fecha)
            )->modelKeys());
        }
        $this->assertSame($porJornadas, $resultado->modelKeys());
        $this->assertSame($entradas, [$desde->toIso8601String(), $hasta->toIso8601String()]);
        $this->assertSame('2028-03-02 00:29:59', $resultado->last()->fin_at->toDateTimeString());
    }

    public function test_periodo_no_hereda_el_limite_de_ventana_de_disponibilidad(): void
    {
        $primero = $this->turno('2026-01-01 08:00:00', '2026-01-01 08:30:00');
        $ultimo = $this->turno('2026-12-31 18:00:00', '2026-12-31 18:35:00');

        $this->assertSame([$primero->id, $ultimo->id], $this->service->turnosDePeriodo(
            $this->kinesiologa, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31')
        )->modelKeys());
    }

    public function test_periodo_invertido_se_rechaza_sin_confundirlo_con_agenda_vacia(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->turnosDePeriodo(
            $this->kinesiologa, CarbonImmutable::parse('2026-11-06'), CarbonImmutable::parse('2026-11-05')
        );
    }

    public function test_representacion_de_periodo_sin_turnos_es_una_lista_vacia(): void
    {
        $this->assertSame([], $this->service->informacionDePeriodo(
            $this->kinesiologa, CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30')
        ));
    }

    public function test_representacion_minima_conserva_identidades_enums_e_intervalos_y_lee_datos_vigentes(): void
    {
        $this->paciente->user->update(['name' => 'Ana', 'apellido' => 'Pérez', 'telefono' => '123456789']);
        $this->kinesiologa->user->update(['name' => 'Laura', 'apellido' => 'Gómez']);
        $this->paciente->update(['direccion' => 'Dirección privada']);
        $primero = Turno::factory()->for($this->paciente)->for($this->kinesiologa)->create([
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA,
            'estado' => EstadoTurnoEnum::CONFIRMADO,
            'inicio_at' => '2026-11-05 06:20:00',
            'fin_at' => '2026-11-05 06:45:00',
            'motivo_solicitud' => 'Información clínica reservada',
            'clave_idempotencia' => 'ed67d7d0-13c3-4d77-9f8b-70a6ff468ead',
        ]);
        $ultimo = $this->turno(
            '2026-11-06 23:50:00', '2026-11-07 00:35:00',
            TipoTurnoEnum::SEGUIMIENTO, EstadoTurnoEnum::CANCELADO
        );
        $desde = CarbonImmutable::parse('2026-11-05');
        $hasta = CarbonImmutable::parse('2026-11-06');
        // Una relación precargada obsoleta del argumento no es la fuente de los nombres.
        $this->kinesiologa->user->name = 'Nombre obsoleto';

        $resultado = $this->service->informacionDePeriodo($this->kinesiologa, $desde, $hasta);

        $this->assertSame([$primero->id, $ultimo->id], array_column($resultado, 'id'));
        foreach ([$primero, $ultimo] as $indice => $turno) {
            $fila = $resultado[$indice];
            $this->assertSame(['id', 'paciente', 'kinesiologa', 'tipo', 'estado', 'inicio_at', 'fin_at'], array_keys($fila));
            $this->assertSame(['id' => $this->paciente->id, 'nombre' => 'Ana Pérez'], $fila['paciente']);
            $this->assertSame(['id' => $this->kinesiologa->id, 'nombre' => 'Laura Gómez'], $fila['kinesiologa']);
            $this->assertSame($turno->tipo, $fila['tipo']);
            $this->assertSame($turno->estado, $fila['estado']);
            $this->assertInstanceOf(CarbonImmutable::class, $fila['inicio_at']);
            $this->assertInstanceOf(CarbonImmutable::class, $fila['fin_at']);
            $this->assertSame($turno->inicio_at->toDateTimeString(), $fila['inicio_at']->toDateTimeString());
            $this->assertSame($turno->fin_at->toDateTimeString(), $fila['fin_at']->toDateTimeString());
        }
        $atributos = $this->service->turnosDePeriodo($this->kinesiologa, $desde, $hasta)->first()->getAttributes();
        $this->assertSame(['id', 'paciente_id', 'kinesiologa_id', 'tipo', 'estado', 'inicio_at', 'fin_at'], array_keys($atributos));

        $this->paciente->user->update(['name' => 'Ana María']);
        $ultimo->estado = EstadoTurnoEnum::ATENDIDO;
        $ultimo->save();
        $actual = $this->service->informacionDePeriodo($this->kinesiologa, $desde, $hasta);
        $this->assertSame('Ana María Pérez', $actual[0]['paciente']['nombre']);
        $this->assertSame(EstadoTurnoEnum::ATENDIDO, $actual[1]['estado']);
    }

    public function test_representacion_carga_relaciones_en_conjunto_sin_consultas_por_turno(): void
    {
        $this->turno('2026-11-05 09:00:00', '2026-11-05 09:45:00');
        $fecha = CarbonImmutable::parse('2026-11-05');
        $conexion = DB::connection();
        $medir = function () use ($conexion, $fecha): int {
            $conexion->enableQueryLog();
            $conexion->flushQueryLog();
            try {
                $this->service->informacionDePeriodo($this->kinesiologa, $fecha, $fecha);

                return count($conexion->getQueryLog());
            } finally {
                $conexion->disableQueryLog();
                $conexion->flushQueryLog();
            }
        };
        $consultasParaUno = $medir();
        foreach (range(10, 18) as $hora) {
            $paciente = Paciente::create(['user_id' => User::factory()->create()->id, 'activo' => true]);
            Turno::factory()->for($paciente)->for($this->kinesiologa)->create([
                'inicio_at' => "2026-11-05 {$hora}:00:00", 'fin_at' => "2026-11-05 {$hora}:35:00",
            ]);
        }

        $this->assertSame($consultasParaUno, $medir());
        $this->assertLessThanOrEqual(5, $consultasParaUno);
    }

    public function test_periodo_y_representacion_solo_leen_y_preservan_todas_las_tablas(): void
    {
        $this->turno('2026-11-05 09:00:00', '2026-11-05 09:45:00');
        $desde = CarbonImmutable::parse('2026-11-01');
        $hasta = CarbonImmutable::parse('2026-11-30');
        $antes = $this->contenidoBase();
        $conexion = DB::connection();
        $conexion->enableQueryLog();
        $conexion->flushQueryLog();
        try {
            $this->service->turnosDePeriodo($this->kinesiologa, $desde, $hasta);
            $this->service->informacionDePeriodo($this->kinesiologa, $desde, $hasta);
            $consultas = $conexion->getQueryLog();
        } finally {
            $conexion->disableQueryLog();
            $conexion->flushQueryLog();
        }

        $this->assertNotEmpty($consultas);
        foreach ($consultas as $consulta) {
            $this->assertMatchesRegularExpression('/^select\b/i', ltrim($consulta['query']));
        }
        $this->assertSame($antes, $this->contenidoBase());
    }

    private function crearKinesiologa(): Kinesiologa
    {
        return Kinesiologa::create(['user_id' => User::factory()->create()->id]);
    }

    private function turno(
        string $inicio,
        string $fin,
        TipoTurnoEnum $tipo = TipoTurnoEnum::SEGUIMIENTO,
        EstadoTurnoEnum $estado = EstadoTurnoEnum::PENDIENTE,
    ): Turno {
        return Turno::factory()->for($this->paciente)->for($this->kinesiologa)->create([
            'tipo' => $tipo,
            'estado' => $estado,
            'inicio_at' => $inicio,
            'fin_at' => $fin,
        ]);
    }

    private function contenidoBase(): array
    {
        $tablas = [];
        foreach (DB::connection()->getSchemaBuilder()->getTableListing() as $tabla) {
            $tablas[$tabla] = DB::table($tabla)->get()->map(fn ($fila) => (array) $fila)->all();
        }

        return $tablas;
    }
}
