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

    public function test_only_pending_and_confirmed_reserve_agenda_without_duplicates(): void
    {
        $estados = EstadoTurnoEnum::estadosQueReservanAgenda();

        $this->assertSame([
            EstadoTurnoEnum::PENDIENTE,
            EstadoTurnoEnum::CONFIRMADO,
        ], $estados);

        $valores = array_map(
            static fn (EstadoTurnoEnum $estado): string => $estado->value,
            $estados
        );
        $this->assertCount(count($estados), array_unique($valores));

        foreach ([
            EstadoTurnoEnum::EN_ATENCION,
            EstadoTurnoEnum::ATENDIDO,
            EstadoTurnoEnum::AUSENTE,
            EstadoTurnoEnum::CANCELADO,
        ] as $estado) {
            $this->assertNotContains($estado, $estados);
        }
    }
}
