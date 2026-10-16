<?php

namespace App\Services\Turnos\Documentos;

use App\Exceptions\Turnos\AlmacenamientoDocumentalException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

final class OperacionAlmacenamientoDocumental
{
    /** @var list<array{ruta: string, bloqueo: BloqueoDocumental}> */
    private array $bloqueos = [];

    /** @var list<string> */
    private array $rutasPropias = [];

    /** @var array<array-key, array{ruta_storage: string, nombre_original: string, mime_type: string, tamanio_bytes: int}> */
    private array $documentos = [];

    private bool $finalizada = false;

    private function __construct(private readonly FilesystemAdapter $disco) {}

    /**
     * Uso interno del Service, después de validar la política 7.07.
     * No admite rutas proporcionadas por el caller.
     *
     * @param  array<array-key, UploadedFile>  $archivos
     */
    public static function almacenarValidados(
        array $archivos,
        FilesystemAdapter $disco,
        CoordinadorBloqueoDocumental $coordinador,
        string $directorio,
    ): self {
        if (! preg_match('/\A[a-z0-9_-]+(?:\/[a-z0-9_-]+)*\z/', $directorio) || strlen($directorio) + 37 > 255) {
            throw new RuntimeException('El namespace documental configurado no es válido.');
        }

        $operacion = new self($disco);

        try {
            foreach ($archivos as $indice => $archivo) {
                $mime = $archivo->getMimeType();
                $bytes = $archivo->getSize();

                if (! is_string($mime) || $mime === '' || ! is_int($bytes) || $bytes < 0) {
                    throw new RuntimeException('No se pudo recuperar la metadata del archivo validado.');
                }

                $ruta = $operacion->reservarRuta($coordinador, $directorio);
                // Registrar antes de abrir/escribir: un adapter puede crear bytes y luego fallar.
                $operacion->rutasPropias[] = $ruta;
                $stream = fopen($archivo->getRealPath(), 'rb');

                if ($stream === false) {
                    throw new RuntimeException('No se pudo abrir el archivo temporal.');
                }

                try {
                    if ($disco->put($ruta, $stream, ['visibility' => 'private']) !== true) {
                        throw new RuntimeException('No se confirmó la escritura documental.');
                    }
                } finally {
                    fclose($stream);
                }

                $operacion->documentos[$indice] = [
                    'ruta_storage' => $ruta,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime_type' => $mime,
                    'tamanio_bytes' => $bytes,
                ];
            }
        } catch (Throwable $original) {
            $operacion->finalizar(true, $original);

            throw new AlmacenamientoDocumentalException('Falló el almacenamiento documental.', $original);
        }

        return $operacion;
    }

    /** @return array<array-key, array{ruta_storage: string, nombre_original: string, mime_type: string, tamanio_bytes: int}> */
    public function documentos(): array
    {
        return $this->documentos;
    }

    public function conservarYLiberar(): void
    {
        $this->finalizar(false);
    }

    public function compensarYLiberar(): void
    {
        $this->finalizar(true);
    }

    private function reservarRuta(CoordinadorBloqueoDocumental $coordinador, string $directorio): string
    {
        for ($intento = 0; $intento < 3; $intento++) {
            $uuid = (string) Str::uuid();

            if (! Str::isUuid($uuid)) {
                throw new RuntimeException('El generador no devolvió una UUID válida.');
            }

            $ruta = $directorio.'/'.$uuid;
            $bloqueo = $coordinador->adquirir($ruta);
            $this->bloqueos[] = ['ruta' => $ruta, 'bloqueo' => $bloqueo];

            if (! $this->disco->exists($ruta)) {
                return $ruta;
            }

            $bloqueo->liberar();
            array_pop($this->bloqueos);
        }

        throw new RuntimeException('Se agotaron los intentos de generar una ruta documental libre.');
    }

    private function finalizar(bool $compensar, ?Throwable $original = null): void
    {
        if ($this->finalizada) {
            throw new LogicException('La operación documental ya fue finalizada.');
        }

        $this->finalizada = true;
        $erroresEliminacion = [];
        $erroresLiberacion = [];

        if ($compensar) {
            foreach ($this->rutasPropias as $ruta) {
                try {
                    if (! $this->disco->delete($ruta)) {
                        throw new RuntimeException('No se confirmó la eliminación documental.');
                    }
                } catch (Throwable $error) {
                    $erroresEliminacion[$ruta] = $error;
                }
            }
        }

        foreach ($this->bloqueos as ['ruta' => $ruta, 'bloqueo' => $bloqueo]) {
            try {
                $bloqueo->liberar();
            } catch (Throwable $error) {
                $erroresLiberacion[$ruta] = $error;
            }
        }

        if ($erroresEliminacion !== [] || $erroresLiberacion !== []) {
            throw new AlmacenamientoDocumentalException(
                'La finalización documental tuvo fallas de limpieza o liberación.',
                $original,
                erroresEliminacion: $erroresEliminacion,
                erroresLiberacion: $erroresLiberacion,
            );
        }
    }
}
