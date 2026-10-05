<?php

namespace Tests\Unit\Enums;

use App\Enums\DiaSemanaEnum;
use PHPUnit\Framework\TestCase;

class DiaSemanaEnumTest extends TestCase
{
    public function test_uses_iso_8601_values_from_monday_to_sunday(): void
    {
        $this->assertSame([
            ['LUNES', 1],
            ['MARTES', 2],
            ['MIERCOLES', 3],
            ['JUEVES', 4],
            ['VIERNES', 5],
            ['SABADO', 6],
            ['DOMINGO', 7],
        ], array_map(
            static fn (DiaSemanaEnum $dia): array => [$dia->name, $dia->value],
            DiaSemanaEnum::cases()
        ));
    }
}
