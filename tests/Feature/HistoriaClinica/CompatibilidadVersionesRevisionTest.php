<?php

namespace Tests\Feature\HistoriaClinica;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompatibilidadVersionesRevisionTest extends TestCase
{
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
