<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\RelojAplicacion;
use App\Services\Turnos\ElegibilidadPrimeraConsultaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ElegibilidadPrimeraConsultaTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $ahora;

    private ElegibilidadPrimeraConsultaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ahora = CarbonImmutable::parse('2026-10-20 10:00:00', config('app.timezone'));
        CarbonImmutable::setTestNow($this->ahora);
        $this->service = app(ElegibilidadPrimeraConsultaService::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_patient_without_appointments_has_no_future_active_first_consultation(): void
    {
        $this->assertFalse($this->service->tienePrimeraConsultaFuturaActiva($this->paciente()));
    }

    public function test_pending_future_first_consultation_blocks_d01_without_clinical_data(): void
    {
        $paciente = $this->paciente();
        $turno = $this->turno($paciente, EstadoTurnoEnum::PENDIENTE);

        $this->assertNull($turno->motivo_solicitud);
        $this->assertDatabaseCount('documentos_clinicos', 0);
        $this->assertTrue($this->service->tienePrimeraConsultaFuturaActiva($paciente));
    }

    public function test_confirmed_future_first_consultation_blocks_d01(): void
    {
        $paciente = $this->paciente();
        $this->turno($paciente, EstadoTurnoEnum::CONFIRMADO);

        $this->assertTrue($this->service->tienePrimeraConsultaFuturaActiva($paciente));
    }

    #[DataProvider('estadosCanonicos')]
    public function test_each_current_state_preserves_the_d01_eligibility_rule(EstadoTurnoEnum $estado, bool $bloqueaD01): void
    {
        $paciente = $this->paciente();
        $this->turno($paciente, $estado);

        $this->assertSame($bloqueaD01, $this->service->tienePrimeraConsultaFuturaActiva($paciente));
    }

    /**
     * @return array<string, array{EstadoTurnoEnum, bool}>
     */
    public static function estadosCanonicos(): array
    {
        return [
            'Pendiente' => [EstadoTurnoEnum::PENDIENTE, true],
            'Confirmado' => [EstadoTurnoEnum::CONFIRMADO, true],
            'En atención' => [EstadoTurnoEnum::EN_ATENCION, false],
            'Atendido' => [EstadoTurnoEnum::ATENDIDO, false],
            'Ausente' => [EstadoTurnoEnum::AUSENTE, false],
            'Cancelado' => [EstadoTurnoEnum::CANCELADO, false],
        ];
    }

    #[DataProvider('limitesTemporales')]
    public function test_future_is_strictly_after_the_application_clock_even_if_the_end_is_still_future(
        EstadoTurnoEnum $estado,
        int $segundos,
        bool $futuro
    ): void {
        $paciente = $this->paciente();
        $turno = $this->turno($paciente, $estado, $this->ahora->addSeconds($segundos));

        $this->assertTrue($turno->fin_at->isAfter($this->ahora));
        $this->assertSame($futuro, $this->service->tienePrimeraConsultaFuturaActiva($paciente));
    }

    /**
     * @return array<string, array{EstadoTurnoEnum, int, bool}>
     */
    public static function limitesTemporales(): array
    {
        $casos = [];
        foreach ([EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO] as $estado) {
            foreach ([-1, 0, 1] as $segundos) {
                $casos[$estado->value.' / '.$segundos.' segundos'] = [$estado, $segundos, $segundos > 0];
            }
        }

        return $casos;
    }

    #[DataProvider('estadosActivosD01')]
    public function test_historical_first_consultations_do_not_block(EstadoTurnoEnum $estado): void
    {
        $paciente = $this->paciente();
        $this->turno($paciente, $estado, $this->ahora->subDay());

        $this->assertFalse($this->service->tienePrimeraConsultaFuturaActiva($paciente));
    }

    #[DataProvider('estadosActivosD01')]
    public function test_another_patients_first_consultation_does_not_block(EstadoTurnoEnum $estado): void
    {
        $paciente = $this->paciente();
        $otro = $this->paciente();
        $this->turno($otro, $estado);

        $this->assertFalse($this->service->tienePrimeraConsultaFuturaActiva($paciente));
        $this->assertTrue($this->service->tienePrimeraConsultaFuturaActiva($otro));
    }

    #[DataProvider('estadosActivosD01')]
    public function test_future_follow_up_appointments_do_not_block(EstadoTurnoEnum $estado): void
    {
        $paciente = $this->paciente();
        $this->turno($paciente, $estado, tipo: TipoTurnoEnum::SEGUIMIENTO);

        $this->assertFalse($this->service->tienePrimeraConsultaFuturaActiva($paciente));
    }

    #[DataProvider('estadosActivosD01')]
    public function test_the_rule_applies_to_the_patient_independently_of_the_professional(EstadoTurnoEnum $estado): void
    {
        $paciente = $this->paciente();
        $profesionalA = $this->kinesiologa();
        $profesionalB = $this->kinesiologa();
        $primero = $this->turno($paciente, $estado, kinesiologa: $profesionalA);

        $this->assertTrue($this->service->tienePrimeraConsultaFuturaActiva($paciente));
        $this->assertSame([$primero->id], $this->service->primerasConsultasFuturasActivasDe($paciente)->pluck('id')->all());

        $primero->forceFill(['estado' => EstadoTurnoEnum::CANCELADO])->save();
        $segundo = $this->turno($paciente, $estado, kinesiologa: $profesionalB);

        $this->assertTrue($this->service->tienePrimeraConsultaFuturaActiva($paciente));
        $this->assertSame([$segundo->id], $this->service->primerasConsultasFuturasActivasDe($paciente)->pluck('id')->all());
    }

    /**
     * @return array<string, array{EstadoTurnoEnum}>
     */
    public static function estadosActivosD01(): array
    {
        $casos = [];
        foreach ([EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO] as $estado) {
            $casos[$estado->value] = [$estado];
        }

        return $casos;
    }

    public function test_each_query_uses_the_current_clock_without_caching_time_in_the_service(): void
    {
        $paciente = $this->paciente();
        $this->turno($paciente, EstadoTurnoEnum::PENDIENTE, $this->ahora->addSecond());

        $this->assertSame($this->ahora->toDateTimeString(), app(RelojAplicacion::class)->ahora()->toDateTimeString());
        $this->assertTrue($this->service->tienePrimeraConsultaFuturaActiva($paciente));

        CarbonImmutable::setTestNow($this->ahora->addSecond());

        $this->assertFalse($this->service->tienePrimeraConsultaFuturaActiva($paciente));
    }

    public function test_query_is_an_unexecuted_builder_reusable_for_exists_and_future_row_reads(): void
    {
        $paciente = $this->paciente();
        $turno = $this->turno($paciente, EstadoTurnoEnum::PENDIENTE);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $query = $this->service->primerasConsultasFuturasActivasDe($paciente);
            $bloqueante = (clone $query)->lockForUpdate();
            $consultas = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertSame([], $consultas);
        $this->assertTrue($bloqueante->getQuery()->lock);
        $this->assertSame($query->getQuery()->wheres, $bloqueante->getQuery()->wheres);
        $this->assertSame($query->getBindings(), $bloqueante->getBindings());
        $this->assertTrue((clone $query)->exists());
        $this->assertTrue((clone $query)->first()->is($turno));
        // La variante bloqueante no se ejecuta: SQLite no certifica locking de InnoDB.
    }

    private function turno(
        Paciente $paciente,
        EstadoTurnoEnum $estado,
        ?CarbonImmutable $inicio = null,
        TipoTurnoEnum $tipo = TipoTurnoEnum::PRIMERA_CONSULTA,
        ?Kinesiologa $kinesiologa = null
    ): Turno {
        $inicio ??= $this->ahora->addMinute();

        return Turno::factory()->for($paciente)->for($kinesiologa ?? $this->kinesiologa())->create([
            'tipo' => $tipo,
            'estado' => $estado,
            'inicio_at' => $inicio,
            'fin_at' => $inicio->addMinutes(45),
        ]);
    }

    private function paciente(): Paciente
    {
        return Paciente::create(['user_id' => User::factory()->create()->id, 'activo' => true]);
    }

    private function kinesiologa(): Kinesiologa
    {
        return Kinesiologa::create(['user_id' => User::factory()->create()->id]);
    }
}
