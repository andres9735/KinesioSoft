<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\DiaSemanaEnum;
use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
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
use App\Services\Turnos\DisponibilidadAgregadaPrimeraConsultaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DisponibilidadAgregadaPrimeraConsultaTest extends TestCase
{
    use RefreshDatabase;

    private DisponibilidadAgregadaPrimeraConsultaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow($this->fecha('2026-10-05 09:00:00'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate(RoleEnum::KINESIOLOGO->value, 'web');
        $this->service = app(DisponibilidadAgregadaPrimeraConsultaService::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        DB::disableQueryLog();
        DB::flushQueryLog();
        parent::tearDown();
    }

    public function test_no_eligible_professionals_returns_empty(): void
    {
        $this->assertSame([], $this->consultar());
    }

    public function test_eligible_professionals_without_configuration_or_daily_bands_do_not_create_dates(): void
    {
        $this->profesional();
        $this->agenda($this->profesional(), dias: [DiaSemanaEnum::MARTES]);

        $this->assertSame([], $this->consultar());
    }

    public function test_single_professional_preserves_exact_intervals_timezone_and_minimal_metadata(): void
    {
        $profesional = $this->profesional();
        $this->agenda($profesional);

        $dias = $this->consultar();

        $this->assertCount(1, $dias);
        $this->assertSame(['fecha', 'horarios'], array_keys($dias[0]));
        $this->assertSame('2026-10-05', $dias[0]['fecha']);
        $this->assertSame(['10:00:00', '10:30:00'], $this->horas($dias[0]));
        foreach ($dias[0]['horarios'] as $horario) {
            $this->assertSame(['inicio_at', 'profesionales'], array_keys($horario));
            $this->assertCount(1, $horario['profesionales']);
            $contribucion = $horario['profesionales'][0];
            $this->assertSame(['kinesiologa_id', 'fin_at'], array_keys($contribucion));
            $this->assertSame($profesional->id, $contribucion['kinesiologa_id']);
            $this->assertInstanceOf(CarbonImmutable::class, $horario['inicio_at']);
            $this->assertInstanceOf(CarbonImmutable::class, $contribucion['fin_at']);
            $this->assertSame(config('app.timezone'), $horario['inicio_at']->timezoneName);
            $this->assertSame(config('app.timezone'), $contribucion['fin_at']->timezoneName);
            $this->assertTrue($horario['inicio_at']->addMinutes(30)->equalTo($contribucion['fin_at']));
        }
    }

    public function test_professionals_on_different_dates_contribute_only_the_union_of_available_dates(): void
    {
        $martes = $this->profesional();
        $lunes = $this->profesional();
        $this->agenda($martes, dias: [DiaSemanaEnum::MARTES]);
        $this->agenda($lunes);
        $this->profesional();

        $dias = $this->consultar('2026-10-05', '2026-10-07');

        $this->assertSame(['2026-10-05', '2026-10-06'], array_column($dias, 'fecha'));
        $this->assertSame([$lunes->id], array_column($dias[0]['horarios'][0]['profesionales'], 'kinesiologa_id'));
        $this->assertSame([$martes->id], array_column($dias[1]['horarios'][0]['profesionales'], 'kinesiologa_id'));
    }

    public function test_matching_starts_are_deduplicated_without_duplicating_professionals(): void
    {
        $primera = $this->profesional();
        $segunda = $this->profesional();
        $this->agenda($primera);
        $this->agenda($segunda);

        $dia = $this->consultar()[0];

        $this->assertSame(['10:00:00', '10:30:00'], $this->horas($dia));
        foreach ($dia['horarios'] as $horario) {
            $ids = array_column($horario['profesionales'], 'kinesiologa_id');
            $this->assertSame([$primera->id, $segunda->id], $ids);
            $this->assertSame($ids, array_values(array_unique($ids)));
            $this->assertSame(array_fill(0, 2, $horario['inicio_at']->addMinutes(30)->toDateTimeString()), array_map(
                fn (array $contribucion): string => $contribucion['fin_at']->toDateTimeString(),
                $horario['profesionales'],
            ));
        }
    }

    public function test_shared_start_with_different_durations_has_no_universal_end(): void
    {
        $treinta = $this->profesional();
        $cuarentaYCinco = $this->profesional();
        $this->agenda($treinta, fin: '10:45:00');
        $this->agenda($cuarentaYCinco, duracion: 45, fin: '10:45:00');

        $dia = $this->consultar()[0];

        $this->assertSame(['10:00:00'], $this->horas($dia));
        $horario = $dia['horarios'][0];
        $this->assertArrayNotHasKey('fin_at', $horario);
        $this->assertSame([$treinta->id, $cuarentaYCinco->id], array_column($horario['profesionales'], 'kinesiologa_id'));
        $this->assertSame(['10:30:00', '10:45:00'], array_map(
            fn (array $contribucion): string => $contribucion['fin_at']->format('H:i:s'),
            $horario['profesionales'],
        ));
    }

    public function test_independent_grids_are_united_without_rounding_or_fabricating_slots(): void
    {
        $treinta = $this->profesional();
        $cuarentaYCinco = $this->profesional();
        $this->agenda($treinta, fin: '12:15:00');
        $this->agenda($cuarentaYCinco, duracion: 45, fin: '12:15:00');

        $dia = $this->consultar()[0];

        $this->assertSame(['10:00:00', '10:30:00', '10:45:00', '11:00:00', '11:30:00'], $this->horas($dia));
        $this->assertSame([
            [$treinta->id, $cuarentaYCinco->id], [$treinta->id], [$cuarentaYCinco->id],
            [$treinta->id], [$treinta->id, $cuarentaYCinco->id],
        ], array_map(fn (array $horario): array => array_column($horario['profesionales'], 'kinesiologa_id'), $dia['horarios']));
    }

    #[DataProvider('condicionesNoElegibles')]
    public function test_ineligible_professional_with_physical_agenda_does_not_contribute(string $condicion): void
    {
        $excluida = $this->profesional();
        $this->agenda($excluida);
        match ($condicion) {
            'inactiva' => $excluida->user->update(['activo' => false]),
            'sin_verificar' => $excluida->user->forceFill(['email_verified_at' => null])->save(),
            'sin_rol' => $excluida->user->syncRoles([]),
            'sin_matricula_activa' => $excluida->matriculasProfesionales()->update(['activa' => false]),
        };

        $this->assertSame([], $this->consultar());
        $valida = $this->profesional();
        $this->agenda($valida);

        foreach ($this->consultar()[0]['horarios'] as $horario) {
            $this->assertSame([$valida->id], array_column($horario['profesionales'], 'kinesiologa_id'));
        }
    }

    public static function condicionesNoElegibles(): array
    {
        return [['inactiva'], ['sin_verificar'], ['sin_rol'], ['sin_matricula_activa']];
    }

    public function test_eligibility_lost_after_enumeration_excludes_only_that_professional(): void
    {
        $excluida = $this->profesional();
        $valida = $this->profesional();
        $this->agenda($excluida);
        $this->agenda($valida);
        $revocada = false;
        DB::listen(function (QueryExecuted $consulta) use ($excluida, &$revocada): void {
            if (! $revocada && str_contains($consulta->sql, 'from "kinesiologas" where "id" = ? limit 1')
                && $consulta->bindings === [$excluida->id]) {
                $revocada = true;
                User::whereKey($excluida->user_id)->update(['activo' => false]);
            }
        });

        $dias = $this->consultar();

        $this->assertTrue($revocada);
        foreach ($dias[0]['horarios'] as $horario) {
            $this->assertSame([$valida->id], array_column($horario['profesionales'], 'kinesiologa_id'));
        }
    }

    public function test_same_day_keeps_only_strictly_future_starts_using_the_canonical_clock(): void
    {
        $this->agenda($this->profesional(), inicio: '09:30:00');
        CarbonImmutable::setTestNow($this->fecha('2026-10-05 10:00:00'));

        $this->assertSame(['10:30:00'], $this->horas($this->consultar()[0]));

        CarbonImmutable::setTestNow($this->fecha('2026-10-05 09:59:59'));
        $this->assertSame(['10:00:00', '10:30:00'], $this->horas($this->consultar()[0]));
        CarbonImmutable::setTestNow($this->fecha('2026-10-05 11:00:00'));
        $this->assertSame([], $this->consultar());
    }

    #[DataProvider('ocupaciones')]
    public function test_occupied_or_blocked_professional_is_excluded_but_free_professional_keeps_start(string $fuente): void
    {
        $ocupada = $this->profesional();
        $libre = $this->profesional();
        $this->agenda($ocupada);
        $this->agenda($libre);
        if ($fuente === 'bloqueo') {
            BloqueoAgenda::factory()->for($ocupada)->create([
                'inicio_at' => $this->fecha('2026-10-05 10:00:00'),
                'fin_at' => $this->fecha('2026-10-05 10:30:00'),
            ]);
        } else {
            $paciente = Paciente::create(['user_id' => User::factory()->create()->id, 'activo' => true]);
            Turno::factory()->for($ocupada)->for($paciente)->create([
                'inicio_at' => $this->fecha('2026-10-05 10:00:00'),
                'fin_at' => $this->fecha('2026-10-05 10:30:00'),
                'estado' => EstadoTurnoEnum::from($fuente),
            ]);
        }

        $dia = $this->consultar()[0];

        $this->assertSame(['10:00:00', '10:30:00'], $this->horas($dia));
        $this->assertSame([$libre->id], array_column($dia['horarios'][0]['profesionales'], 'kinesiologa_id'));
        $this->assertSame([$ocupada->id, $libre->id], array_column($dia['horarios'][1]['profesionales'], 'kinesiologa_id'));
    }

    public static function ocupaciones(): array
    {
        return [
            'Pendiente' => [EstadoTurnoEnum::PENDIENTE->value],
            'Confirmado' => [EstadoTurnoEnum::CONFIRMADO->value],
            'En atención' => [EstadoTurnoEnum::EN_ATENCION->value],
            'bloqueo' => ['bloqueo'],
        ];
    }

    public function test_date_with_no_effective_slots_is_omitted(): void
    {
        $profesional = $this->profesional();
        $this->agenda($profesional);
        BloqueoAgenda::factory()->for($profesional)->create([
            'inicio_at' => $this->fecha('2026-10-05 10:00:00'),
            'fin_at' => $this->fecha('2026-10-05 11:00:00'),
        ]);

        $this->assertSame([], $this->consultar());
    }

    public function test_each_date_uses_its_applicable_configuration_and_future_professionals_start_on_time(): void
    {
        $cambia = $this->profesional();
        $futura = $this->profesional();
        $actual = $this->agenda($cambia);
        $actual->update(['vigencia_hasta' => '2026-10-05']);
        $this->agenda($cambia, duracion: 45, desde: '2026-10-06', dias: [DiaSemanaEnum::MARTES], fin: '11:30:00');
        $this->agenda($futura, desde: '2026-10-06', dias: [DiaSemanaEnum::MARTES], inicio: '12:00:00', fin: '12:30:00');

        $dias = $this->consultar('2026-10-05', '2026-10-06');

        $this->assertSame(['2026-10-05', '2026-10-06'], array_column($dias, 'fecha'));
        $this->assertSame(['10:00:00', '10:30:00'], $this->horas($dias[0]));
        $this->assertSame(['10:00:00', '10:45:00', '12:00:00'], $this->horas($dias[1]));
        $this->assertSame('10:30:00', $dias[0]['horarios'][0]['profesionales'][0]['fin_at']->format('H:i:s'));
        $this->assertSame('10:45:00', $dias[1]['horarios'][0]['profesionales'][0]['fin_at']->format('H:i:s'));
        $this->assertSame([$futura->id], array_column($dias[1]['horarios'][2]['profesionales'], 'kinesiologa_id'));
    }

    public function test_window_is_inclusive_without_an_absolute_booking_horizon(): void
    {
        $this->agenda($this->profesional(), dias: DiaSemanaEnum::cases());

        $dias = $this->consultar('2026-10-06', '2026-10-07');
        $this->assertSame(['2026-10-06', '2026-10-07'], array_column($dias, 'fecha'));
        $lejana = $this->consultar('2099-12-25', '2099-12-26');
        $this->assertSame(['2099-12-25', '2099-12-26'], array_column($lejana, 'fecha'));
    }

    public function test_dates_starts_and_professionals_are_sorted_independently_of_source_order(): void
    {
        $primera = $this->profesional();
        $segunda = $this->profesional();
        $this->agenda($primera, dias: [DiaSemanaEnum::MARTES, DiaSemanaEnum::LUNES], inicio: '10:30:00', fin: '11:30:00');
        $this->agenda($segunda, dias: [DiaSemanaEnum::MARTES, DiaSemanaEnum::LUNES]);
        // Invierte las lecturas sin ORDER BY para no depender del orden accidental de SQLite.
        DB::statement('PRAGMA reverse_unordered_selects = ON');
        try {
            $dias = $this->consultar('2026-10-05', '2026-10-06');
        } finally {
            DB::statement('PRAGMA reverse_unordered_selects = OFF');
        }

        $this->assertSame(['2026-10-05', '2026-10-06'], array_column($dias, 'fecha'));
        foreach ($dias as $dia) {
            $this->assertSame(['10:00:00', '10:30:00', '11:00:00'], $this->horas($dia));
            $this->assertSame([$primera->id, $segunda->id], array_column($dia['horarios'][1]['profesionales'], 'kinesiologa_id'));
        }
    }

    public function test_internal_agenda_error_is_propagated_instead_of_becoming_empty_availability(): void
    {
        $profesional = $this->profesional();
        $this->agenda($profesional);
        $this->agenda($profesional);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('más de una configuración');
        $this->consultar();
    }

    public function test_read_only_query_cost_for_occupations_does_not_grow_with_slot_count(): void
    {
        $primera = $this->profesional();
        $segunda = $this->profesional();
        $primeraAgenda = $this->agenda($primera, fin: '10:30:00');
        $segundaAgenda = $this->agenda($segunda, fin: '10:30:00');
        [$pocos, $consultasPocos] = $this->medir();
        foreach ([$primeraAgenda, $segundaAgenda] as $agenda) {
            $agenda->franjasHorarias()->update(['hora_fin' => '14:00:00']);
        }
        [$muchos, $consultasMuchos] = $this->medir();

        $this->assertCount(1, $pocos[0]['horarios']);
        $this->assertCount(8, $muchos[0]['horarios']);
        foreach ([$consultasPocos, $consultasMuchos] as $consultas) {
            foreach ($consultas as $consulta) {
                $this->assertStringStartsWith('select ', strtolower(ltrim($consulta['query'])));
            }
            foreach (['turnos', 'bloqueos_agenda'] as $tabla) {
                $coinciden = array_filter($consultas, fn (array $consulta): bool => str_contains($consulta['query'], 'from "'.$tabla.'"'));
                $this->assertCount(2, $coinciden);
            }
        }
        $this->assertCount(count($consultasPocos), $consultasMuchos);
    }

    private function fecha(string $valor): CarbonImmutable
    {
        return CarbonImmutable::parse($valor, config('app.timezone'));
    }

    private function consultar(string $desde = '2026-10-05', string $hasta = '2026-10-05'): array
    {
        return $this->service->consultarDisponibilidad($this->fecha($desde), $this->fecha($hasta));
    }

    private function horas(array $dia): array
    {
        return array_map(fn (array $horario): string => $horario['inicio_at']->format('H:i:s'), $dia['horarios']);
    }

    private function medir(): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $dias = $this->consultar();

            return [$dias, DB::getQueryLog()];
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    private function agenda(
        Kinesiologa $profesional,
        int $duracion = 30,
        string $desde = '2026-10-01',
        array $dias = [DiaSemanaEnum::LUNES],
        string $inicio = '10:00:00',
        string $fin = '11:00:00',
    ): ConfiguracionAgenda {
        $agenda = ConfiguracionAgenda::factory()->for($profesional)->create([
            'vigencia_desde' => $desde, 'duracion_turno_minutos' => $duracion,
        ]);
        foreach ($dias as $dia) {
            FranjaHorariaAgenda::factory()->for($agenda, 'configuracionAgenda')->create([
                'dia_semana' => $dia, 'hora_inicio' => $inicio, 'hora_fin' => $fin,
            ]);
        }

        return $agenda;
    }

    private function profesional(): Kinesiologa
    {
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-agregada'], ['nombre' => 'Título', 'activo' => true]);
        $especialidad = EspecialidadKinesiologia::firstOrCreate(['slug' => 'especialidad-agregada'], ['nombre' => 'Especialidad', 'activo' => true]);
        $jurisdiccion = Jurisdiccion::firstOrCreate(['slug' => 'jurisdiccion-agregada'], ['nombre' => 'Jurisdicción', 'activo' => true]);
        $colegio = ColegioEmisor::firstOrCreate(['sigla' => 'AGR'], [
            'nombre' => 'Colegio', 'jurisdiccion_id' => $jurisdiccion->id, 'activo' => true,
        ]);
        $user = User::factory()->create([
            'dni' => fake()->unique()->numerify('########'), 'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino', 'telefono' => '3764123456', 'activo' => true,
        ]);
        $user->assignRole(RoleEnum::KINESIOLOGO->value);
        $profesional = Kinesiologa::create(['user_id' => $user->id, 'titulo_profesional_id' => $titulo->id]);
        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $profesional->id, 'nro_matricula_profesional' => 'MP-'.$user->id,
            'jurisdiccion_id' => $jurisdiccion->id, 'colegio_emisor_id' => $colegio->id, 'activa' => true,
        ]);
        $profesional->especialidades()->attach($especialidad);

        return $profesional->fresh();
    }
}
