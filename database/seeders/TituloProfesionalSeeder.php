<?php

namespace Database\Seeders;

use App\Models\TituloProfesional;
use Illuminate\Database\Seeder;

class TituloProfesionalSeeder extends Seeder
{
    public function run(): void
    {
        $titulos = [
            [
                'nombre' => 'Kinesiólogo',
                'slug' => 'kinesiologo',
            ],
            [
                'nombre' => 'Kinesiólogo Fisiatra',
                'slug' => 'kinesiologo-fisiatra',
            ],
            [
                'nombre' => 'Licenciado en Kinesiología',
                'slug' => 'licenciado-en-kinesiologia',
            ],
            [
                'nombre' => 'Fisioterapeuta',
                'slug' => 'fisioterapeuta',
            ],
            [
                'nombre' => 'Terapista Físico',
                'slug' => 'terapista-fisico',
            ],
        ];

        foreach ($titulos as $titulo) {
            TituloProfesional::firstOrCreate(
                ['slug' => $titulo['slug']],
                [
                    'nombre' => $titulo['nombre'],
                    'activo' => true,
                ]
            );
        }
    }
}
