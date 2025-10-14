<?php

namespace Database\Seeders;

use App\Models\Tecnica;
use App\Models\TecnicaTipo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogoTecnicasSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // --- TIPOS DE TÉCNICA ---
            $tipos = [
                ['codigo' => 'TT-MASO', 'nombre' => 'Masoterapia',    'descripcion' => 'Maniobras manuales y masaje terapéutico', 'activo' => true],
                ['codigo' => 'TT-KINE', 'nombre' => 'Kinesioterapia', 'descripcion' => 'Ejercicio terapéutico y movilizaciones',  'activo' => true],
                ['codigo' => 'TT-ELEC', 'nombre' => 'Electroterapia', 'descripcion' => 'Agentes físicos de corriente',            'activo' => true],
                ['codigo' => 'TT-TERMO', 'nombre' => 'Termoterapia',   'descripcion' => 'Agentes térmicos (calor/frío)',           'activo' => true],
                ['codigo' => 'TT-MANU', 'nombre' => 'Terapia manual', 'descripcion' => 'Técnicas manuales de movilización',       'activo' => true],
            ];

            foreach ($tipos as $t) {
                TecnicaTipo::updateOrCreate(
                    ['codigo' => $t['codigo']],
                    [
                        'nombre'      => $t['nombre'],
                        'descripcion' => $t['descripcion'],
                        'activo'      => $t['activo'],
                    ]
                );
            }

            // --- MAPEO para FK ---
            $mapTipos = TecnicaTipo::pluck('id_tecnica_tipo', 'codigo');

            // --- TÉCNICAS ---
            $tecnicas = [
                // Masoterapia
                ['tipo' => 'TT-MASO', 'codigo' => 'TEC-MAS-DSC', 'nombre' => 'Masaje descontracturante', 'descripcion' => 'Disminuye tono y puntos gatillo', 'activo' => true],
                ['tipo' => 'TT-MASO', 'codigo' => 'TEC-MAS-CIRC', 'nombre' => 'Masaje circulatorio',       'descripcion' => 'Drenaje y retorno venoso',        'activo' => true],

                // Kinesioterapia
                ['tipo' => 'TT-KINE', 'codigo' => 'TEC-KIN-MOB', 'nombre' => 'Movilización activa asistida', 'descripcion' => 'Recupera rango articular con asistencia', 'activo' => true],
                ['tipo' => 'TT-KINE', 'codigo' => 'TEC-KIN-EST', 'nombre' => 'Estiramientos terapéuticos',    'descripcion' => 'Flexibilidad y extensibilidad',          'activo' => true],

                // Electroterapia
                ['tipo' => 'TT-ELEC', 'codigo' => 'TEC-ELT-TENS', 'nombre' => 'TENS',                        'descripcion' => 'Analgesia por estimulación eléctrica',  'activo' => true],
                ['tipo' => 'TT-ELEC', 'codigo' => 'TEC-ELT-INTF', 'nombre' => 'Interferenciales',             'descripcion' => 'Corrientes de media frecuencia',        'activo' => true],
                ['tipo' => 'TT-ELEC', 'codigo' => 'TEC-ELT-US',  'nombre' => 'Ultrasonido terapéutico',      'descripcion' => 'Efecto térmico y mecánico tisular',     'activo' => true],

                // Termoterapia
                ['tipo' => 'TT-TERMO', 'codigo' => 'TEC-TRM-CAL', 'nombre' => 'Compresas calientes',          'descripcion' => 'Termoterapia superficial caliente',     'activo' => true],
                ['tipo' => 'TT-TERMO', 'codigo' => 'TEC-TRM-FRIO', 'nombre' => 'Crioterapia local',            'descripcion' => 'Aplicación de frío antiinflamatorio',  'activo' => true],

                // Terapia manual
                ['tipo' => 'TT-MANU', 'codigo' => 'TEC-MAN-MAIT', 'nombre' => 'Movilización Maitland',        'descripcion' => 'Movilizaciones pasivas grado I–IV',    'activo' => true],
                ['tipo' => 'TT-MANU', 'codigo' => 'TEC-MAN-MULL', 'nombre' => 'Técnicas Mulligan',            'descripcion' => 'MWM / SNAGS para dolor y función',     'activo' => true],
            ];

            foreach ($tecnicas as $t) {
                $idTipo = $mapTipos[$t['tipo']] ?? null;
                if (! $idTipo) continue;

                Tecnica::updateOrCreate(
                    ['codigo' => $t['codigo']],
                    [
                        'id_tecnica_tipo' => $idTipo,
                        'nombre'          => $t['nombre'],
                        'descripcion'     => $t['descripcion'],
                        'activo'          => $t['activo'],
                    ]
                );
            }
        });
    }
}
