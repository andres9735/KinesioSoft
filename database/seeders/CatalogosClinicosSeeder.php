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
            ['codigo' => 'DF001', 'nombre' => 'Lumbalgia mecánica', 'descripcion' => 'Dolor lumbar de origen muscular o articular sin irradiación significativa.', 'activo' => true],
            ['codigo' => 'DF002', 'nombre' => 'Síndrome femoropatelar', 'descripcion' => 'Dolor anterior de rodilla asociado a disfunción del seguimiento rotuliano.', 'activo' => true],
            ['codigo' => 'DF003', 'nombre' => 'Impingement de hombro', 'descripcion' => 'Compresión de estructuras subacromiales durante la elevación del brazo.', 'activo' => true],
            ['codigo' => 'DF004', 'nombre' => 'Tendinopatía del manguito rotador', 'descripcion' => 'Lesión degenerativa o inflamatoria de los tendones del hombro.', 'activo' => true],
            ['codigo' => 'DF005', 'nombre' => 'Cervicalgia postural', 'descripcion' => 'Dolor cervical asociado a mala postura o tensión muscular sostenida.', 'activo' => true],
            ['codigo' => 'DF006', 'nombre' => 'Epicondilitis lateral', 'descripcion' => 'Dolor en la cara lateral del codo por sobreuso de extensores del antebrazo.', 'activo' => true],
            ['codigo' => 'DF007', 'nombre' => 'Síndrome del túnel carpiano', 'descripcion' => 'Compresión del nervio mediano a nivel del túnel carpiano.', 'activo' => true],
            ['codigo' => 'DF008', 'nombre' => 'Gonalgia mecánica', 'descripcion' => 'Dolor en rodilla relacionado con esfuerzo, sobreuso o artrosis leve.', 'activo' => true],
            ['codigo' => 'DF009', 'nombre' => 'Tendinopatía aquílea', 'descripcion' => 'Dolor y engrosamiento del tendón de Aquiles por sobreuso.', 'activo' => true],
            ['codigo' => 'DF010', 'nombre' => 'Fascitis plantar', 'descripcion' => 'Dolor plantar en la inserción de la fascia por microtraumatismos repetidos.', 'activo' => true],
            ['codigo' => 'DF011', 'nombre' => 'Esguince de tobillo', 'descripcion' => 'Lesión ligamentaria por inversión o eversión forzada del tobillo.', 'activo' => true],
            ['codigo' => 'DF012', 'nombre' => 'Síndrome del piramidal', 'descripcion' => 'Dolor glúteo irradiado por compresión del nervio ciático.', 'activo' => true],
            ['codigo' => 'DF013', 'nombre' => 'Lumbociatalgia', 'descripcion' => 'Dolor lumbar con irradiación hacia el trayecto del nervio ciático.', 'activo' => true],
            ['codigo' => 'DF014', 'nombre' => 'Cervicobraquialgia', 'descripcion' => 'Dolor cervical irradiado hacia el brazo por compresión nerviosa.', 'activo' => true],
            ['codigo' => 'DF015', 'nombre' => 'Bursitis trocantérica', 'descripcion' => 'Inflamación de la bursa trocantérica con dolor lateral de cadera.', 'activo' => true],
            ['codigo' => 'DF016', 'nombre' => 'Síndrome de latigazo cervical', 'descripcion' => 'Lesión de partes blandas cervicales por movimiento brusco de aceleración-desaceleración.', 'activo' => true],
            ['codigo' => 'DF017', 'nombre' => 'Escoliosis funcional', 'descripcion' => 'Desviación lateral de la columna sin alteraciones estructurales óseas.', 'activo' => true],
            ['codigo' => 'DF018', 'nombre' => 'Dorsalgia postural', 'descripcion' => 'Dolor en región dorsal media por sobrecarga muscular o mala postura.', 'activo' => true],
            ['codigo' => 'DF019', 'nombre' => 'Síndrome de dolor miofascial', 'descripcion' => 'Dolor localizado con puntos gatillo en músculos y fascias.', 'activo' => true],
            ['codigo' => 'DF020', 'nombre' => 'Tendinopatía rotuliana', 'descripcion' => 'Dolor en el polo inferior de la rótula por sobrecarga de tendón rotuliano.', 'activo' => true],
        ], ['nombre'], ['codigo', 'descripcion', 'activo']);


        // Patología (antecedentes)
        Patologia::upsert([
            ['nombre' => 'Hipertensión arterial', 'activo' => true],
            ['nombre' => 'Diabetes mellitus',     'activo' => true],
            ['nombre' => 'Hipotiroidismo',        'activo' => true],
            ['nombre' => 'Asma bronquial',        'activo' => true],
        ], ['nombre'], ['activo']);
    }
}
