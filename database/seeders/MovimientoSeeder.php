<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Movimiento;

class MovimientoSeeder extends Seeder
{
    public function run(): void
    {
        $movimientos = [
            [
                'id_zona_anatomica' => 1, // asegurate de tener esta zona creada
                'nombre' => 'Flexión de rodilla',
                'slug' => 'flexion-rodilla',
                'codigo' => 'MOV-ROD-FLEX',
                'plano' => 'sagital',
                'tipo_movimiento' => 'activa',
                'rango_norm_min' => 0,
                'rango_norm_max' => 135,
                'activo' => true,
            ],
            [
                'id_zona_anatomica' => 1,
                'nombre' => 'Extensión de rodilla',
                'slug' => 'extension-rodilla',
                'codigo' => 'MOV-ROD-EXT',
                'plano' => 'sagital',
                'tipo_movimiento' => 'activa',
                'rango_norm_min' => 0,
                'rango_norm_max' => 10,
                'activo' => true,
            ],
            [
                'id_zona_anatomica' => 2, // ejemplo: hombro
                'nombre' => 'Abducción de hombro',
                'slug' => 'abduccion-hombro',
                'codigo' => 'MOV-HOM-ABD',
                'plano' => 'frontal',
                'tipo_movimiento' => 'activa',
                'rango_norm_min' => 0,
                'rango_norm_max' => 180,
                'activo' => true,
            ],
        ];

        Movimiento::insert($movimientos);
    }
}
