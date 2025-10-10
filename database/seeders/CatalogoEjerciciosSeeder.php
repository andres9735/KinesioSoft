<?php

namespace Database\Seeders;

use App\Models\Ejercicio;
use App\Models\CategoriaEjercicio;
use Illuminate\Database\Seeder;

class CatalogoEjerciciosSeeder extends Seeder
{
    public function run(): void
    {
        // Categorías base
        $categorias = [
            ['tipo' => 'fuerza',        'codigo' => 'CAT-FZA', 'nombre' => 'Fortalecimiento', 'descripcion' => 'Ejercicios orientados a fuerza', 'activo' => true],
            ['tipo' => 'movilidad',     'codigo' => 'CAT-MOV', 'nombre' => 'Movilidad',       'descripcion' => 'Movilidad articular y tisular',  'activo' => true],
            ['tipo' => 'estiramiento',  'codigo' => 'CAT-EST', 'nombre' => 'Estiramientos',   'descripcion' => 'Flexibilidad y elongación',       'activo' => true],
            ['tipo' => 'propiocepcion', 'codigo' => 'CAT-PRO', 'nombre' => 'Propiocepción',   'descripcion' => 'Control neuromuscular',           'activo' => true],
            ['tipo' => 'funcional',     'codigo' => 'CAT-FUN', 'nombre' => 'Funcional',       'descripcion' => 'Patrones de movimiento',          'activo' => true],
        ];

        CategoriaEjercicio::upsert($categorias, ['codigo'], ['tipo', 'nombre', 'descripcion', 'activo']);

        // Ejercicios iniciales
        $ejercicios = [
            ['nombre' => 'Puente glúteo',              'descripcion' => 'Activación de glúteos y core',                  'nivel_dificultad_base' => 'baja',  'url_recurso' => null, 'activo' => true],
            ['nombre' => 'Plancha anterior',           'descripcion' => 'Estabilidad lumbopélvica',                      'nivel_dificultad_base' => 'media', 'url_recurso' => null, 'activo' => true],
            ['nombre' => 'Bird-dog',                   'descripcion' => 'Control de columna',                            'nivel_dificultad_base' => 'baja',  'url_recurso' => null, 'activo' => true],
            ['nombre' => 'Estiramiento isquiotibial',  'descripcion' => 'Flexibilidad posterior de muslo',               'nivel_dificultad_base' => 'baja',  'url_recurso' => null, 'activo' => true],
            ['nombre' => 'Sentadilla a caja',          'descripcion' => 'Patrón de sentadilla asistida',                 'nivel_dificultad_base' => 'baja',  'url_recurso' => null, 'activo' => true],
        ];

        Ejercicio::upsert($ejercicios, ['nombre'], ['descripcion', 'nivel_dificultad_base', 'url_recurso', 'activo']);

        // Ejercicios extra (10)
        $ejerciciosExtra = [
            ['nombre' => 'Remo con banda elástica',         'descripcion' => 'Trabajo de dorsales y retractores escapulares con banda.',          'nivel_dificultad_base' => 'media', 'url_recurso' => null, 'activo' => true],
            ['nombre' => 'Sentadilla isométrica en pared',  'descripcion' => 'Isométrico de cuádriceps apoyando la espalda en la pared.',         'nivel_dificultad_base' => 'media', 'url_recurso' => null, 'activo' => true],
            ['nombre' => 'Step-up al banco',                 'descripcion' => 'Subida al banco para fortalecer glúteos y cuádriceps.',            'nivel_dificultad_base' => 'baja',  'url_recurso' => null, 'activo' => true],
            ['nombre' => 'Elevación de talones de pie',      'descripcion' => 'Fortalecimiento de tríceps sural en apoyo bipodal.',               'nivel_dificultad_base' => 'baja',  'url_recurso' => null, 'activo' => true],
            ['nombre' => 'Rotación torácica en cuadrupedia', 'descripcion' => 'Movilidad de columna torácica con rotación controlada.',           'nivel_dificultad_base' => 'baja',  'url_recurso' => null, 'activo' => true],
            ['nombre' => 'Movilidad de cadera 90/90',        'descripcion' => 'Transiciones 90/90 para movilidad interna/externa de cadera.',     'nivel_dificultad_base' => 'baja',  'url_recurso' => null, 'activo' => true],
            ['nombre' => 'Estiramiento de psoas en estocada', 'descripcion' => 'Elongación del flexor de cadera en posición de estocada.',         'nivel_dificultad_base' => 'baja',  'url_recurso' => null, 'activo' => true],
            ['nombre' => 'Estiramiento de pectoral en puerta', 'descripcion' => 'Elongación del pectoral mayor apoyando antebrazo en el marco.',   'nivel_dificultad_base' => 'baja',  'url_recurso' => null, 'activo' => true],
            ['nombre' => 'Equilibrio unipodal',              'descripcion' => 'Propiocepción en apoyo monopodal con control postural.',           'nivel_dificultad_base' => 'baja',  'url_recurso' => null, 'activo' => true],
            ['nombre' => 'Caminata en tándem',               'descripcion' => 'Marcha talón-punta en línea para control y equilibrio.',            'nivel_dificultad_base' => 'baja',  'url_recurso' => null, 'activo' => true],
        ];

        Ejercicio::upsert($ejerciciosExtra, ['nombre'], ['descripcion', 'nivel_dificultad_base', 'url_recurso', 'activo']);

        // Relacionar por código de categoría
        $mapCat = CategoriaEjercicio::pluck('id_categoria_ejercicio', 'codigo');

        // Relaciones iniciales
        $pares = [
            'Puente glúteo'             => ['CAT-FZA', 'CAT-FUN'],
            'Plancha anterior'          => ['CAT-FZA', 'CAT-FUN'],
            'Bird-dog'                  => ['CAT-FUN', 'CAT-PRO'],
            'Estiramiento isquiotibial' => ['CAT-EST'],
            'Sentadilla a caja'         => ['CAT-FZA', 'CAT-FUN'],
        ];

        foreach ($pares as $nombreEj => $codigos) {
            $ej = Ejercicio::where('nombre', $nombreEj)->first();
            if (! $ej) continue;

            $ids = collect($codigos)->map(fn($c) => $mapCat[$c] ?? null)->filter()->all();
            $ej->categorias()->syncWithoutDetaching($ids);
        }

        // Relaciones extra
        $paresExtra = [
            'Remo con banda elástica'          => ['CAT-FZA', 'CAT-FUN'],
            'Sentadilla isométrica en pared'   => ['CAT-FZA'],
            'Step-up al banco'                  => ['CAT-FZA', 'CAT-FUN'],
            'Elevación de talones de pie'       => ['CAT-FZA'],
            'Rotación torácica en cuadrupedia'  => ['CAT-MOV'],
            'Movilidad de cadera 90/90'         => ['CAT-MOV', 'CAT-EST'],
            'Estiramiento de psoas en estocada' => ['CAT-EST'],
            'Estiramiento de pectoral en puerta' => ['CAT-EST'],
            'Equilibrio unipodal'               => ['CAT-PRO'],
            'Caminata en tándem'                => ['CAT-PRO', 'CAT-FUN'],
        ];

        foreach ($paresExtra as $nombreEj => $codigos) {
            $ej = Ejercicio::where('nombre', $nombreEj)->first();
            if (! $ej) continue;

            $ids = collect($codigos)->map(fn($c) => $mapCat[$c] ?? null)->filter()->all();
            $ej->categorias()->syncWithoutDetaching($ids);
        }
    }
}
