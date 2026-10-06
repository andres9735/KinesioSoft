<?php

namespace Database\Factories;

use App\Enums\EstadoTurnoEnum;
use App\Models\Turno;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Turno>
 */
class TurnoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = DateTimeImmutable::createFromMutable(
            fake()->dateTimeBetween('+1 day', '+1 year')
        );

        return [
            'tipo' => 'SEGUIMIENTO',
            'estado' => EstadoTurnoEnum::PENDIENTE,
            'inicio_at' => $inicio,
            'fin_at' => $inicio->modify('+45 minutes'),
            'motivo_solicitud' => null,
            'recordatorio_enviado_at' => null,
        ];
    }
}
