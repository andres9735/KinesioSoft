<?php

namespace Tests\Unit\Enums;

use App\Enums\EstadoTurnoEnum;
use PHPUnit\Framework\TestCase;

class EstadoTurnoEnumTest extends TestCase
{
    public function test_it_contains_exactly_the_six_canonical_states(): void
    {
        $this->assertSame([
            'PENDIENTE',
            'CONFIRMADO',
            'EN_ATENCION',
            'ATENDIDO',
            'AUSENTE',
            'CANCELADO',
        ], array_map(
            static fn (EstadoTurnoEnum $estado): string => $estado->value,
            EstadoTurnoEnum::cases()
        ));
        $this->assertCount(6, EstadoTurnoEnum::cases());
    }

    public function test_reprogramado_and_realizado_are_not_states(): void
    {
        $this->assertFalse(defined(EstadoTurnoEnum::class.'::REPROGRAMADO'));
        $this->assertFalse(defined(EstadoTurnoEnum::class.'::REALIZADO'));
    }
}
