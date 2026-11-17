<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\DiaSemanaEnum;
use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\BloqueoAgenda;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Turnos\ValidarDestinoAdelantoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class ValidarDestinoAdelantoServiceTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Kinesiologa $profesional;

    private Paciente $paciente;

    private Turno $candidato;

    private ConfiguracionAgenda $configuracion;

    private ValidarDestinoAdelantoService $validador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->profesional = $this->profesionalUc07();
        $this->paciente = $this->pacienteUc07();
        $this->configuracion = $this->profesional->configuracionesAgenda()->sole();
        $this->candidato = $this->turno($this->profesional, $this->paciente, [
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA,
            'inicio_at' => '2026-11-23 10:00:00', 'fin_at' => '2026-11-23 10:30:00',
            'motivo_solicitud' => 'Motivo original de prueba',
        ]);
        $this->validador = app(ValidarDestinoAdelantoService::class);
    }

    #[DataProvider('tiposCupoYEstadosCandidato')]
    public function test_conserva_treinta_minutos_en_cupo_de_cuarenta_y_cinco_de_ambos_tipos(string $tipo, string $estado): void
    {
        $cupo = $this->turno($this->profesional, $this->paciente, [
            'tipo' => $tipo, 'estado' => EstadoTurnoEnum::CANCELADO,
            'inicio_at' => '2026-11-16 10:10:00', 'fin_at' => '2026-11-16 10:55:00',
        ]);
        $this->candidato->forceFill(['estado' => $estado])->save();
        $antes = $this->candidato->getAttributes();
        $cancelado = $cupo->getAttributes();

        $destino = $this->validador->validar($this->candidato, $this->profesional, $cupo->inicio_at, $cupo->fin_at);

        $this->assertNotNull($destino);
        $this->assertTrue($destino['inicio_at']->equalTo($cupo->inicio_at));
        $this->assertSame('2026-11-16 10:40:00', $destino['fin_at']->toDateTimeString());
        $this->assertSame(30.0, $destino['inicio_at']->diffInMinutes($destino['fin_at']));
        $this->assertSame($antes, $this->candidato->fresh()->getAttributes());
        $this->assertSame($cancelado, $cupo->fresh()->getAttributes());
    }

    public static function tiposCupoYEstadosCandidato(): array
    {
        return [
            ['PRIMERA_CONSULTA', 'PENDIENTE'], ['PRIMERA_CONSULTA', 'CONFIRMADO'],
            ['SEGUIMIENTO', 'PENDIENTE'], ['SEGUIMIENTO', 'CONFIRMADO'],
        ];
    }

    public function test_admite_inicio_fuera_de_grilla_y_origen_en_otra_configuracion(): void
    {
        $this->configuracion->update(['vigencia_hasta' => '2026-11-16', 'duracion_turno_minutos' => 45]);
        $otra = ConfiguracionAgenda::factory()->for($this->profesional)->create([
            'vigencia_desde' => '2026-11-17', 'duracion_turno_minutos' => 30,
        ]);
        FranjaHorariaAgenda::factory()->for($otra, 'configuracionAgenda')->create([
            'dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00',
        ]);

        $destino = $this->validar();

        $this->assertNotNull($destino);
        $this->assertSame('10:10:00', $destino['inicio_at']->format('H:i:s'));
        $this->assertSame('10:40:00', $destino['fin_at']->format('H:i:s'));
        $this->assertSame('2026-11-23 10:30:00', $this->candidato->fresh()->fin_at->toDateTimeString());
    }

    #[DataProvider('duracionesInvalidas')]
    public function test_rechaza_duracion_no_positiva_o_mayor_que_el_cupo(int $minutos): void
    {
        $this->candidato->update(['fin_at' => $this->candidato->inicio_at->addMinutes($minutos)]);
        $this->assertNull($this->validar());
    }

    public static function duracionesInvalidas(): array
    {
        return [[0], [-30], [46]];
    }

    public function test_admite_encaje_exacto_y_conserva_segundos_sin_redondear(): void
    {
        $this->candidato->update(['fin_at' => '2026-11-23 10:30:15']);
        $destino = $this->validar('2026-11-16 10:10:05', '2026-11-16 10:40:20');
        $this->assertSame('10:10:05', $destino['inicio_at']->format('H:i:s'));
        $this->assertSame('10:40:20', $destino['fin_at']->format('H:i:s'));
        $this->assertNull($this->validar('2026-11-16 10:10:05', '2026-11-16 10:40:19'));
    }

    public function test_rechaza_cupo_no_positivo(): void
    {
        $this->assertNull($this->validar('2026-11-16 10:10:00', '2026-11-16 10:10:00'));
        $this->assertNull($this->validar('2026-11-16 10:10:00', '2026-11-16 10:09:00'));
    }

    #[DataProvider('franjasSinContencion')]
    public function test_exige_una_franja_del_dia_que_contenga_todo_el_intervalo(array $franjas): void
    {
        $this->configuracion->franjasHorarias()->delete();
        foreach ($franjas as [$dia, $inicio, $fin]) {
            FranjaHorariaAgenda::factory()->for($this->configuracion, 'configuracionAgenda')->create([
                'dia_semana' => $dia, 'hora_inicio' => $inicio, 'hora_fin' => $fin,
            ]);
        }
        $this->assertNull($this->validar());
    }

    public static function franjasSinContencion(): array
    {
        return [
            'sin franjas' => [[]],
            'otro dia' => [[[2, '10:00:00', '12:00:00']]],
            'inicio anterior' => [[[1, '10:15:00', '12:00:00']]],
            'fin posterior' => [[[1, '10:00:00', '10:30:00']]],
            'no unir franjas contiguas' => [[[1, '10:00:00', '10:30:00'], [1, '10:30:00', '11:00:00']]],
            'no atravesar hueco' => [[[1, '09:30:00', '10:15:00'], [1, '10:30:00', '12:00:00']]],
        ];
    }

    public function test_no_extiende_la_franja_a_la_jornada_siguiente(): void
    {
        $this->configuracion->franjasHorarias()->update(['hora_inicio' => '23:00:00', 'hora_fin' => '23:59:59']);
        $this->assertNull($this->validar('2026-11-16 23:45:00', '2026-11-17 00:30:00'));
    }

    #[DataProvider('intersecciones')]
    public function test_bloqueos_y_turnos_usan_cruces_semiabiertos(string $fuente, string $inicio, string $fin, bool $libre): void
    {
        if ($fuente === 'bloqueo') {
            BloqueoAgenda::factory()->for($this->profesional)->create([
                'inicio_at' => '2026-11-16 '.$inicio, 'fin_at' => '2026-11-16 '.$fin,
            ]);
        } else {
            $profesional = $fuente === 'paciente' ? $this->profesionalUc07() : $this->profesional;
            $paciente = $fuente === 'paciente' ? $this->paciente : $this->pacienteUc07();
            $this->turno($profesional, $paciente, [
                'inicio_at' => '2026-11-16 '.$inicio, 'fin_at' => '2026-11-16 '.$fin,
            ]);
        }
        $this->assertSame($libre, $this->validar() !== null);
    }

    public static function intersecciones(): array
    {
        $casos = [];
        foreach (['bloqueo', 'profesional', 'paciente'] as $fuente) {
            foreach ([
                'contiguo anterior' => ['09:40:00', '10:10:00', true],
                'contiguo posterior' => ['10:40:00', '11:10:00', true],
                'parcial anterior' => ['10:00:00', '10:11:00', false],
                'parcial posterior' => ['10:39:00', '11:00:00', false],
                'exacto' => ['10:10:00', '10:40:00', false],
                'contiene destino' => ['09:00:00', '12:00:00', false],
            ] as $nombre => $datos) {
                $casos[$fuente.' '.$nombre] = [$fuente, ...$datos];
            }
        }

        return $casos;
    }

    #[DataProvider('tiposYEstadosOcupantes')]
    public function test_cruce_del_paciente_no_filtra_profesional_ni_tipo_y_respeta_estados(string $tipo, string $estado, bool $ocupa): void
    {
        $otro = $this->turno($this->profesionalUc07(), $this->paciente, [
            'tipo' => $tipo, 'estado' => $estado,
            'inicio_at' => '2026-11-16 10:20:00', 'fin_at' => '2026-11-16 10:50:00',
        ]);
        $query = app(AgendaQueryService::class)->turnosDePacienteAfectadosPorIntervalo(
            $this->paciente->id, $this->fecha('2026-11-16 10:10:00'), $this->fecha('2026-11-16 10:40:00'),
        );
        $this->assertSame($ocupa ? [$otro->id] : [], $query->get()->modelKeys());
        $this->assertSame(! $ocupa, $this->validar() !== null);
    }

    public static function tiposYEstadosOcupantes(): array
    {
        $casos = [];
        foreach (TipoTurnoEnum::cases() as $tipo) {
            foreach (EstadoTurnoEnum::cases() as $estado) {
                $casos[$tipo->value.' '.$estado->value] = [$tipo->value, $estado->value,
                    in_array($estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO, EstadoTurnoEnum::EN_ATENCION], true)];
            }
        }

        return $casos;
    }

    public function test_excluye_el_propio_candidato_en_ambas_consultas(): void
    {
        $this->candidato->update(['inicio_at' => '2026-11-16 10:20:00', 'fin_at' => '2026-11-16 10:50:00']);
        $this->assertNotNull($this->validar());
        $this->assertSame('2026-11-16 10:20:00', $this->candidato->fresh()->inicio_at->toDateTimeString());
    }

    public function test_ignora_bloqueo_anulado_y_ocupaciones_ajenas_y_relee_cambios_de_agenda(): void
    {
        $otra = $this->profesionalUc07();
        $this->turno($otra, $this->pacienteUc07(), [
            'inicio_at' => '2026-11-16 10:00:00', 'fin_at' => '2026-11-16 11:00:00',
        ]);
        BloqueoAgenda::factory()->for($otra)->create([
            'inicio_at' => '2026-11-16 10:00:00', 'fin_at' => '2026-11-16 11:00:00',
        ]);
        $this->assertNotNull($this->validar());
        $bloqueo = BloqueoAgenda::factory()->for($this->profesional)->create([
            'inicio_at' => '2026-11-16 10:00:00', 'fin_at' => '2026-11-16 11:00:00',
        ]);
        $this->assertNull($this->validar());
        $bloqueo->forceFill(['anulado_at' => $this->fecha('2026-11-15 08:00:00'), 'motivo_anulacion' => 'Prueba'])->save();
        $this->assertNotNull($this->validar());
        $this->configuracion->franjasHorarias()->update(['hora_inicio' => '11:00:00']);
        $this->assertNull($this->validar());
    }

    #[DataProvider('configuracionesNoDisponibles')]
    public function test_rechaza_configuracion_ausente_anulada_o_fuera_de_vigencia(array $cambios): void
    {
        $this->configuracion->forceFill($cambios)->save();
        $this->assertNull($this->validar());
    }

    public static function configuracionesNoDisponibles(): array
    {
        return [
            [['vigencia_desde' => '2026-11-17']],
            [['vigencia_hasta' => '2026-11-15']],
            [['anulado_at' => '2026-11-15 08:00:00', 'motivo_anulacion' => 'Prueba']],
        ];
    }

    public function test_configuracion_ambigua_es_error_de_integridad_y_no_un_destino_valido(): void
    {
        ConfiguracionAgenda::factory()->for($this->profesional)->create(['vigencia_desde' => '2026-11-01']);
        $this->expectException(LogicException::class);
        $this->validar();
    }

    public function test_matriz_corrupta_no_se_interpreta_como_disponibilidad(): void
    {
        $this->configuracion->franjasHorarias()->update(['hora_fin' => '09:00:00']);
        $this->expectException(LogicException::class);
        $this->validar();
    }

    public function test_rechaza_candidato_de_seguimiento_y_otra_profesional(): void
    {
        $this->assertNull($this->validador->validar($this->candidato, $this->profesionalUc07(),
            $this->fecha('2026-11-16 10:10:00'), $this->fecha('2026-11-16 10:55:00')));
        DB::table('turnos')->where('id', $this->candidato->id)->update(['tipo' => 'SEGUIMIENTO']);
        $this->candidato->refresh();
        $this->assertNull($this->validar());
    }

    public function test_no_confunde_atributos_sucios_con_la_programacion_registrada(): void
    {
        $this->candidato->fill(['inicio_at' => '2026-11-23 10:00:00', 'fin_at' => '2026-11-23 14:00:00']);
        $antes = $this->candidato->getAttributes();
        $this->assertSame('10:40:00', $this->validar()['fin_at']->format('H:i:s'));
        $this->assertSame($antes, $this->candidato->getAttributes());
        $this->assertSame('2026-11-23 10:30:00', $this->candidato->fresh()->fin_at->toDateTimeString());
    }

    public function test_rechaza_modelos_no_persistidos_y_una_identidad_profesional_sucia(): void
    {
        $inicio = $this->fecha('2026-11-16 10:10:00');
        $fin = $this->fecha('2026-11-16 10:55:00');
        $this->assertNull($this->validador->validar($this->candidato->replicate(), $this->profesional, $inicio, $fin));
        $this->assertNull($this->validador->validar($this->candidato, new Kinesiologa, $inicio, $fin));
        $this->profesional->id = $this->profesionalUc07()->id;
        $this->assertNull($this->validar());
    }

    public function test_validar_es_solo_lectura_y_no_cambia_turnos_ni_crea_intenciones(): void
    {
        $antes = $this->candidato->fresh()->getAttributes();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertNotNull($this->validar());
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertNotEmpty($consultas);
        foreach ($consultas as $consulta) {
            $this->assertMatchesRegularExpression('/^select\b/i', ltrim($consulta['query']));
        }
        $this->assertSame($antes, $this->candidato->fresh()->getAttributes());
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
    }

    private function validar(string $inicio = '2026-11-16 10:10:00', string $fin = '2026-11-16 10:55:00'): ?array
    {
        return $this->validador->validar($this->candidato, $this->profesional, $this->fecha($inicio), $this->fecha($fin));
    }

    private function fecha(string $valor): CarbonImmutable
    {
        return CarbonImmutable::parse($valor, config('app.timezone'));
    }

    private function turno(Kinesiologa $profesional, Paciente $paciente, array $atributos): Turno
    {
        return Turno::factory()->for($profesional)->for($paciente)->create($atributos)->fresh();
    }
}
