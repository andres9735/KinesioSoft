<?php

namespace Tests\Feature\Models;

use App\Enums\EstadoTurnoEnum;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnoModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_expected_table_fillable_casts_and_relations(): void
    {
        $paciente = $this->paciente();
        $kinesiologa = $this->kinesiologa();
        $turno = Turno::factory()
            ->for($paciente)
            ->for($kinesiologa)
            ->create([
                'tipo' => 'SEGUIMIENTO',
                'estado' => EstadoTurnoEnum::CONFIRMADO,
                'inicio_at' => '2026-10-10 08:00:00',
                'fin_at' => '2026-10-10 08:45:00',
                'motivo_solicitud' => 'Control programado.',
                'recordatorio_enviado_at' => '2026-10-09 08:00:00',
            ])
            ->fresh();

        $this->assertSame('turnos', $turno->getTable());
        $this->assertSame([
            'tipo',
            'inicio_at',
            'fin_at',
            'motivo_solicitud',
        ], $turno->getFillable());
        $this->assertSame(EstadoTurnoEnum::CONFIRMADO, $turno->estado);
        $this->assertInstanceOf(CarbonImmutable::class, $turno->inicio_at);
        $this->assertInstanceOf(CarbonImmutable::class, $turno->fin_at);
        $this->assertInstanceOf(CarbonImmutable::class, $turno->recordatorio_enviado_at);
        $this->assertTrue($turno->paciente->is($paciente));
        $this->assertTrue($turno->kinesiologa->is($kinesiologa));
        $this->assertTrue($paciente->fresh()->turnos->contains($turno));
        $this->assertTrue($kinesiologa->fresh()->turnos->contains($turno));
    }

    public function test_factory_persists_a_turno_with_explicit_relations(): void
    {
        $paciente = $this->paciente();
        $kinesiologa = $this->kinesiologa();

        $turno = Turno::factory()
            ->for($paciente)
            ->for($kinesiologa)
            ->create();

        $this->assertTrue($turno->paciente->is($paciente));
        $this->assertTrue($turno->kinesiologa->is($kinesiologa));
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $turno->estado);
        $this->assertTrue($turno->fin_at->isAfter($turno->inicio_at));
    }

    public function test_it_persists_and_recovers_each_canonical_state(): void
    {
        $paciente = $this->paciente();
        $kinesiologa = $this->kinesiologa();

        foreach (EstadoTurnoEnum::cases() as $index => $estado) {
            $turno = Turno::factory()
                ->for($paciente)
                ->for($kinesiologa)
                ->create([
                    'estado' => $estado,
                    'inicio_at' => CarbonImmutable::parse('2026-10-10 08:00:00')
                        ->addHours($index),
                    'fin_at' => CarbonImmutable::parse('2026-10-10 08:45:00')
                        ->addHours($index),
                ]);

            $this->assertSame($estado, $turno->fresh()->estado);
        }
    }

    public function test_it_preserves_its_own_temporal_coordinates_without_an_agenda(): void
    {
        $turno = Turno::factory()
            ->for($this->paciente())
            ->for($this->kinesiologa())
            ->create([
                'inicio_at' => '2026-11-20 14:15:00',
                'fin_at' => '2026-11-20 15:00:00',
            ])
            ->fresh();

        $this->assertSame('2026-11-20 14:15:00', $turno->inicio_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-20 15:00:00', $turno->fin_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('configuraciones_agenda', 0);
        $this->assertDatabaseCount('franjas_horarias_agenda', 0);
    }

    private function paciente(): Paciente
    {
        return Paciente::create([
            'user_id' => User::factory()->create()->id,
            'activo' => true,
        ]);
    }

    private function kinesiologa(): Kinesiologa
    {
        return Kinesiologa::create([
            'user_id' => User::factory()->create()->id,
            'titulo_profesional_id' => null,
            'consultorio_id' => null,
        ]);
    }
}
