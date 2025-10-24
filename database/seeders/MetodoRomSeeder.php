<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MetodoRom;

class MetodoRomSeeder extends Seeder
{
    public function run(): void
    {
        $metodos = [
            [
                'nombre' => 'Goniómetro manual',
                'slug' => 'goniometro-manual',
                'codigo' => 'ROM-MAN-001',
                'tipo' => 'manual',
                'precision_decimales' => 0,
                'unidad_defecto' => '°',
                'activo' => true,
            ],
            [
                'nombre' => 'Inclinómetro digital',
                'slug' => 'inclinometro-digital',
                'codigo' => 'ROM-DIG-001',
                'tipo' => 'digital',
                'precision_decimales' => 1,
                'unidad_defecto' => '°',
                'activo' => true,
            ],
            [
                'nombre' => 'Sensor IMU portátil',
                'slug' => 'sensor-imu-portatil',
                'codigo' => 'ROM-IMU-001',
                'tipo' => 'imu',
                'precision_decimales' => 2,
                'unidad_defecto' => '°',
                'activo' => true,
            ],
        ];

        MetodoRom::insert($metodos);
    }
}
