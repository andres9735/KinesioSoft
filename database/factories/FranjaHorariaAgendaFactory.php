<?php

namespace Database\Factories;

use App\Enums\DiaSemanaEnum;
use App\Models\FranjaHorariaAgenda;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FranjaHorariaAgenda>
 */
class FranjaHorariaAgendaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dia_semana' => fake()->randomElement(DiaSemanaEnum::cases()),
            'hora_inicio' => '08:00:00',
            'hora_fin' => '12:00:00',
        ];
    }
}
