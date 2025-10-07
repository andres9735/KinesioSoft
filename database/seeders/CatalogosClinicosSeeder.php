<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\PadecimientoTipo;
use App\Models\ZonaAnatomica;
use App\Models\DiagnosticoFuncional;
use App\Models\Patologia;

class CatalogosClinicosSeeder extends Seeder
{
    public function run(): void
    {
        // Padecimiento tipo
        PadecimientoTipo::upsert([
            ['nombre' => 'Lumbalgia',     'activo' => true],
            ['nombre' => 'Cervicalgia',   'activo' => true],
            ['nombre' => 'Gonalgia',      'activo' => true],
            ['nombre' => 'Tendinopatía',  'activo' => true],
        ], ['nombre'], ['activo']);

        // Zonas raíz
        $sup = ZonaAnatomica::updateOrCreate(
            ['slug' => 'miembro-superior'],
            ['nombre' => 'Miembro superior', 'requiere_lateralidad' => false, 'activo' => true]
        );
        $inf = ZonaAnatomica::updateOrCreate(
            ['slug' => 'miembro-inferior'],
            ['nombre' => 'Miembro inferior', 'requiere_lateralidad' => false, 'activo' => true]
        );
        $col = ZonaAnatomica::updateOrCreate(
            ['slug' => 'columna'],
            ['nombre' => 'Columna', 'requiere_lateralidad' => false, 'activo' => true]
        );

        // Hijas
        foreach (
            [
                [$sup, 'Hombro',   true],
                [$sup, 'Codo',   true],
                [$sup, 'Muñeca', true],
                [$sup, 'Mano', true],
                [$inf, 'Cadera',   true],
                [$inf, 'Rodilla', true],
                [$inf, 'Tobillo', true],
                [$inf, 'Pie',  true],
                [$col, 'Cervical', false],
                [$col, 'Dorsal', false],
                [$col, 'Lumbar', false],
                [$col, 'Sacro', false],
            ] as [$parent, $nombre, $lat]
        ) {
            ZonaAnatomica::updateOrCreate(
                ['slug' => Str::slug($nombre)],
                [
                    'nombre' => $nombre,
                    'parent_id' => $parent->getKey(),
                    'requiere_lateralidad' => $lat,
                    'activo' => true
                ]
            );
        }

        // Diagnóstico funcional
        DiagnosticoFuncional::upsert([
            ['nombre' => 'Lumbalgia mecánica',          'activo' => true],
            ['nombre' => 'Síndrome femoropatelar',      'activo' => true],
            ['nombre' => 'Impingement de hombro',       'activo' => true],
        ], ['nombre'], ['activo']);

        // Patología (antecedentes)
        Patologia::upsert([
            ['nombre' => 'Hipertensión arterial', 'activo' => true],
            ['nombre' => 'Diabetes mellitus',     'activo' => true],
            ['nombre' => 'Hipotiroidismo',        'activo' => true],
            ['nombre' => 'Asma bronquial',        'activo' => true],
        ], ['nombre'], ['activo']);
    }
}
