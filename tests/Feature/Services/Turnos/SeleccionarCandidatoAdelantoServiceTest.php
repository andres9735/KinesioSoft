<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\DiaSemanaEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\BloqueoAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Services\Turnos\SeleccionarCandidatoAdelantoService;
use App\Services\Turnos\ValidarDestinoAdelantoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class SeleccionarCandidatoAdelantoServiceTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Kinesiologa $profesional;

    private SeleccionarCandidatoAdelantoService $selector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->profesional = $this->profesionalUc07();
        $this->selector = app(SeleccionarCandidatoAdelantoService::class);
    }

    #[DataProvider('estadosElegibles')]
    public function test_selecciona_ambos_estados_para_cupos_de_ambos_tipos(string $estado, string $tipoCupo): void
    {
        $cancelado = Turno::factory()->for($this->profesional)->for($this->pacienteUc07())->create([
            'tipo' => $tipoCupo, 'estado' => 'CANCELADO',
            'inicio_at' => $this->fecha('2026-11-16 10:10:00'), 'fin_at' => $this->fecha('2026-11-16 10:55:00'),
        ])->fresh();
        $antes = $cancelado->getAttributes();
        $candidato = $this->turno('2026-11-20 11:00:00', ['estado' => $estado]);

        $elegido = $this->selector->seleccionar($this->profesional, $cancelado->inicio_at, $cancelado->fin_at);

        $this->assertSame($candidato->id, $elegido?->id);
        $this->assertSame($estado, $elegido->estado->value);
        $this->assertSame($antes, $cancelado->fresh()->getAttributes());
    }

    public static function estadosElegibles(): array
    {
        return [
            ['PENDIENTE', 'PRIMERA_CONSULTA'], ['CONFIRMADO', 'PRIMERA_CONSULTA'],
            ['PENDIENTE', 'SEGUIMIENTO'], ['CONFIRMADO', 'SEGUIMIENTO'],
        ];
    }

    #[DataProvider('estadosNoElegibles')]
    public function test_omite_estados_no_elegibles_y_continua(string $estado): void
    {
        $this->turno('2026-11-22 11:00:00', ['estado' => $estado]);
        $valido = $this->turno('2026-11-20 11:00:00');
        $this->assertSame($valido->id, $this->seleccionar()?->id);
    }

    public static function estadosNoElegibles(): array
    {
        return [['EN_ATENCION'], ['ATENDIDO'], ['AUSENTE'], ['CANCELADO']];
    }

    public function test_omite_seguimiento_y_otra_profesional_aunque_tengan_mayor_prioridad(): void
    {
        $this->turno('2026-11-22 12:00:00', ['tipo' => TipoTurnoEnum::SEGUIMIENTO]);
        $this->turno('2026-11-22 13:00:00', profesional: $this->profesionalUc07());
        $valido = $this->turno('2026-11-20 11:00:00');
        $this->assertSame($valido->id, $this->seleccionar()?->id);
    }

    #[DataProvider('limitesVentana')]
    public function test_ventana_es_estricta_en_inicio_e_inclusiva_a_siete_dias(string $inicio, bool $incluido): void
    {
        $turno = $this->turno($inicio);
        $this->assertSame($incluido ? $turno->id : null, $this->seleccionar()?->id);
    }

    public static function limitesVentana(): array
    {
        return [
            'anterior' => ['2026-11-16 10:09:59', false],
            'igual al inicio' => ['2026-11-16 10:10:00', false],
            'un segundo posterior' => ['2026-11-16 10:10:01', true],
            'siete dias exactos' => ['2026-11-23 10:10:00', true],
            'un segundo fuera' => ['2026-11-23 10:10:01', false],
            'ocho dias' => ['2026-11-24 10:00:00', false],
        ];
    }

    public function test_la_ventana_cruza_semana_y_usa_el_instante_en_zona_local(): void
    {
        $configuracion = $this->profesional->configuracionesAgenda()->sole();
        FranjaHorariaAgenda::factory()->for($configuracion, 'configuracionAgenda')->create([
            'dia_semana' => DiaSemanaEnum::DOMINGO, 'hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00',
        ]);
        $candidato = $this->turno('2026-11-16 10:30:00');
        $inicio = $this->fecha('2026-11-15 10:10:00');
        $elegido = $this->selector->seleccionar($this->profesional, $inicio->utc(), $inicio->addMinutes(45)->utc());
        $this->assertSame($candidato->id, $elegido?->id);
    }

    public function test_prioriza_inicio_mas_lejano_por_encima_de_fecha_de_registro(): void
    {
        $this->turno('2026-11-20 11:00:00', ['created_at' => '2026-10-01 08:00:00']);
        $lejano = $this->turno('2026-11-22 11:00:00', ['created_at' => '2026-11-01 08:00:00']);
        $this->assertSame($lejano->id, $this->seleccionar()?->id);
    }

    public function test_desempata_por_registro_mas_temprano_aunque_tenga_id_mayor(): void
    {
        $this->turno('2026-11-22 11:00:00', ['created_at' => '2026-11-01 08:00:00']);
        $temprano = $this->turno('2026-11-22 11:00:00', ['created_at' => '2026-10-01 08:00:00']);
        $this->assertSame($temprano->id, $this->seleccionar()?->id);
    }

    public function test_desempata_por_id_menor_cuando_inicio_y_registro_coinciden(): void
    {
        $primero = $this->turno('2026-11-22 11:00:00', ['created_at' => '2026-10-01 08:00:00']);
        $segundo = $this->turno('2026-11-22 11:00:00', ['created_at' => '2026-10-01 08:00:00']);
        $this->assertLessThan($segundo->id, $primero->id);
        $this->assertSame($primero->id, $this->seleccionar()?->id);
    }

    #[DataProvider('correosInvalidos')]
    public function test_omite_correo_ausente_o_invalido_y_continua(string $correo): void
    {
        $paciente = $this->pacienteUc07();
        $paciente->user->update(['email' => $correo]);
        $this->turno('2026-11-22 11:00:00', paciente: $paciente);
        $valido = $this->turno('2026-11-20 11:00:00');
        $this->assertSame($valido->id, $this->seleccionar()?->id);
    }

    public static function correosInvalidos(): array
    {
        return [[''], ['sin-arroba'], ['paciente@'], [' paciente@example.test'], ["paciente@example.test\r\nBcc:otro@example.test"]];
    }

    public function test_admite_correo_ficticio_valido_sin_verificacion_ni_envio(): void
    {
        $paciente = $this->pacienteUc07();
        $paciente->user->update(['email' => 'paciente@example.test', 'email_verified_at' => null]);
        $turno = $this->turno('2026-11-20 11:00:00', paciente: $paciente);
        $this->assertSame($turno->id, $this->seleccionar()?->id);
    }

    public function test_excluye_por_paciente_todos_sus_turnos_y_no_por_id_de_turno(): void
    {
        $paciente = $this->pacienteUc07();
        $this->turno('2026-11-22 11:00:00', paciente: $paciente);
        $this->turno('2026-11-21 11:00:00', paciente: $paciente);
        $otro = $this->turno('2026-11-20 11:00:00');

        $this->assertSame($otro->id, $this->seleccionar([$paciente->id])?->id);
        $this->assertNull($this->seleccionar([$paciente->id, $otro->paciente_id]));
        $this->assertNotSame($otro->id, $this->seleccionar()?->id);
    }

    public function test_omite_duracion_que_no_cabe_y_reutiliza_validador_para_el_siguiente(): void
    {
        $invalido = $this->turno('2026-11-22 11:00:00', ['fin_at' => '2026-11-22 12:00:00']);
        $valido = $this->turno('2026-11-20 11:00:00');
        $this->assertNull(app(ValidarDestinoAdelantoService::class)->validar($invalido, $this->profesional,
            $this->fecha('2026-11-16 10:10:00'), $this->fecha('2026-11-16 10:55:00')));
        $this->assertSame($valido->id, $this->seleccionar()?->id);
    }

    public function test_omite_cruce_del_paciente_con_otra_profesional_y_continua(): void
    {
        $paciente = $this->pacienteUc07();
        $this->turno('2026-11-22 11:00:00', paciente: $paciente);
        $this->turno('2026-11-16 10:20:00', ['tipo' => TipoTurnoEnum::SEGUIMIENTO], $paciente, $this->profesionalUc07());
        $valido = $this->turno('2026-11-20 11:00:00');
        $this->assertSame($valido->id, $this->seleccionar()?->id);
    }

    public function test_bloqueo_del_destino_agota_la_busqueda_sin_seleccionar(): void
    {
        $this->turno('2026-11-22 11:00:00');
        $this->turno('2026-11-20 11:00:00');
        BloqueoAgenda::factory()->for($this->profesional)->create([
            'inicio_at' => '2026-11-16 10:00:00', 'fin_at' => '2026-11-16 11:00:00',
        ]);
        $this->assertNull($this->seleccionar());
    }

    public function test_no_convierte_corrupcion_de_agenda_en_ausencia_normal_de_candidatos(): void
    {
        $this->turno('2026-11-20 11:00:00');
        $this->profesional->configuracionesAgenda()->create([
            'vigencia_desde' => '2026-11-01', 'duracion_turno_minutos' => 30,
        ]);
        $this->expectException(LogicException::class);
        $this->seleccionar();
    }

    public function test_devuelve_null_sin_turnos_o_con_todos_excluidos(): void
    {
        $this->assertNull($this->seleccionar());
        $turno = $this->turno('2026-11-20 11:00:00');
        $this->assertNull($this->seleccionar([$turno->paciente_id]));
    }

    public function test_relee_correo_y_programacion_en_cada_seleccion(): void
    {
        $lejano = $this->turno('2026-11-22 11:00:00');
        $otro = $this->turno('2026-11-20 11:00:00');
        $this->assertSame($lejano->id, $this->seleccionar()?->id);
        $lejano->paciente->user->update(['email' => 'invalido']);
        $this->assertSame($otro->id, $this->seleccionar()?->id);
        $otro->update(['inicio_at' => '2026-11-24 11:00:00', 'fin_at' => '2026-11-24 11:30:00']);
        $this->assertNull($this->seleccionar());
    }

    public function test_seleccionar_solo_ejecuta_select_y_no_modifica_turnos_ni_crea_intenciones(): void
    {
        $this->turno('2026-11-22 11:00:00', ['fin_at' => '2026-11-22 12:00:00']);
        $valido = $this->turno('2026-11-20 11:00:00');
        $antes = Turno::orderBy('id')->get()->map->getAttributes()->all();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($valido->id, $this->seleccionar()?->id);
        $this->assertNull($this->seleccionar([$valido->paciente_id]));
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertNotEmpty($consultas);
        foreach ($consultas as $consulta) {
            $this->assertMatchesRegularExpression('/^select\b/i', ltrim($consulta['query']));
        }
        $this->assertSame($antes, Turno::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
    }

    #[DataProvider('exclusionesInvalidas')]
    public function test_no_admite_exclusiones_sin_identidad_de_paciente_valida(mixed $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->seleccionar([$id]);
    }

    public static function exclusionesInvalidas(): array
    {
        return [[0], [-1], ['1'], [null]];
    }

    public function test_no_consulta_con_profesional_no_persistida_o_identidad_sucia_o_cupo_invalido(): void
    {
        $inicio = $this->fecha('2026-11-16 10:10:00');
        $fin = $inicio->addMinutes(45);
        $this->assertNull($this->selector->seleccionar(new Kinesiologa, $inicio, $fin));
        $this->assertNull($this->selector->seleccionar($this->profesional, $inicio, $inicio));
        $this->assertNull($this->selector->seleccionar($this->profesional, $fin, $inicio));
        $this->profesional->id = $this->profesionalUc07()->id;
        $this->assertNull($this->selector->seleccionar($this->profesional, $inicio, $fin));
    }

    private function seleccionar(array $excluidos = []): ?Turno
    {
        return $this->selector->seleccionar($this->profesional,
            $this->fecha('2026-11-16 10:10:00'), $this->fecha('2026-11-16 10:55:00'), $excluidos);
    }

    private function turno(string $inicio, array $atributos = [], ?Paciente $paciente = null, ?Kinesiologa $profesional = null): Turno
    {
        return Turno::factory()->for($profesional ?? $this->profesional)->for($paciente ?? $this->pacienteUc07())->create([
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'estado' => 'PENDIENTE',
            'inicio_at' => $this->fecha($inicio), 'fin_at' => $this->fecha($inicio)->addMinutes(30),
            'motivo_solicitud' => 'Motivo de prueba', ...$atributos,
        ])->fresh();
    }

    private function fecha(string $valor): CarbonImmutable
    {
        return CarbonImmutable::parse($valor, config('app.timezone'));
    }
}
