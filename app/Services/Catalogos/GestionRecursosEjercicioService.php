<?php

namespace App\Services\Catalogos;

use App\Enums\OrigenRecursoEjercicioEnum;
use App\Models\RecursoEjercicio;
use BackedEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

final class GestionRecursosEjercicioService
{
    private const DISK = 'public';

    private const DIRECTORIO_CONFIG = 'kinesiosoft.ejercicios.recursos_directorio';

    /**
     * @param  array<string, mixed>  $datos
     * @return array{
     *     atributos: array<string, mixed>,
     *     archivos_nuevos: list<string>,
     *     archivos_antiguos_a_eliminar: list<string>
     * }
     */
    public function prepararNuevo(array $datos): array
    {
        $this->validarDatosComunes($datos);
        $origen = $this->normalizarOrigen($datos['origen'] ?? null);
        $archivo = $this->obtenerArchivo($datos);

        if ($origen === OrigenRecursoEjercicioEnum::LOCAL) {
            $this->rechazarUrlParaLocal($datos);

            if (! $archivo instanceof UploadedFile) {
                throw new InvalidArgumentException(
                    'Un recurso LOCAL nuevo requiere un archivo.'
                );
            }

            $rutaNueva = $this->almacenar($archivo);

            return $this->resultado(
                $this->atributos($datos, $origen, $rutaNueva, null),
                [$rutaNueva]
            );
        }

        $this->rechazarArchivoParaExterno($archivo);
        $urlExterna = $this->urlInformada($datos);

        if ($urlExterna === null) {
            throw new InvalidArgumentException(
                'Un recurso EXTERNO nuevo requiere una URL externa.'
            );
        }

        return $this->resultado(
            $this->atributos($datos, $origen, null, $urlExterna)
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{
     *     atributos: array<string, mixed>,
     *     archivos_nuevos: list<string>,
     *     archivos_antiguos_a_eliminar: list<string>
     * }
     */
    public function prepararActualizacion(
        RecursoEjercicio $recurso,
        array $datos
    ): array {
        $this->validarDatosComunes($datos);
        $origenActual = $this->normalizarOrigen($recurso->origen);
        $origenNuevo = $this->normalizarOrigen($datos['origen'] ?? null);
        $archivo = $this->obtenerArchivo($datos);
        $rutaActual = $origenActual === OrigenRecursoEjercicioEnum::LOCAL
            ? $this->rutaLocalPersistida($recurso)
            : null;

        if ($origenNuevo === OrigenRecursoEjercicioEnum::LOCAL) {
            $this->rechazarUrlParaLocal($datos);

            if ($archivo instanceof UploadedFile) {
                $rutaNueva = $this->almacenar($archivo);
                $archivosAntiguos = $origenActual === OrigenRecursoEjercicioEnum::LOCAL
                    ? [$rutaActual]
                    : [];

                return $this->resultado(
                    $this->atributos($datos, $origenNuevo, $rutaNueva, null),
                    [$rutaNueva],
                    $archivosAntiguos
                );
            }

            if ($origenActual === OrigenRecursoEjercicioEnum::EXTERNO) {
                throw new InvalidArgumentException(
                    'Cambiar un recurso EXTERNO a LOCAL requiere un archivo.'
                );
            }

            return $this->resultado(
                $this->atributos(
                    $datos,
                    $origenNuevo,
                    $rutaActual,
                    null
                )
            );
        }

        $this->rechazarArchivoParaExterno($archivo);
        $urlExterna = $this->urlInformada($datos);

        if ($urlExterna === null) {
            if ($origenActual === OrigenRecursoEjercicioEnum::LOCAL) {
                throw new InvalidArgumentException(
                    'Cambiar un recurso LOCAL a EXTERNO requiere una URL externa.'
                );
            }

            $urlExterna = $this->urlExternaPersistida($recurso);
        }

        $archivosAntiguos = $origenActual === OrigenRecursoEjercicioEnum::LOCAL
            ? [$rutaActual]
            : [];

        return $this->resultado(
            $this->atributos($datos, $origenNuevo, null, $urlExterna),
            [],
            $archivosAntiguos
        );
    }

    /**
     * @param  array<int, mixed>  $rutas
     */
    public function limpiarArchivosNuevos(array $rutas): void
    {
        $this->eliminarRutas($rutas);
    }

    /**
     * @param  array<int, mixed>  $rutas
     */
    public function eliminarArchivosAntiguos(array $rutas): void
    {
        $this->eliminarRutas($rutas);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function atributos(
        array $datos,
        OrigenRecursoEjercicioEnum $origen,
        ?string $rutaStorage,
        ?string $urlExterna
    ): array {
        return [
            'tipo' => $this->valorPersistible($datos['tipo']),
            'origen' => $origen->value,
            'titulo' => $datos['titulo'],
            'orden' => $datos['orden'],
            'activo' => $datos['activo'],
            'ruta_storage' => $rutaStorage,
            'url_externa' => $urlExterna,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarDatosComunes(array $datos): void
    {
        foreach (['tipo', 'titulo', 'orden', 'activo'] as $campo) {
            if (! array_key_exists($campo, $datos)) {
                throw new InvalidArgumentException(
                    "El dato [$campo] es obligatorio para preparar un recurso."
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @param  list<string>  $archivosNuevos
     * @param  list<string>  $archivosAntiguos
     * @return array{
     *     atributos: array<string, mixed>,
     *     archivos_nuevos: list<string>,
     *     archivos_antiguos_a_eliminar: list<string>
     * }
     */
    private function resultado(
        array $atributos,
        array $archivosNuevos = [],
        array $archivosAntiguos = []
    ): array {
        return [
            'atributos' => $atributos,
            'archivos_nuevos' => $archivosNuevos,
            'archivos_antiguos_a_eliminar' => $archivosAntiguos,
        ];
    }

    private function almacenar(UploadedFile $archivo): string
    {
        $directorio = $this->directorioConfigurado();
        $ruta = $archivo->store($directorio, self::DISK);

        if (! is_string($ruta) || $ruta === '') {
            throw new RuntimeException(
                'No fue posible almacenar el archivo del recurso de ejercicio.'
            );
        }

        $this->validarRutaRelativa($ruta);

        if (! str_starts_with($ruta, $directorio.'/')) {
            throw new RuntimeException(
                'La ruta almacenada no pertenece al directorio configurado.'
            );
        }

        return $ruta;
    }

    private function directorioConfigurado(): string
    {
        $directorio = config(self::DIRECTORIO_CONFIG);

        if (! is_string($directorio)) {
            throw new RuntimeException(
                'El directorio de recursos de ejercicios no está configurado.'
            );
        }

        $directorio = rtrim(trim(str_replace('\\', '/', $directorio)), '/');
        $this->validarRutaRelativa($directorio);

        return $directorio;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function obtenerArchivo(array $datos): ?UploadedFile
    {
        $archivo = $datos['archivo'] ?? null;

        if ($archivo !== null && ! $archivo instanceof UploadedFile) {
            throw new InvalidArgumentException(
                'El archivo del recurso debe ser una instancia de UploadedFile.'
            );
        }

        return $archivo;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function rechazarUrlParaLocal(array $datos): void
    {
        if (array_key_exists('url_externa', $datos) && $datos['url_externa'] !== null) {
            throw new InvalidArgumentException(
                'Un recurso LOCAL no puede incluir una URL externa.'
            );
        }
    }

    private function rechazarArchivoParaExterno(?UploadedFile $archivo): void
    {
        if ($archivo instanceof UploadedFile) {
            throw new InvalidArgumentException(
                'Un recurso EXTERNO no puede incluir un archivo.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function urlInformada(array $datos): ?string
    {
        $url = $datos['url_externa'] ?? null;

        return is_string($url) && trim($url) !== '' ? $url : null;
    }

    private function rutaLocalPersistida(RecursoEjercicio $recurso): string
    {
        $ruta = $recurso->ruta_storage;

        if (! is_string($ruta) || $ruta === '') {
            throw new RuntimeException(
                'El recurso LOCAL persistido no posee una ruta de almacenamiento válida.'
            );
        }

        $this->validarRutaRelativa($ruta);

        return $ruta;
    }

    private function urlExternaPersistida(RecursoEjercicio $recurso): string
    {
        $url = $recurso->url_externa;

        if (! is_string($url) || trim($url) === '') {
            throw new RuntimeException(
                'El recurso EXTERNO persistido no posee una URL válida.'
            );
        }

        return $url;
    }

    private function normalizarOrigen(mixed $origen): OrigenRecursoEjercicioEnum
    {
        if ($origen instanceof OrigenRecursoEjercicioEnum) {
            return $origen;
        }

        $origenNormalizado = is_string($origen)
            ? OrigenRecursoEjercicioEnum::tryFrom($origen)
            : null;

        if (! $origenNormalizado instanceof OrigenRecursoEjercicioEnum) {
            throw new InvalidArgumentException(
                'El origen del recurso debe ser LOCAL o EXTERNO.'
            );
        }

        return $origenNormalizado;
    }

    private function valorPersistible(mixed $valor): mixed
    {
        return $valor instanceof BackedEnum ? $valor->value : $valor;
    }

    /**
     * @param  array<int, mixed>  $rutas
     */
    private function eliminarRutas(array $rutas): void
    {
        $rutasUnicas = [];

        foreach ($rutas as $ruta) {
            if (! is_string($ruta) || $ruta === '') {
                throw new InvalidArgumentException(
                    'Las rutas a eliminar deben ser rutas relativas no vacías.'
                );
            }

            $this->validarRutaRelativa($ruta);
            $rutasUnicas[$ruta] = true;
        }

        $disk = Storage::disk(self::DISK);

        foreach (array_keys($rutasUnicas) as $ruta) {
            if ($disk->exists($ruta)) {
                $disk->delete($ruta);
            }
        }
    }

    private function validarRutaRelativa(string $ruta): void
    {
        $rutaNormalizada = str_replace('\\', '/', $ruta);
        $segmentos = explode('/', $rutaNormalizada);

        if (
            trim($rutaNormalizada) === ''
            || str_starts_with($rutaNormalizada, '/')
            || preg_match('/^[A-Za-z]:\//', $rutaNormalizada) === 1
            || in_array('..', $segmentos, true)
        ) {
            throw new InvalidArgumentException(
                'Las rutas de recursos deben ser relativas y no pueden contener traversal.'
            );
        }
    }
}
