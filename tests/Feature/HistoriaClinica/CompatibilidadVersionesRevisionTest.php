<?php

namespace Tests\Feature\HistoriaClinica;

use App\Data\HistoriaClinica\FuenteDeclaracionParaRevision;
use App\Models\DeclaracionSaludPaciente;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompatibilidadVersionesRevisionTest extends TestCase
{
    public function test_respuestas_aditivas_conservan_originales_items_y_huellas_historicas_sin_inferencias(): void
    {
        $anterior = DB::getDefaultConnection();
        config(['database.connections.uc15_compatibilidad' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('uc15_compatibilidad');
        try {
            $db = DB::connection();
            $this->assertSame('sqlite', $db->getDriverName());
            $this->assertSame(':memory:', $db->getConfig('database'));
            foreach ($db->select('PRAGMA database_list') as $archivo) {
                $this->assertSame('', $archivo->file);
            }
            $db->getSchemaBuilder()->create('pacientes', fn (Blueprint $table) => $table->id());
            $db->table('pacientes')->insert(['id' => 1]);
            (require database_path('migrations/2026_09_08_155141_create_declaraciones_salud_paciente_table.php'))->up();
            (require database_path('migrations/2026_09_08_155312_create_items_declaracion_salud_table.php'))->up();
            foreach (['PENDIENTE_REVISION', 'REVISADA'] as $indice => $estado) {
                $db->table('declaraciones_salud_paciente')->insert([
                    'id' => $indice + 1, 'paciente_id' => 1, 'objetivo_salud' => 'Original histórico',
                    'peso_kg' => 70, 'altura_cm' => '170.25', 'ocupacion' => 'Empleado',
                    'nivel_actividad_fisica' => 'MODERADO', 'detalle_actividad_fisica' => 'Caminata',
                    'estado' => $estado, 'created_at' => '2026-11-20 10:00:00',
                ]);
            }
            $db->table('items_declaracion_salud')->insert([
                'declaracion_salud_id' => 2, 'tipo' => 'ANTECEDENTE', 'descripcion' => 'Descripción histórica',
            ]);
            $originales = $db->table('declaraciones_salud_paciente')->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
            $items = $db->table('items_declaracion_salud')->get()->all();
            $capturar = fn () => DeclaracionSaludPaciente::with('items')->orderBy('id')->get()->map(
                fn ($declaracion) => $declaracion->estado === 'REVISADA'
                    ? FuenteDeclaracionParaRevision::desdeRevisada($declaracion)
                    : FuenteDeclaracionParaRevision::desde($declaracion)
            )->all();
            $fuentesAnteriores = $capturar();
            $migracion = require database_path('migrations/2026_11_24_000001_add_respuestas_declaradas_to_declaraciones_salud_paciente_table.php');
            $migracion->up();
            foreach ($db->table('declaraciones_salud_paciente')->orderBy('id')->get() as $indice => $fila) {
                $atributos = (array) $fila;
                foreach (['antecedentes', 'cirugias', 'alergias', 'medicacion'] as $categoria) {
                    $this->assertNull($atributos[$categoria.'_respuesta']);
                    unset($atributos[$categoria.'_respuesta']);
                }
                $this->assertSame($originales[$indice], $atributos);
                $fuente = $capturar()[$indice];
                $this->assertSame($fuentesAnteriores[$indice]->bloques, $fuente->bloques);
                $this->assertSame($fuentesAnteriores[$indice]->huella, $fuente->huella);
                foreach (FuenteDeclaracionParaRevision::LISTAS as $bloque => $tipo) {
                    $this->assertArrayNotHasKey('respuesta', $fuente->bloques[$bloque]);
                }
            }
            $this->assertEquals($items, $db->table('items_declaracion_salud')->get()->all());
            $migracion->down();
            $this->assertSame($originales, $db->table('declaraciones_salud_paciente')->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all());
        } finally {
            DB::purge('uc15_compatibilidad');
            DB::setDefaultConnection($anterior);
            config(['database.connections.uc15_compatibilidad' => null]);
        }
    }

    public function test_la_migracion_aditiva_conserva_la_apertura_anterior_y_amplia_altura_y_versiones_sin_clave(): void
    {
        // Segundo SQLite :memory: exclusivo. No usa una base ni un archivo preexistente.
        $anterior = DB::getDefaultConnection();
        config(['database.connections.uc14_compatibilidad' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('uc14_compatibilidad');
        try {
            $db = DB::connection();
            $this->assertSame('sqlite', $db->getDriverName());
            $this->assertSame(':memory:', $db->getConfig('database'));
            foreach ($db->select('PRAGMA database_list') as $archivo) {
                $this->assertSame('', $archivo->file);
            }
            $schema = $db->getSchemaBuilder();
            foreach (['users', 'pacientes', 'kinesiologas', 'especialidades_kinesiologia', 'turnos'] as $tabla) {
                $schema->create($tabla, fn (Blueprint $table) => $table->id());
                $db->table($tabla)->insert(['id' => 1]);
            }
            foreach (['historias_clinicas', 'declaraciones_salud_paciente'] as $tabla) {
                $schema->create($tabla, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('paciente_id');
                });
                $db->table($tabla)->insert(['id' => 1, 'paciente_id' => 1]);
            }
            $historica = require database_path('migrations/2026_11_20_120000_create_versiones_datos_clinicos_base_tables.php');
            $historica->up();
            $clave = (string) Str::uuid();
            $version = ['historia_clinica_id' => 1, 'kinesiologa_id' => 1, 'autor_user_id' => 1,
                'especialidad_id' => 1, 'turno_origen_id' => 1, 'numero_version' => 1, 'clave_apertura' => $clave,
                'huella_solicitud' => str_repeat('a', 64), 'autor_nombre_snapshot' => 'Profesional original',
                'especialidad_nombre_snapshot' => 'Especialidad', 'origen' => 'CARGA_PROFESIONAL_DIRECTA',
                'procedencia_relevamiento' => 'REFERIDO_PACIENTE', 'relevamientos' => '{}',
                'resultado_nucleo_familiar' => 'INFORMACION_NO_DISPONIBLE', 'altura_cm' => 170,
                'created_at' => '2026-11-20 10:00:00'];
            $db->table('versiones_datos_clinicos_base')->insert($version);
            $aditiva = require database_path('migrations/2026_11_21_000001_prepare_versiones_base_for_revision.php');
            $aditiva->up();
            $previa = $db->table('versiones_datos_clinicos_base')->where('numero_version', 1)->first();
            $this->assertSame($clave, $previa->clave_apertura);
            $this->assertEquals(170, $previa->altura_cm);
            $this->assertSame('Profesional original', $previa->autor_nombre_snapshot);
            $this->assertSame('2026-11-20 10:00:00', $previa->created_at);
            $db->table('versiones_datos_clinicos_base')->insert([...$version, 'numero_version' => 2, 'clave_apertura' => null, 'altura_cm' => '170.25']);
            $db->table('versiones_datos_clinicos_base')->insert([...$version, 'numero_version' => 3, 'clave_apertura' => null, 'altura_cm' => '170.75']);
            $this->assertEquals(170.25, $db->table('versiones_datos_clinicos_base')->where('numero_version', 2)->value('altura_cm'));
            $this->assertEquals(170.75, $db->table('versiones_datos_clinicos_base')->where('numero_version', 3)->value('altura_cm'));
            $this->assertSame(2, $db->table('versiones_datos_clinicos_base')->whereNull('clave_apertura')->count());
        } finally {
            DB::purge('uc14_compatibilidad');
            DB::setDefaultConnection($anterior);
            config(['database.connections.uc14_compatibilidad' => null]);
        }
    }
}
