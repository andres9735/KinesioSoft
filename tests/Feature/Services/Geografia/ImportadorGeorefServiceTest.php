<?php

namespace Tests\Feature\Services\Geografia;

use App\Models\Calle;
use App\Models\Localidad;
use App\Models\Paciente;
use App\Models\Provincia;
use App\Models\User;
use App\Services\Geografia\ImportadorGeorefService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class ImportadorGeorefServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $directorio) {
            $this->eliminarDirectorio($directorio);
        }

        parent::tearDown();
    }

    public function test_validar_acepta_fixture_correcto_y_no_escribe(): void
    {
        $resultado = $this->service()->validar($this->fixtureValido());

        $this->assertSame('validacion', $resultado['modo']);
        $this->assertSame(2, $resultado['catalogos']['provincias']['filas_source']);
        $this->assertSame(2, $resultado['catalogos']['localidades']['filas_source']);
        $this->assertSame(3, $resultado['catalogos']['calles']['filas_source']);
        $this->assertDatabaseCount('provincias', 0);
        $this->assertDatabaseCount('localidades', 0);
        $this->assertDatabaseCount('calles', 0);
    }

    public function test_importacion_inicial_preserva_ids_ceros_y_fks_internas(): void
    {
        $resultado = $this->service()->importar($this->fixtureValido());

        $provincia = Provincia::query()->where('georef_id', '06')->firstOrFail();
        $localidad = Localidad::query()->where('georef_id', '06441030')->firstOrFail();
        $calle = Calle::query()->where('georef_id', '064410300001234')->firstOrFail();

        $this->assertSame('06', $provincia->georef_id);
        $this->assertSame('06441030', $localidad->georef_id);
        $this->assertSame('064410300001234', $calle->georef_id);
        $this->assertSame($provincia->id, $localidad->provincia_id);
        $this->assertSame($localidad->id, $calle->localidad_id);
        $this->assertNotSame($provincia->georef_id, (string) $localidad->provincia_id);
        $this->assertNotSame($localidad->georef_id, (string) $calle->localidad_id);
        $this->assertSame(2, $resultado['catalogos']['provincias']['insertadas']);
        $this->assertSame(2, $resultado['catalogos']['localidades']['insertadas']);
        $this->assertSame(3, $resultado['catalogos']['calles']['insertadas']);
        $this->assertSame(0, $resultado['validacion_posterior']['localidades_huerfanas']);
        $this->assertSame(0, $resultado['validacion_posterior']['calles_huerfanas']);
    }

    public function test_segunda_importacion_identica_no_escribe_ni_cambia_ids_o_timestamps(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->service()->importar($this->fixtureValido());

        $antes = [
            'provincia' => Provincia::query()->where('georef_id', '06')->firstOrFail(),
            'localidad' => Localidad::query()->where('georef_id', '06441030')->firstOrFail(),
            'calle' => Calle::query()->where('georef_id', '064410300001234')->firstOrFail(),
        ];

        $this->travel(5)->minutes();
        $resultado = $this->service()->importar($this->fixtureValido());

        foreach ($antes as $tipo => $modelo) {
            $despues = $modelo->fresh();
            $this->assertSame($modelo->id, $despues->id, $tipo);
            $this->assertTrue($modelo->created_at->equalTo($despues->created_at), $tipo);
            $this->assertTrue($modelo->updated_at->equalTo($despues->updated_at), $tipo);
        }

        foreach ($resultado['catalogos'] as $catalogo) {
            $this->assertSame(0, $catalogo['insertadas']);
            $this->assertSame(0, $catalogo['actualizadas']);
            $this->assertSame($catalogo['filas_source'], $catalogo['sin_cambios']);
        }
    }

    public function test_cambio_de_nombre_conserva_id_y_created_at_y_actualiza_updated_at(): void
    {
        $directorio = $this->copiarFixtureValido();
        $this->travelTo(now()->startOfSecond());
        $this->service()->importar($directorio);
        $antes = Localidad::query()->where('georef_id', '06441030')->firstOrFail();

        $this->reemplazarEnArchivo(
            $directorio.'/localidades_censales.csv',
            '06441030,La Plata,La Plata,06,Buenos Aires',
            '06441030,La Plata Centro,La Plata,06,Buenos Aires'
        );
        $this->travel(5)->minutes();
        $resultado = $this->service()->importar($directorio);
        $despues = Localidad::query()->where('georef_id', '06441030')->firstOrFail();

        $this->assertSame($antes->id, $despues->id);
        $this->assertTrue($antes->created_at->equalTo($despues->created_at));
        $this->assertTrue($despues->updated_at->greaterThan($antes->updated_at));
        $this->assertSame('La Plata Centro', $despues->nombre);
        $this->assertSame(1, $resultado['catalogos']['localidades']['actualizadas']);
    }

    public function test_localidad_con_provincia_inexistente_falla_sin_escribir(): void
    {
        $directorio = $this->copiarFixtureValido();
        $this->reemplazarEnArchivo(
            $directorio.'/localidades_censales.csv',
            '06441030,La Plata,La Plata,06,Buenos Aires',
            '06441030,La Plata,La Plata,99,Atlantis'
        );

        try {
            $this->service()->importar($directorio);
            $this->fail('Se esperaba un error de preflight.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('provincia_inexistente', $exception->getMessage());
        }

        $this->assertTablasGeograficasVacias();
    }

    public function test_calle_con_localidad_inexistente_falla_sin_escribir(): void
    {
        $directorio = $this->copiarFixtureValido();
        $this->reemplazarEnArchivo(
            $directorio.'/calles.csv',
            '06441030,06,Buenos Aires',
            '99999999,06,Buenos Aires'
        );

        try {
            $this->service()->importar($directorio);
            $this->fail('Se esperaba un error de preflight.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('localidad_inexistente', $exception->getMessage());
        }

        $this->assertTablasGeograficasVacias();
    }

    public function test_georef_id_duplicado_falla_antes_de_escribir(): void
    {
        $directorio = $this->copiarFixtureValido();
        file_put_contents(
            $directorio.'/provincias.csv',
            "id,nombre\n06,Buenos Aires\n06,Otra provincia\n"
        );

        try {
            $this->service()->importar($directorio);
            $this->fail('Se esperaba un error de preflight.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('georef_id_duplicado', $exception->getMessage());
        }

        $this->assertTablasGeograficasVacias();
    }

    public function test_header_requerido_ausente_falla_antes_de_escribir(): void
    {
        $directorio = $this->copiarFixtureValido();
        $this->reemplazarEnArchivo(
            $directorio.'/provincias.csv',
            "id,nombre\n06,Buenos Aires\n54,Misiones\n",
            "id\n06\n54\n"
        );

        $this->assertImportacionFallaSinEscribir($directorio, 'header_faltante');
    }

    public function test_utf8_invalido_falla_antes_de_escribir(): void
    {
        $directorio = $this->copiarFixtureValido();
        $archivo = $directorio.'/provincias.csv';
        $contenido = file_get_contents($archivo);

        $this->assertNotFalse($contenido);
        $contenidoInvalido = str_replace(
            'Buenos Aires',
            "Buenos \xC3\x28 Aires",
            $contenido,
            $reemplazos
        );

        $this->assertSame(1, $reemplazos);
        $this->assertFalse(mb_check_encoding($contenidoInvalido, 'UTF-8'));
        $this->assertNotFalse(file_put_contents($archivo, $contenidoInvalido));
        clearstatcache(true, $archivo);

        $this->assertImportacionFallaSinEscribir($directorio, 'utf8_invalido');
    }

    public function test_cantidad_de_columnas_invalida_falla_antes_de_escribir(): void
    {
        $directorio = $this->copiarFixtureValido();
        $this->reemplazarEnArchivo(
            $directorio.'/provincias.csv',
            '06,Buenos Aires',
            '06'
        );

        $this->assertImportacionFallaSinEscribir(
            $directorio,
            'cantidad_columnas_invalida'
        );
    }

    public function test_nombre_de_provincia_con_longitud_excedida_falla_antes_de_escribir(): void
    {
        $directorio = $this->copiarFixtureValido();
        $nombreLargo = str_repeat("\u{00C1}", 101);

        $this->assertTrue(mb_check_encoding($nombreLargo, 'UTF-8'));
        $this->assertSame(101, mb_strlen($nombreLargo, 'UTF-8'));
        $this->reemplazarEnArchivo(
            $directorio.'/provincias.csv',
            'Buenos Aires',
            $nombreLargo
        );

        $this->assertImportacionFallaSinEscribir(
            $directorio,
            'nombre_provincia_invalido',
            'supera 100 caracteres'
        );
    }

    public function test_nombre_de_provincia_vacio_falla_antes_de_escribir(): void
    {
        $directorio = $this->copiarFixtureValido();
        $this->reemplazarEnArchivo(
            $directorio.'/provincias.csv',
            '06,Buenos Aires',
            '06,   '
        );

        $this->assertImportacionFallaSinEscribir(
            $directorio,
            'nombre_provincia_invalido',
            'valor obligatorio esta vacio'
        );
    }

    public function test_misma_calle_y_nombre_con_georef_distinto_es_aceptada(): void
    {
        $this->service()->importar($this->fixtureValido());

        $this->assertSame(
            2,
            Calle::query()
                ->where('localidad_id', Localidad::where('georef_id', '06441030')->value('id'))
                ->where('nombre', 'Calle 12')
                ->count()
        );
    }

    public function test_registro_ausente_del_snapshot_se_conserva_y_reporta(): void
    {
        $directorio = $this->copiarFixtureValido();
        $this->service()->importar($directorio);
        $calleId = Calle::query()->where('georef_id', '540280100000001')->value('id');

        $this->reemplazarEnArchivo(
            $directorio.'/calles.csv',
            "540280100000001,San Martín,54028010,54,Misiones\n",
            ''
        );
        $resultado = $this->service()->importar($directorio);

        $this->assertDatabaseHas('calles', [
            'id' => $calleId,
            'georef_id' => '540280100000001',
        ]);
        $this->assertSame(1, $resultado['catalogos']['calles']['ausentes_conservadas']);
        $this->assertSame(3, $resultado['catalogos']['calles']['total_bd']);
    }

    public function test_estado_parcial_se_completa_sin_duplicar_existentes(): void
    {
        $provincia = Provincia::create([
            'georef_id' => '06',
            'nombre' => 'Buenos Aires',
        ]);
        $localidad = Localidad::create([
            'georef_id' => '06441030',
            'provincia_id' => $provincia->id,
            'nombre' => 'La Plata',
            'departamento_nombre' => 'La Plata',
        ]);

        $resultado = $this->service()->importar($this->fixtureValido());

        $this->assertDatabaseCount('provincias', 2);
        $this->assertDatabaseCount('localidades', 2);
        $this->assertDatabaseCount('calles', 3);
        $this->assertSame($provincia->id, Provincia::where('georef_id', '06')->value('id'));
        $this->assertSame($localidad->id, Localidad::where('georef_id', '06441030')->value('id'));
        $this->assertSame(1, $resultado['catalogos']['provincias']['sin_cambios']);
        $this->assertSame(1, $resultado['catalogos']['localidades']['sin_cambios']);
    }

    public function test_columnas_adicionales_se_aceptan_y_reportan(): void
    {
        $directorio = $this->copiarFixtureValido();
        $this->reemplazarEnArchivo(
            $directorio.'/provincias.csv',
            "id,nombre\n06,Buenos Aires\n54,Misiones\n",
            "nombre,extra,id\nBuenos Aires,ignorado,06\nMisiones,ignorado,54\n"
        );

        $resultado = $this->service()->validar($directorio);

        $this->assertCount(1, $resultado['catalogos']['provincias']['advertencias']);
        $this->assertStringContainsString(
            'extra',
            $resultado['catalogos']['provincias']['advertencias'][0]
        );
    }

    public function test_reimportacion_conserva_fk_historica_de_paciente(): void
    {
        $directorio = $this->copiarFixtureValido();
        $this->service()->importar($directorio);
        $localidad = Localidad::query()->where('georef_id', '06441030')->firstOrFail();
        $paciente = Paciente::create([
            'user_id' => User::factory()->create()->id,
            'localidad_id' => $localidad->id,
            'direccion' => 'Direccion historica',
        ]);

        $this->reemplazarEnArchivo(
            $directorio.'/localidades_censales.csv',
            '06441030,La Plata,La Plata,06,Buenos Aires',
            '06441030,La Plata Centro,La Plata,06,Buenos Aires'
        );
        $this->service()->importar($directorio);

        $this->assertSame($localidad->id, $paciente->fresh()->localidad_id);
        $this->assertSame('La Plata Centro', $paciente->fresh()->localidad->nombre);
    }

    public function test_importar_falla_sin_escribir_si_el_lock_esta_adquirido(): void
    {
        $lock = Cache::lock('kinesiosoft:geografia:importar', 60);
        $this->assertTrue($lock->get());

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('importacion de geografia en curso');
            $this->service()->importar($this->fixtureValido());
        } finally {
            $lock->release();
            $this->assertDatabaseCount('provincias', 0);
        }
    }

    private function service(): ImportadorGeorefService
    {
        return app(ImportadorGeorefService::class);
    }

    private function fixtureValido(): string
    {
        return base_path('tests/Fixtures/georef/valido');
    }

    private function copiarFixtureValido(): string
    {
        $destino = storage_path('framework/testing/georef-'.uniqid('', true));

        if (! mkdir($destino, 0777, true) && ! is_dir($destino)) {
            throw new RuntimeException('No se pudo crear el directorio temporal.');
        }

        foreach (['provincias.csv', 'localidades_censales.csv', 'calles.csv'] as $archivo) {
            copy($this->fixtureValido().'/'.$archivo, $destino.'/'.$archivo);
        }

        $this->temporales[] = $destino;

        return str_replace('\\', '/', $destino);
    }

    private function reemplazarEnArchivo(
        string $archivo,
        string $buscar,
        string $reemplazar
    ): void {
        $contenido = file_get_contents($archivo);

        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el fixture temporal.');
        }

        file_put_contents($archivo, str_replace($buscar, $reemplazar, $contenido));
        clearstatcache(true, $archivo);
    }

    private function assertTablasGeograficasVacias(): void
    {
        $this->assertDatabaseCount('provincias', 0);
        $this->assertDatabaseCount('localidades', 0);
        $this->assertDatabaseCount('calles', 0);
    }

    private function assertImportacionFallaSinEscribir(
        string $directorio,
        string ...$mensajesEsperados
    ): void {
        try {
            $this->service()->importar($directorio);
            $this->fail('Se esperaba un error de preflight.');
        } catch (RuntimeException $exception) {
            foreach ($mensajesEsperados as $mensajeEsperado) {
                $this->assertStringContainsString(
                    $mensajeEsperado,
                    $exception->getMessage()
                );
            }
        }

        $this->assertTablasGeograficasVacias();
    }

    private function eliminarDirectorio(string $directorio): void
    {
        if (! is_dir($directorio)) {
            return;
        }

        foreach (scandir($directorio) ?: [] as $archivo) {
            if ($archivo !== '.' && $archivo !== '..') {
                unlink($directorio.DIRECTORY_SEPARATOR.$archivo);
            }
        }

        rmdir($directorio);
    }
}
