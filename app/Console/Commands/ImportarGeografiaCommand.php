<?php

namespace App\Console\Commands;

use App\Services\Geografia\ImportadorGeorefService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

final class ImportarGeografiaCommand extends Command
{
    protected $signature = 'geografia:importar
        {--path= : Directorio que contiene los tres CSV Georef}
        {--validar : Ejecuta solo preflight}
        {--force : Autoriza escritura cuando APP_ENV=production}';

    protected $description = 'Valida o importa los catalogos geograficos locales de Georef';

    public function handle(ImportadorGeorefService $service): int
    {
        $path = $this->option('path');

        if (! is_string($path) || trim($path) === '') {
            $this->error('Debe indicar --path con el directorio de los CSV Georef.');

            return self::INVALID;
        }

        $soloValidar = (bool) $this->option('validar');

        if (
            ! $soloValidar
            && app()->environment('production')
            && ! (bool) $this->option('force')
        ) {
            $this->error(
                'En production, la importacion con escritura requiere --force.'
            );

            return self::INVALID;
        }

        $inicio = microtime(true);

        try {
            $this->info('Iniciando preflight Georef...');

            if ($soloValidar) {
                $resultado = $service->validar($path);
                $this->info('Preflight finalizado correctamente.');
                $this->mostrarFilasSource($resultado);
                $this->mostrarAdvertencias($resultado);
                $this->line('Ruta efectiva: '.$resultado['directorio']);
                $this->info('Validacion Georef exitosa.');
                $this->mostrarDuracion($inicio);

                return self::SUCCESS;
            }

            $resultado = $service->importar(
                $path,
                function (array $progreso): void {
                    if ($progreso['fase'] === 'preflight') {
                        $this->info('Preflight finalizado correctamente.');

                        return;
                    }

                    if (
                        $progreso['fase'] === 'importacion'
                        && is_string($progreso['catalogo'])
                    ) {
                        $this->line(sprintf(
                            '%s: %d/%d procesadas.',
                            ucfirst($progreso['catalogo']),
                            $progreso['procesadas'],
                            $progreso['total']
                        ));
                    }
                }
            );

            $this->info('Importacion finalizada correctamente.');
            $this->line('Ruta efectiva: '.$resultado['directorio']);
            $this->mostrarResumenImportacion($resultado);
            $this->mostrarAdvertencias($resultado);
            $this->info('Validacion posterior exitosa.');
            $this->mostrarDuracion($inicio);

            return self::SUCCESS;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function mostrarFilasSource(array $resultado): void
    {
        foreach ($resultado['catalogos'] as $catalogo => $resumen) {
            $this->line(sprintf(
                '%s: %d filas validas.',
                ucfirst($catalogo),
                $resumen['filas_source']
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function mostrarResumenImportacion(array $resultado): void
    {
        $this->table(
            [
                'Catalogo',
                'Source',
                'Insertadas',
                'Actualizadas',
                'Sin cambios',
                'Ausentes conservadas',
                'Total BD',
            ],
            collect($resultado['catalogos'])
                ->map(fn (array $resumen, string $catalogo): array => [
                    $catalogo,
                    $resumen['filas_source'],
                    $resumen['insertadas'],
                    $resumen['actualizadas'],
                    $resumen['sin_cambios'],
                    $resumen['ausentes_conservadas'],
                    $resumen['total_bd'],
                ])
                ->values()
                ->all()
        );
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function mostrarAdvertencias(array $resultado): void
    {
        foreach ($resultado['advertencias'] as $advertencia) {
            $this->warn($advertencia);
        }
    }

    private function mostrarDuracion(float $inicio): void
    {
        $this->line(sprintf(
            'Tiempo aproximado: %.2f segundos.',
            microtime(true) - $inicio
        ));
    }
}
