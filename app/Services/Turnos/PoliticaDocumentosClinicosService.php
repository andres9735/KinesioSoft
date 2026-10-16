<?php

namespace App\Services\Turnos;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

final class PoliticaDocumentosClinicosService
{
    /**
     * Ambas colecciones de errores vacías indican que la política se cumple.
     * Los índices originales se conservan; el consumidor decide cómo presentar los códigos.
     *
     * @param  array<array-key, mixed>  $archivos
     * @return array{coleccion: list<string>, archivos: array<array-key, list<string>>}
     */
    public function validar(array $archivos): array
    {
        $politica = config('documentos_clinicos');
        $errores = ['coleccion' => [], 'archivos' => []];
        $totalBytes = 0;

        if (count($archivos) > $politica['max_archivos']) {
            $errores['coleccion'][] = 'cantidad_excedida';
        }

        foreach ($archivos as $indice => $archivo) {
            if (! $archivo instanceof UploadedFile) {
                $errores['archivos'][$indice][] = 'tipo_invalido';

                continue;
            }

            if (! $archivo->isValid()) {
                $errores['archivos'][$indice][] = 'upload_invalido';

                continue;
            }

            try {
                $tamanio = $archivo->getSize();
            } catch (RuntimeException) {
                $tamanio = null;
            }

            if (! is_int($tamanio) || $tamanio < 0) {
                $errores['archivos'][$indice][] = 'tamanio_ilegible';
            } else {
                $totalBytes += $tamanio;

                if ($tamanio > $politica['max_bytes_por_archivo']) {
                    $errores['archivos'][$indice][] = 'tamanio_individual_excedido';
                }
            }

            try {
                $mime = $archivo->getMimeType();

                if (! in_array($mime, $politica['mime_permitidos'], true)) {
                    $errores['archivos'][$indice][] = 'mime_no_permitido';
                }
            } catch (InvalidArgumentException|RuntimeException) {
                $errores['archivos'][$indice][] = 'mime_ilegible';
            }
        }

        if ($totalBytes > $politica['max_bytes_total']) {
            $errores['coleccion'][] = 'tamanio_total_excedido';
        }

        return $errores;
    }
}
