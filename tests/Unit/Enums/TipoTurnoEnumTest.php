<?php

namespace Tests\Unit\Enums;

use App\Enums\TipoTurnoEnum;
use PHPUnit\Framework\TestCase;

class TipoTurnoEnumTest extends TestCase
{
    public function test_it_contains_exactly_the_canonical_types_and_persisted_string_values(): void
    {
        $this->assertSame([
            ['PRIMERA_CONSULTA', 'PRIMERA_CONSULTA'],
            ['SEGUIMIENTO', 'SEGUIMIENTO'],
        ], array_map(
            static fn (TipoTurnoEnum $tipo): array => [$tipo->name, $tipo->value],
            TipoTurnoEnum::cases()
        ));

        $this->assertSame(TipoTurnoEnum::PRIMERA_CONSULTA, TipoTurnoEnum::from('PRIMERA_CONSULTA'));
        $this->assertSame(TipoTurnoEnum::SEGUIMIENTO, TipoTurnoEnum::from('SEGUIMIENTO'));
    }
}
