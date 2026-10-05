<?php

namespace Database\Factories;

use App\Models\BloqueoAgenda;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BloqueoAgenda>
 */
class BloqueoAgendaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = DateTimeImmutable::createFromMutable(
            fake()->dateTimeBetween('-1 year', '+1 year')
        );

        return [
            'inicio_at' => $inicio,
            'fin_at' => $inicio->modify('+2 hours'),
            'motivo' => fake()->sentence(),
            'anulado_at' => null,
            'motivo_anulacion' => null,
        ];
    }
}
