<?php

namespace Database\Seeders;

use App\Models\EspecialidadKinesiologia;
use Illuminate\Database\Seeder;

class EspecialidadKinesiologiaSeeder extends Seeder
{
    public function run(): void
    {
        $especialidades = [
            [
                'nombre' => 'Kinesiología Traumatológica',
                'slug' => 'kinesiologia-traumatologica',
            ],
            [
                'nombre' => 'Kinesiología Neurológica',
                'slug' => 'kinesiologia-neurologica',
            ],
            [
                'nombre' => 'Kinesiología Respiratoria',
                'slug' => 'kinesiologia-respiratoria',
            ],
            [
                'nombre' => 'Kinesiología Deportiva',
                'slug' => 'kinesiologia-deportiva',
            ],
            [
                'nombre' => 'Kinesiología Pediátrica',
                'slug' => 'kinesiologia-pediatrica',
            ],
            [
                'nombre' => 'Kinesiología Geriátrica',
                'slug' => 'kinesiologia-geriatrica',
            ],
            [
                'nombre' => 'Kinesiología en Rehabilitación Postural',
                'slug' => 'kinesiologia-rehabilitacion-postural',
            ],
        ];

        foreach ($especialidades as $especialidad) {
            EspecialidadKinesiologia::firstOrCreate(
                ['slug' => $especialidad['slug']],
                [
                    'nombre' => $especialidad['nombre'],
                    'activo' => true,
                ]
            );
        }
    }
}
