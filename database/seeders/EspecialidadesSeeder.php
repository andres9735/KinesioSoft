<?php

namespace Database\Seeders;

use App\Models\Especialidad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EspecialidadesSeeder extends Seeder
{
    public function run(): void
    {
        $nombres = [
            'Kinesiología Deportiva',
            'Neurorehabilitación',
            'Traumatológica',
            'Respiratoria',
            'Pediátrica',
            'Geriátrica',
            'Suelo pélvico',
            'Cardiorrespiratoria',
        ];

        foreach ($nombres as $nombre) {
            Especialidad::firstOrCreate(
                ['slug' => Str::slug($nombre)],
                ['nombre' => $nombre, 'estado' => true]
            );
        }
    }
}
