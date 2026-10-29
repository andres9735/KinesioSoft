<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\DiaSemanaEnum;
use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Exceptions\Turnos\ProfesionalNoElegibleException;
use App\Models\BloqueoAgenda;
use App\Models\ColegioEmisor;
use App\Models\ConfiguracionAgenda;
use App\Models\EspecialidadKinesiologia;
use App\Models\FranjaHorariaAgenda;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\Paciente;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\RelojAplicacion;
use App\Services\Turnos\DisponibilidadPrimeraConsultaService;
use App\Services\Turnos\ElegibilidadKinesiologaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DisponibilidadPrimeraConsultaTest extends TestCase
{
    use RefreshDatabase;

    private DisponibilidadPrimeraConsultaService $service;

    private Kinesiologa $kinesiologa;

    private ConfiguracionAgenda $configuracion;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow($this->fecha('2026-10-05 09:00:00'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate(RoleEnum::KINESIOLOGO->value, 'web');
        $this->service = app(DisponibilidadPrimeraConsultaService::class);
        $this->kinesiologa = $this->profesional();
        $this->configuracion = $this->agenda($this->kinesiologa);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        DB::disableQueryLog();
        parent::tearDown();
    }

    public function test_eligible_professional_with_free_slots_returns_only_candidate_intervals_in_order(): void
    {
        $disponibles = $this->consultar();
        $this->assertSame(['10:00:00', '10:30:00'], $this->horas($disponibles));
        $this->assertSame(['inicio_at', 'fin_at'], array_keys($disponibles[0]));
        $this->assertSame('10:30:00', $disponibles[0]['fin_at']->format('H:i:s'));
        $this->assertSame(config('app.timezone'), $disponibles[0]['inicio_at']->timezoneName);
    }

    public function test_ineligible_professional_is_not_reported_as_an_empty_valid_window(): void
    {
        $this->kinesiologa->user->update(['activo' => false]);
        $this->expectException(ProfesionalNoElegibleException::class);
        $this->consultar();
    }

    public function test_eligible_professional_without_configuration_or_daily_bands_returns_empty(): void
    {
        $sinAgenda = $this->profesional();
        $this->assertSame([], $this->service->consultarDisponibilidad($sinAgenda, $this->fecha(), $this->fecha()));
        $this->configuracion->franjasHorarias()->where('dia_semana', DiaSemanaEnum::LUNES->value)->delete();
        $this->assertSame([], $this->consultar());
    }

    #[DataProvider('estadosCanonicos')]
    public function test_each_appointment_state_obeys_the_canonical_reserving_definition(EstadoTurnoEnum $estado, bool $ocupa): void
    {
        $this->turno('10:00:00', '10:30:00', $estado);
        $this->assertSame($ocupa ? ['10:30:00'] : ['10:00:00', '10:30:00'], $this->horas($this->consultar()));
        $this->assertSame(! $ocupa, $this->service->revalidarSlot($this->kinesiologa, $this->fecha('2026-10-05 10:00:00')) !== null);
    }

    public static function estadosCanonicos(): array
    {
        return [
            'Pendiente' => [EstadoTurnoEnum::PENDIENTE, true],
            'Confirmado' => [EstadoTurnoEnum::CONFIRMADO, true],
            'En atención' => [EstadoTurnoEnum::EN_ATENCION, true],
            'Atendido' => [EstadoTurnoEnum::ATENDIDO, false],
            'Ausente' => [EstadoTurnoEnum::AUSENTE, false],
            'Cancelado' => [EstadoTurnoEnum::CANCELADO, false],
        ];
    }

    #[DataProvider('intersecciones')]
    public function test_turns_and_blocks_use_semiclosed_intersections(string $fuente, string $inicio, string $fin, bool $libre): void
    {
        if ($fuente !== 'bloqueo') {
            $this->turno($inicio, $fin, $fuente === 'turno_en_atencion' ? EstadoTurnoEnum::EN_ATENCION : EstadoTurnoEnum::PENDIENTE);
        } else {
            $this->bloqueo($inicio, $fin);
        }

        $this->assertSame($libre, in_array('10:00:00', $this->horas($this->consultar()), true));
        $this->assertSame($libre, $this->service->revalidarSlot($this->kinesiologa, $this->fecha('2026-10-05 10:00:00')) !== null);
    }

    public static function intersecciones(): array
    {
        $casos = [];
        foreach (['turno', 'turno_en_atencion', 'bloqueo'] as $fuente) {
            foreach ([
                'contiguo anterior' => ['09:30:00', '10:00:00', true],
                'contiguo posterior' => ['10:30:00', '11:00:00', true],
                'parcial izquierda' => ['09:45:00', '10:15:00', false],
                'parcial derecha' => ['10:15:00', '10:45:00', false],
                'exacto' => ['10:00:00', '10:30:00', false],
                'contiene slot' => ['09:00:00', '12:00:00', false],
            ] as $nombre => $datos) {
                $casos[$fuente.' '.$nombre] = [$fuente, ...$datos];
            }
        }

        return $casos;
    }

    public function test_other_professionals_appointments_and_blocks_do_not_affect_availability(): void
    {
        $otra = $this->profesional();
        $this->turno('10:00:00', '11:00:00', kinesiologa: $otra);
        $this->bloqueo('10:00:00', '11:00:00', $otra);
        $this->assertSame(['10:00:00', '10:30:00'], $this->horas($this->consultar()));
    }

    public function test_future_effective_blocks_apply_even_when_not_currently_in_progress(): void
    {
        CarbonImmutable::setTestNow($this->fecha('2026-10-04 09:00:00'));
        $this->bloqueo('10:00:00', '11:00:00');
        $this->assertSame([], $this->consultar());
    }

    public function test_annulled_and_nonintersecting_historical_blocks_do_not_exclude_slots(): void
    {
        $anulado = $this->bloqueo('10:00:00', '11:00:00');
        $anulado->forceFill(['anulado_at' => $this->fecha('2026-10-04 12:00:00'), 'motivo_anulacion' => 'Prueba'])->save();
        $this->bloqueo('07:00:00', '08:00:00');
        $this->assertSame(['10:00:00', '10:30:00'], $this->horas($this->consultar()));
    }

    public function test_multiple_overlapping_blocks_do_not_reveal_reasons_or_shift_grid(): void
    {
        $this->bloqueo('10:10:00', '10:20:00');
        $this->bloqueo('10:05:00', '10:15:00');
        $this->assertSame(['10:30:00'], $this->horas($this->consultar()));
        $this->assertSame(['inicio_at', 'fin_at'], array_keys($this->consultar()[0]));
    }

    public function test_same_day_rejects_past_and_exact_start_and_accepts_next_second_without_buffer(): void
    {
        $this->configuracion->franjasHorarias()->update(['hora_inicio' => '09:30:00']);
        CarbonImmutable::setTestNow($this->fecha('2026-10-05 10:00:00'));
        $this->assertSame(['10:30:00'], $this->horas($this->consultar()));
        $this->assertNull($this->service->revalidarSlot($this->kinesiologa, $this->fecha('2026-10-05 10:00:00')));
        CarbonImmutable::setTestNow($this->fecha('2026-10-05 09:59:59'));
        $this->assertSame(['10:00:00', '10:30:00'], $this->horas($this->consultar()));
        $this->assertSame([], $this->consultar('2026-10-04', '2026-10-04'));
    }

    public function test_future_and_distant_windows_have_no_absolute_booking_horizon(): void
    {
        $this->assertCount(2, $this->consultar('2026-10-06', '2026-10-06'));
        $lejana = $this->consultar('2099-12-25', '2099-12-31');
        $this->assertCount(14, $lejana);
        $this->assertSame('2099-12-25', $lejana[0]['inicio_at']->toDateString());
        $this->assertSame('2099-12-31', $lejana[13]['inicio_at']->toDateString());
    }

    public function test_maximum_window_is_31_inclusive_calendar_dates(): void
    {
        $this->assertSame(31, config('turnos.disponibilidad.max_dias_por_consulta'));
        $this->assertCount(62, $this->consultar('2026-10-05', '2026-11-04'));
    }

    #[DataProvider('rangosInvalidos')]
    public function test_invalid_windows_are_rejected_as_arguments(string $desde, string $hasta): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->consultar($desde, $hasta);
    }

    public static function rangosInvalidos(): array
    {
        return ['32 fechas' => ['2026-10-05', '2026-11-05'], 'orden inverso' => ['2026-10-06', '2026-10-05']];
    }

    public function test_window_size_is_read_from_central_configuration(): void
    {
        config(['turnos.disponibilidad.max_dias_por_consulta' => 2]);
        $this->assertCount(4, $this->consultar('2026-10-05', '2026-10-06'));
        $this->expectException(InvalidArgumentException::class);
        $this->consultar('2026-10-05', '2026-10-07');
    }

    #[DataProvider('iniciosFueraDeGrilla')]
    public function test_revalidation_rejects_exactly_non_grid_starts_without_rounding(string $inicio): void
    {
        $this->configuracion->update(['duracion_turno_minutos' => 45]);
        $this->configuracion->franjasHorarias()->update(['hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00']);
        foreach (DiaSemanaEnum::cases() as $dia) {
            FranjaHorariaAgenda::factory()->for($this->configuracion, 'configuracionAgenda')->create([
                'dia_semana' => $dia, 'hora_inicio' => '13:00:00', 'hora_fin' => '14:00:00',
            ]);
        }
        $this->assertNull($this->service->revalidarSlot($this->kinesiologa, $this->fecha($inicio)));
    }

    public static function iniciosFueraDeGrilla(): array
    {
        return [
            'entre inicios' => ['2026-10-05 10:15:00'],
            'no alineado' => ['2026-10-05 10:30:00'],
            'remanente' => ['2026-10-05 11:30:00'],
            'gap' => ['2026-10-05 12:30:00'],
            'sin configuracion' => ['2026-09-28 10:00:00'],
            'precision exacta' => ['2026-10-05 10:00:00.000001'],
        ];
    }

    public function test_revalidation_rejects_band_from_another_day_or_previous_duration(): void
    {
        $this->configuracion->franjasHorarias()->where('dia_semana', DiaSemanaEnum::MARTES->value)->delete();
        $this->assertNull($this->service->revalidarSlot($this->kinesiologa, $this->fecha('2026-10-06 10:00:00')));
        $this->configuracion->update(['vigencia_hasta' => '2026-10-11']);
        $nueva = $this->agenda($this->kinesiologa, desde: '2026-10-12', duracion: 60);
        $this->assertNull($this->service->revalidarSlot($this->kinesiologa, $this->fecha('2026-10-12 10:30:00')));
        $this->assertSame('11:00:00', $this->service->revalidarSlot($this->kinesiologa, $this->fecha('2026-10-12 10:00:00'))['fin_at']->format('H:i:s'));
    }

    public function test_revalidation_derives_end_from_current_configuration_with_only_a_start_input(): void
    {
        $inicio = $this->fecha('2026-10-05 10:00:00');
        $this->assertSame('10:30:00', $this->service->revalidarSlot($this->kinesiologa, $inicio)['fin_at']->format('H:i:s'));
        $this->configuracion->update(['duracion_turno_minutos' => 60]);
        $this->assertSame('11:00:00', $this->service->revalidarSlot($this->kinesiologa, $inicio)['fin_at']->format('H:i:s'));
        $this->assertSame('10:00:00', $inicio->format('H:i:s'));
        $this->assertNotNull($this->service->revalidarSlot($this->kinesiologa, $inicio->setTimezone('UTC')));
    }

    public function test_slot_lost_to_an_appointment_is_rejected_and_cancellation_releases_it(): void
    {
        $inicio = $this->fecha('2026-10-05 10:00:00');
        $this->assertNotNull($this->service->revalidarSlot($this->kinesiologa, $inicio));
        $turno = $this->turno('10:00:00', '10:30:00');
        $this->assertNull($this->service->revalidarSlot($this->kinesiologa, $inicio));
        $turno->estado = EstadoTurnoEnum::CANCELADO;
        $turno->save();
        $this->assertNotNull($this->service->revalidarSlot($this->kinesiologa, $inicio));
    }

    public function test_slot_lost_to_a_block_returns_after_annulment(): void
    {
        $inicio = $this->fecha('2026-10-05 10:00:00');
        $this->assertNotNull($this->service->revalidarSlot($this->kinesiologa, $inicio));
        $bloqueo = $this->bloqueo('10:00:00', '10:30:00');
        $this->assertNull($this->service->revalidarSlot($this->kinesiologa, $inicio));
        $bloqueo->forceFill(['anulado_at' => $this->fecha('2026-10-05 09:15:00'), 'motivo_anulacion' => 'Prueba'])->save();
        $this->assertNotNull($this->service->revalidarSlot($this->kinesiologa, $inicio));
    }

    public function test_agenda_and_clock_changes_invalidate_a_previously_displayed_start(): void
    {
        $inicio = $this->fecha('2026-10-05 10:00:00');
        $this->assertContains('10:00:00', $this->horas($this->consultar()));
        $this->configuracion->franjasHorarias()->update(['hora_inicio' => '10:10:00', 'hora_fin' => '11:10:00']);
        $this->assertNull($this->service->revalidarSlot($this->kinesiologa, $inicio));
        $nuevoInicio = $this->fecha('2026-10-05 10:10:00');
        $this->assertNotNull($this->service->revalidarSlot($this->kinesiologa, $nuevoInicio));
        CarbonImmutable::setTestNow($nuevoInicio);
        $this->assertNull($this->service->revalidarSlot($this->kinesiologa, $nuevoInicio));
    }

    public function test_eligibility_is_reloaded_even_when_the_caller_holds_preloaded_relations(): void
    {
        $this->kinesiologa->load(['user.roles', 'user.kinesiologa.matriculasProfesionales', 'user.kinesiologa.especialidades']);
        $this->assertNotEmpty($this->consultar());
        User::findOrFail($this->kinesiologa->user_id)->update(['activo' => false]);
        $this->assertTrue($this->kinesiologa->user->activo);
        $this->expectException(ProfesionalNoElegibleException::class);
        $this->service->revalidarSlot($this->kinesiologa, $this->fecha('2026-10-05 10:00:00'));
    }

    public function test_occupancy_and_block_queries_remain_one_each_with_few_or_many_candidates(): void
    {
        $this->configuracion->franjasHorarias()->update(['hora_fin' => '10:30:00']);
        [$pocos, $consultasPocos] = $this->medir();
        $this->configuracion->update(['duracion_turno_minutos' => 15]);
        $this->configuracion->franjasHorarias()->update(['hora_inicio' => '09:00:00', 'hora_fin' => '21:00:00']);
        [$muchos, $consultasMuchos] = $this->medir();

        $this->assertCount(1, $pocos);
        $this->assertCount(47, $muchos);
        $this->assertSame(['turnos' => 1, 'bloqueos_agenda' => 1], $consultasPocos);
        $this->assertSame($consultasPocos, $consultasMuchos);
    }

    public function test_loaded_sources_share_the_same_evaluation_without_hidden_queries_or_slot_persistence(): void
    {
        $this->turno('10:00:00', '10:30:00');
        $agenda = app(AgendaQueryService::class);
        $desde = $this->fecha();
        $hasta = $desde->addDay();
        $configuracion = $agenda->configuracionAplicableEn($this->kinesiologa, $desde);
        $queryTurnos = $agenda->turnosAfectadosPorIntervalo($this->kinesiologa, $desde, $hasta);
        $queryBloqueos = $agenda->bloqueosSuperpuestosCon($this->kinesiologa, $desde, $hasta);
        $this->assertInstanceOf(Builder::class, $queryTurnos);
        $this->assertInstanceOf(Builder::class, $queryBloqueos);
        $turnos = $queryTurnos->get();
        $bloqueos = $queryBloqueos->get();
        $elegible = app(ElegibilidadKinesiologaService::class)->esElegible($this->kinesiologa->fresh());
        $ahora = app(RelojAplicacion::class)->ahora();
        $esperados = $this->consultar();
        DB::enableQueryLog();
        DB::flushQueryLog();

        $grilla = $this->service->generarGrillaDesdeConfiguracion($configuracion, $desde);
        $evaluados = $this->service->evaluarCandidatosDisponibles($grilla, $turnos, $bloqueos, $ahora, $elegible);

        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertEquals($esperados, $evaluados);
        foreach ($esperados as $candidato) {
            $this->assertEquals($candidato, $this->service->revalidarSlot($this->kinesiologa, $candidato['inicio_at']));
        }
        $this->assertFalse(Schema::hasTable('slots'));
        $this->assertFalse(Schema::hasTable('disponibilidades'));
        $this->assertDatabaseCount('turnos', 1);
    }

    public function test_pure_evaluation_distinguishes_ineligible_authoritative_input(): void
    {
        $this->expectException(ProfesionalNoElegibleException::class);
        $this->service->evaluarCandidatosDisponibles([], [], [], $this->fecha(), false);
    }

    public function test_loaded_grid_api_refuses_to_lazily_fetch_bands(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('franjas deben estar cargadas');
        $this->service->generarGrillaDesdeConfiguracion($this->configuracion->fresh(), $this->fecha());
    }

    public function test_internal_agenda_corruption_is_not_converted_into_empty_availability(): void
    {
        $this->agenda($this->kinesiologa);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('más de una configuración');
        $this->consultar();
    }

    private function fecha(string $valor = '2026-10-05'): CarbonImmutable
    {
        return CarbonImmutable::parse($valor, config('app.timezone'));
    }

    private function consultar(string $desde = '2026-10-05', string $hasta = '2026-10-05'): array
    {
        return $this->service->consultarDisponibilidad($this->kinesiologa, $this->fecha($desde), $this->fecha($hasta));
    }

    private function horas(array $candidatos): array
    {
        return array_map(fn (array $candidato) => $candidato['inicio_at']->format('H:i:s'), $candidatos);
    }

    private function medir(): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $resultado = $this->consultar();
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();
        $cantidades = ['turnos' => 0, 'bloqueos_agenda' => 0];
        foreach ($consultas as $consulta) {
            foreach (array_keys($cantidades) as $tabla) {
                if (preg_match('/\bfrom\s+["`]?'.preg_quote($tabla, '/').'["`]?\b/i', $consulta['query'])) {
                    $cantidades[$tabla]++;
                }
            }
        }

        return [$resultado, $cantidades];
    }

    private function agenda(Kinesiologa $kinesiologa, string $desde = '2026-10-01', int $duracion = 30): ConfiguracionAgenda
    {
        $configuracion = ConfiguracionAgenda::factory()->for($kinesiologa)->create([
            'vigencia_desde' => $desde, 'duracion_turno_minutos' => $duracion,
        ]);
        foreach (DiaSemanaEnum::cases() as $dia) {
            FranjaHorariaAgenda::factory()->for($configuracion, 'configuracionAgenda')->create([
                'dia_semana' => $dia, 'hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00',
            ]);
        }

        return $configuracion;
    }

    private function turno(string $inicio, string $fin, EstadoTurnoEnum $estado = EstadoTurnoEnum::PENDIENTE, ?Kinesiologa $kinesiologa = null): Turno
    {
        $paciente = Paciente::create(['user_id' => User::factory()->create()->id, 'activo' => true]);

        return Turno::factory()->for($kinesiologa ?? $this->kinesiologa)->for($paciente)->create([
            'inicio_at' => $this->fecha('2026-10-05 '.$inicio), 'fin_at' => $this->fecha('2026-10-05 '.$fin), 'estado' => $estado,
        ]);
    }

    private function bloqueo(string $inicio, string $fin, ?Kinesiologa $kinesiologa = null): BloqueoAgenda
    {
        return BloqueoAgenda::factory()->for($kinesiologa ?? $this->kinesiologa)->create([
            'inicio_at' => $this->fecha('2026-10-05 '.$inicio), 'fin_at' => $this->fecha('2026-10-05 '.$fin),
        ]);
    }

    private function profesional(): Kinesiologa
    {
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-disponibilidad'], ['nombre' => 'Título', 'activo' => true]);
        $especialidad = EspecialidadKinesiologia::firstOrCreate(['slug' => 'especialidad-disponibilidad'], ['nombre' => 'Especialidad', 'activo' => true]);
        $jurisdiccion = Jurisdiccion::firstOrCreate(['slug' => 'jurisdiccion-disponibilidad'], ['nombre' => 'Jurisdicción', 'activo' => true]);
        $colegio = ColegioEmisor::firstOrCreate(['sigla' => 'DISP'], [
            'nombre' => 'Colegio', 'jurisdiccion_id' => $jurisdiccion->id, 'activo' => true,
        ]);
        $user = User::factory()->create([
            'dni' => fake()->unique()->numerify('########'), 'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino', 'telefono' => '3764123456', 'activo' => true,
        ]);
        $user->assignRole(RoleEnum::KINESIOLOGO->value);
        $kinesiologa = Kinesiologa::create(['user_id' => $user->id, 'titulo_profesional_id' => $titulo->id]);
        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $kinesiologa->id, 'nro_matricula_profesional' => 'MP-'.$user->id,
            'jurisdiccion_id' => $jurisdiccion->id, 'colegio_emisor_id' => $colegio->id, 'activa' => true,
        ]);
        $kinesiologa->especialidades()->attach($especialidad);

        return $kinesiologa->fresh();
    }
}
