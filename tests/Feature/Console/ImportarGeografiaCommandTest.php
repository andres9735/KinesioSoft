<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class ImportarGeografiaCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporales = [];

    protected function tearDown(): void
    {
        $this->app->detectEnvironment(fn (): string => 'testing');

        foreach ($this->temporales as $directorio) {
            $this->eliminarDirectorio($directorio);
        }

        parent::tearDown();
    }

    public function test_command_es_descubierto_por_artisan(): void
    {
        $this->assertArrayHasKey('geografia:importar', Artisan::all());
    }

    public function test_falta_path_retorna_invalid(): void
    {
        $this->artisan('geografia:importar')
            ->expectsOutputToContain('Debe indicar --path')
            ->assertExitCode(Command::INVALID);
    }

    public function test_validar_fixture_valido_retorna_success_y_no_escribe(): void
    {
        $this->artisan('geografia:importar', [
            '--path' => $this->fixtureValido(),
            '--validar' => true,
        ])
            ->expectsOutputToContain('Validacion Georef exitosa')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('provincias', 0);
        $this->assertDatabaseCount('localidades', 0);
        $this->assertDatabaseCount('calles', 0);
    }

    public function test_importacion_valida_retorna_success(): void
    {
        $this->artisan('geografia:importar', [
            '--path' => $this->fixtureValido(),
        ])
            ->expectsOutputToContain('Validacion posterior exitosa')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('provincias', 2);
        $this->assertDatabaseCount('localidades', 2);
        $this->assertDatabaseCount('calles', 3);
    }

    public function test_source_invalido_retorna_failure(): void
    {
        $directorio = $this->copiarFixtureValido();
        file_put_contents(
            $directorio.'/provincias.csv',
            "id,nombre\n06,Buenos Aires\n06,Duplicada\n"
        );

        $this->artisan('geografia:importar', ['--path' => $directorio])
            ->expectsOutputToContain('georef_id_duplicado')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('provincias', 0);
    }

    public function test_production_sin_force_retorna_invalid_y_no_escribe(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('geografia:importar', [
            '--path' => $this->fixtureValido(),
        ])
            ->expectsOutputToContain('requiere --force')
            ->assertExitCode(Command::INVALID);

        $this->assertDatabaseCount('provincias', 0);
    }

    public function test_production_validar_sin_force_esta_permitido(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('geografia:importar', [
            '--path' => $this->fixtureValido(),
            '--validar' => true,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('provincias', 0);
    }

    public function test_production_con_force_permite_importar_fixture(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('geografia:importar', [
            '--path' => $this->fixtureValido(),
            '--force' => true,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('provincias', 2);
        $this->assertDatabaseCount('localidades', 2);
        $this->assertDatabaseCount('calles', 3);
    }

    private function fixtureValido(): string
    {
        return base_path('tests/Fixtures/georef/valido');
    }

    private function copiarFixtureValido(): string
    {
        $destino = storage_path('framework/testing/georef-command-'.uniqid('', true));

        if (! mkdir($destino, 0777, true) && ! is_dir($destino)) {
            throw new RuntimeException('No se pudo crear el directorio temporal.');
        }

        foreach (['provincias.csv', 'localidades_censales.csv', 'calles.csv'] as $archivo) {
            copy($this->fixtureValido().'/'.$archivo, $destino.'/'.$archivo);
        }

        $this->temporales[] = $destino;

        return str_replace('\\', '/', $destino);
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
