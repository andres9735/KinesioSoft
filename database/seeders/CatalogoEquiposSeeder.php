<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EquipoTerapeutico;
use Illuminate\Support\Facades\DB;

class CatalogoEquiposSeeder extends Seeder
{
    public function run(): void
    {
        // Aseguramos que existan algunos consultorios base (mínimos) si no cargaste nada aún.
        // Si ya tienes tus consultorios reales, podés borrar este bloque.
        $consultorios = [
            ['id_consultorio' => 1, 'nombre' => 'Consultorio 1', 'created_at' => now(), 'updated_at' => now()],
            ['id_consultorio' => 2, 'nombre' => 'Consultorio 2', 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($consultorios as $c) {
            DB::table('consultorio')->updateOrInsert(
                ['id_consultorio' => $c['id_consultorio']],
                $c
            );
        }

        // Equipos de ejemplo (usa consultorio 1/2 por FK)
        $equipos = [
            [
                'codigo'         => 'EQ-ULTR-001',
                'nombre'         => 'Ultrasonido portátil',
                'marca_modelo'   => 'Sonus Pro 200',
                'descripcion'    => 'Equipo de ultrasonido terapéutico de 1 y 3 MHz.',
                'estado'         => 'operativo',
                'id_consultorio' => 1,
                'activo'         => true,
            ],
            [
                'codigo'         => 'EQ-TENS-002',
                'nombre'         => 'Electroestimulador TENS',
                'marca_modelo'   => 'NeuroStim T-4',
                'descripcion'    => 'Unidad TENS de 4 canales para analgesia.',
                'estado'         => 'operativo',
                'id_consultorio' => 1,
                'activo'         => true,
            ],
            [
                'codigo'         => 'EQ-LASR-003',
                'nombre'         => 'Láser terapéutico',
                'marca_modelo'   => 'LaserMed L-10',
                'descripcion'    => 'Láser de baja intensidad para biomodulación tisular.',
                'estado'         => 'operativo',
                'id_consultorio' => 2,
                'activo'         => true,
            ],
            [
                'codigo'         => 'EQ-CRYO-004',
                'nombre'         => 'Crioterapia de contacto',
                'marca_modelo'   => 'CryoTouch C-2',
                'descripcion'    => 'Sistema de crioterapia para aplicaciones localizadas.',
                'estado'         => 'baja',
                'id_consultorio' => 2,
                'activo'         => false,
            ],
        ];

        // upsert por código
        foreach ($equipos as $e) {
            EquipoTerapeutico::updateOrCreate(
                ['codigo' => $e['codigo']],
                $e
            );
        }
    }
}
