<?php

namespace Database\Factories;

use App\Models\ConfiguracionAgenda;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfiguracionAgenda>
 */
class ConfiguracionAgendaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vigencia_desde' => fake()->date(),
            'vigencia_hasta' => null,
            'duracion_turno_minutos' => 45,
            'anulado_at' => null,
            'motivo_anulacion' => null,
        ];
    }
}
