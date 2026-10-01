<?php

namespace App\Services\Geografia;

use Generator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

final class ImportadorGeorefService
{
    private const LOCK_KEY = 'kinesiosoft:geografia:importar';

    private const LOCK_TTL_SECONDS = 86400;

    private const PROVINCIAS_BATCH_SIZE = 24;

    private const LOCALIDADES_BATCH_SIZE = 500;

    private const CALLES_BATCH_SIZE = 1000;

    private const QUERY_BATCH_SIZE = 1000;

    private const MAX_ERROR_EXAMPLES_PER_CATEGORY = 20;

    private const MAX_ERROR_EXAMPLES_TOTAL = 100;

    private const ARCHIVOS = [
        'provincias' => 'provincias.csv',
        'localidades' => 'localidades_censales.csv',
        'calles' => 'calles.csv',
    ];

    private const HEADERS = [
        'provincias' => ['id', 'nombre'],
        'localidades' => [
            'id',
            'nombre',
            'departamento_nombre',
            'provincia_id',
            'provincia_nombre',
        ],
        'calles' => [
            'id',
            'nombre',
            'localidad_censal_id',
            'provincia_id',
            'provincia_nombre',
        ],
    ];

    /**
     * Ejecuta el preflight completo sin adquirir el lock ni escribir en la BD.
     *
     * @return array{
     *     modo: string,
     *     directorio: string,
     *     catalogos: array<string, array{filas_source: int, advertencias: list<string>}>,
     *     advertencias: list<string>
     * }
     */
    public function validar(string $directorio): array
    {
        $preflight = $this->preflight($directorio);

        return $this->crearResumenValidacion($preflight);
    }

    /**
     * Ejecuta preflight, importacion y validacion posterior bajo un lock atomico.
     *
     * El callback recibe payloads pequenos con las claves fase, catalogo,
     * procesadas y total. Se notifica como maximo una vez por lote o etapa.
     *
     * @return array{
     *     modo: string,
     *     directorio: string,
     *     catalogos: array<string, array{
     *         filas_source: int,
     *         insertadas: int,
     *         actualizadas: int,
     *         sin_cambios: int,
     *         ausentes_conservadas: int,
     *         total_bd: int,
     *         advertencias: list<string>
     *     }>,
     *     validacion_posterior: array{localidades_huerfanas: int, calles_huerfanas: int},
     *     advertencias: list<string>
     * }
     */
    public function importar(
        string $directorio,
        ?callable $informarProgreso = null
    ): array {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            throw new RuntimeException(
                'Ya existe una importacion de geografia en curso.'
            );
        }

        try {
            $preflight = $this->preflight($directorio);
            $this->notificar($informarProgreso, 'preflight', null, 1, 1);
            $this->verificarArchivosSinCambios($preflight['archivos']);

            $estadisticas = [];
            $estadisticas['provincias'] = $this->importarProvincias(
                $preflight,
                $informarProgreso
            );
            $mapaProvincias = $this->cargarMapaInterno(
                'provincias',
                $preflight['provincias_ids']
            );

            $estadisticas['localidades'] = $this->importarLocalidades(
                $preflight,
                $mapaProvincias,
                $informarProgreso
            );
            $mapaLocalidades = $this->cargarMapaInterno(
                'localidades',
                $preflight['localidades_ids']
            );

            $estadisticas['calles'] = $this->importarCalles(
                $preflight,
                $mapaLocalidades,
                $informarProgreso
            );

            $posterior = $this->validarPosteriormente(
                $preflight,
                $estadisticas
            );
            $this->notificar($informarProgreso, 'validacion_posterior', null, 1, 1);

            return $this->crearResumenImportacion(
                $preflight,
                $estadisticas,
                $posterior
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function preflight(string $directorio): array
    {
        $resolucion = $this->resolverArchivos($directorio);
        $this->validarEsquemaDestino();

        $errores = [];
        $totalEjemplos = 0;
        $advertencias = [
            'provincias' => [],
            'localidades' => [],
            'calles' => [],
        ];

        $provincias = $this->preflightProvincias(
            $resolucion['archivos']['provincias'],
            $advertencias['provincias'],
            $errores,
            $totalEjemplos
        );
        $localidades = $this->preflightLocalidades(
            $resolucion['archivos']['localidades'],
            $provincias,
            $advertencias['localidades'],
            $errores,
            $totalEjemplos
        );
        $calles = $this->preflightCalles(
            $resolucion['archivos']['calles'],
            $provincias,
            $localidades,
            $advertencias['calles'],
            $errores,
            $totalEjemplos
        );

        $this->lanzarErroresPreflight($errores);

        return [
            'directorio' => $resolucion['directorio'],
            'archivos' => $resolucion['archivos'],
            'advertencias' => $advertencias,
            'filas' => [
                'provincias' => $provincias['filas'],
                'localidades' => $localidades['filas'],
                'calles' => $calles['filas'],
            ],
            'provincias_ids' => $provincias['ids'],
            'provincias_nombres' => $provincias['nombres'],
            'localidades_ids' => $localidades['ids'],
            'localidades_provincias' => $localidades['provincias'],
            'calles_ids' => $calles['ids'],
        ];
    }

    /**
     * @return array{directorio: string, archivos: array<string, array{ruta: string, tamano: int, modificado: int}>}
     */
    private function resolverArchivos(string $directorio): array
    {
        if (trim($directorio) === '') {
            throw new InvalidArgumentException(
                'El directorio Georef no puede estar vacio.'
            );
        }

        $ruta = $this->esRutaAbsoluta($directorio)
            ? $directorio
            : base_path($directorio);
        $rutaReal = realpath($ruta);

        if ($rutaReal === false || ! is_dir($rutaReal)) {
            throw new InvalidArgumentException(
                "El directorio Georef no existe o no es un directorio: {$directorio}"
            );
        }

        $archivos = [];

        foreach (self::ARCHIVOS as $catalogo => $basename) {
            $archivo = $rutaReal.DIRECTORY_SEPARATOR.$basename;
            $archivoReal = realpath($archivo);

            if ($archivoReal === false || ! is_file($archivoReal)) {
                throw new RuntimeException(
                    "Falta el archivo obligatorio {$basename}."
                );
            }

            if (! is_readable($archivoReal)) {
                throw new RuntimeException(
                    "El archivo {$basename} no es legible."
                );
            }

            $archivos[$catalogo] = $this->estadoArchivo($archivoReal);
        }

        return [
            'directorio' => $rutaReal,
            'archivos' => $archivos,
        ];
    }

    private function esRutaAbsoluta(string $ruta): bool
    {
        return str_starts_with($ruta, '/')
            || str_starts_with($ruta, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $ruta) === 1;
    }

    /**
     * @return array{ruta: string, tamano: int, modificado: int}
     */
    private function estadoArchivo(string $ruta): array
    {
        clearstatcache(true, $ruta);
        $rutaReal = realpath($ruta);
        $tamano = filesize($ruta);
        $modificado = filemtime($ruta);

        if ($rutaReal === false || $tamano === false || $modificado === false) {
            throw new RuntimeException(
                "No fue posible obtener el estado del archivo {$ruta}."
            );
        }

        return [
            'ruta' => $rutaReal,
            'tamano' => $tamano,
            'modificado' => $modificado,
        ];
    }

    private function validarEsquemaDestino(): void
    {
        $requerido = [
            'provincias' => ['id', 'georef_id', 'nombre', 'created_at', 'updated_at'],
            'localidades' => [
                'id',
                'georef_id',
                'provincia_id',
                'nombre',
                'departamento_nombre',
                'created_at',
                'updated_at',
            ],
            'calles' => [
                'id',
                'georef_id',
                'localidad_id',
                'nombre',
                'created_at',
                'updated_at',
            ],
        ];

        foreach ($requerido as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                throw new RuntimeException(
                    "La tabla destino {$tabla} no existe."
                );
            }

            if (! Schema::hasColumns($tabla, $columnas)) {
                throw new RuntimeException(
                    "La tabla destino {$tabla} no contiene todas las columnas requeridas."
                );
            }
        }
    }

    /**
     * @param  array{ruta: string, tamano: int, modificado: int}  $archivo
     * @param  list<string>  $advertencias
     * @param  array<string, array{total: int, ejemplos: list<string>}>  $errores
     * @return array{filas: int, ids: array<string, true>, nombres: array<string, string>}
     */
    private function preflightProvincias(
        array $archivo,
        array &$advertencias,
        array &$errores,
        int &$totalEjemplos
    ): array {
        $ids = [];
        $nombres = [];
        $filas = 0;

        foreach ($this->leerCsv(
            $archivo,
            'provincias',
            $advertencias,
            $errores,
            $totalEjemplos,
            $filas
        ) as $registro) {
            $fila = $registro['datos'];
            $numero = $registro['registro'];
            $id = $fila['id'];
            $nombre = $fila['nombre'];
            $idValido = $this->validarId(
                $id,
                2,
                'provincias.csv',
                $numero,
                'id_provincia_invalido',
                $errores,
                $totalEjemplos
            );

            $this->validarTexto(
                $nombre,
                100,
                'provincias.csv',
                $numero,
                'nombre_provincia_invalido',
                $errores,
                $totalEjemplos
            );

            if (! $idValido) {
                continue;
            }

            $clave = $this->claveGeoref($id);

            if (isset($ids[$clave])) {
                $this->agregarError(
                    $errores,
                    $totalEjemplos,
                    'georef_id_duplicado',
                    "provincias.csv, registro {$numero}: id duplicado {$id}."
                );

                continue;
            }

            $ids[$clave] = true;
            $nombres[$clave] = $nombre;
        }

        return compact('filas', 'ids', 'nombres');
    }

    /**
     * @param  array{ruta: string, tamano: int, modificado: int}  $archivo
     * @param  array{ids: array<string, true>, nombres: array<string, string>}  $provincias
     * @param  list<string>  $advertencias
     * @param  array<string, array{total: int, ejemplos: list<string>}>  $errores
     * @return array{filas: int, ids: array<string, true>, provincias: array<string, string>}
     */
    private function preflightLocalidades(
        array $archivo,
        array $provincias,
        array &$advertencias,
        array &$errores,
        int &$totalEjemplos
    ): array {
        $ids = [];
        $provinciasPorLocalidad = [];
        $filas = 0;

        foreach ($this->leerCsv(
            $archivo,
            'localidades',
            $advertencias,
            $errores,
            $totalEjemplos,
            $filas
        ) as $registro) {
            $fila = $registro['datos'];
            $numero = $registro['registro'];
            $id = $fila['id'];
            $provinciaId = $fila['provincia_id'];
            $idValido = $this->validarId(
                $id,
                8,
                'localidades_censales.csv',
                $numero,
                'id_localidad_invalido',
                $errores,
                $totalEjemplos
            );
            $provinciaValida = $this->validarId(
                $provinciaId,
                2,
                'localidades_censales.csv',
                $numero,
                'provincia_id_invalido',
                $errores,
                $totalEjemplos
            );

            $this->validarTexto(
                $fila['nombre'],
                150,
                'localidades_censales.csv',
                $numero,
                'nombre_localidad_invalido',
                $errores,
                $totalEjemplos
            );
            $this->validarTexto(
                $fila['departamento_nombre'],
                100,
                'localidades_censales.csv',
                $numero,
                'departamento_invalido',
                $errores,
                $totalEjemplos
            );
            $this->validarTexto(
                $fila['provincia_nombre'],
                null,
                'localidades_censales.csv',
                $numero,
                'provincia_nombre_invalido',
                $errores,
                $totalEjemplos
            );

            if ($idValido) {
                $clave = $this->claveGeoref($id);

                if (isset($ids[$clave])) {
                    $this->agregarError(
                        $errores,
                        $totalEjemplos,
                        'georef_id_duplicado',
                        "localidades_censales.csv, registro {$numero}: id duplicado {$id}."
                    );
                } else {
                    $ids[$clave] = true;

                    if ($provinciaValida) {
                        $provinciasPorLocalidad[$clave] = $provinciaId;
                    }
                }
            }

            if (! $provinciaValida) {
                continue;
            }

            $claveProvincia = $this->claveGeoref($provinciaId);

            if (! isset($provincias['ids'][$claveProvincia])) {
                $this->agregarError(
                    $errores,
                    $totalEjemplos,
                    'provincia_inexistente',
                    "localidades_censales.csv, registro {$numero}: provincia_id {$provinciaId} no existe en provincias.csv."
                );

                continue;
            }

            if (
                isset($provincias['nombres'][$claveProvincia])
                && $fila['provincia_nombre'] !== $provincias['nombres'][$claveProvincia]
            ) {
                $this->agregarError(
                    $errores,
                    $totalEjemplos,
                    'provincia_nombre_inconsistente',
                    "localidades_censales.csv, registro {$numero}: provincia_nombre no coincide con provincia_id {$provinciaId}."
                );
            }
        }

        return [
            'filas' => $filas,
            'ids' => $ids,
            'provincias' => $provinciasPorLocalidad,
        ];
    }

    /**
     * @param  array{ruta: string, tamano: int, modificado: int}  $archivo
     * @param  array{ids: array<string, true>, nombres: array<string, string>}  $provincias
     * @param  array{ids: array<string, true>, provincias: array<string, string>}  $localidades
     * @param  list<string>  $advertencias
     * @param  array<string, array{total: int, ejemplos: list<string>}>  $errores
     * @return array{filas: int, ids: array<string, true>}
     */
    private function preflightCalles(
        array $archivo,
        array $provincias,
        array $localidades,
        array &$advertencias,
        array &$errores,
        int &$totalEjemplos
    ): array {
        $ids = [];
        $filas = 0;

        foreach ($this->leerCsv(
            $archivo,
            'calles',
            $advertencias,
            $errores,
            $totalEjemplos,
            $filas
        ) as $registro) {
            $fila = $registro['datos'];
            $numero = $registro['registro'];
            $id = $fila['id'];
            $localidadId = $fila['localidad_censal_id'];
            $provinciaId = $fila['provincia_id'];
            $idValido = $this->validarId(
                $id,
                15,
                'calles.csv',
                $numero,
                'id_calle_invalido',
                $errores,
                $totalEjemplos
            );
            $localidadValida = $this->validarId(
                $localidadId,
                8,
                'calles.csv',
                $numero,
                'localidad_censal_id_invalido',
                $errores,
                $totalEjemplos
            );
            $provinciaValida = $this->validarId(
                $provinciaId,
                2,
                'calles.csv',
                $numero,
                'provincia_id_invalido',
                $errores,
                $totalEjemplos
            );

            $this->validarTexto(
                $fila['nombre'],
                150,
                'calles.csv',
                $numero,
                'nombre_calle_invalido',
                $errores,
                $totalEjemplos
            );
            $this->validarTexto(
                $fila['provincia_nombre'],
                null,
                'calles.csv',
                $numero,
                'provincia_nombre_invalido',
                $errores,
                $totalEjemplos
            );

            if ($idValido) {
                $clave = $this->claveGeoref($id);

                if (isset($ids[$clave])) {
                    $this->agregarError(
                        $errores,
                        $totalEjemplos,
                        'georef_id_duplicado',
                        "calles.csv, registro {$numero}: id duplicado {$id}."
                    );
                } else {
                    $ids[$clave] = true;
                }
            }

            if (! $localidadValida) {
                continue;
            }

            $claveLocalidad = $this->claveGeoref($localidadId);

            if (! isset($localidades['ids'][$claveLocalidad])) {
                $this->agregarError(
                    $errores,
                    $totalEjemplos,
                    'localidad_inexistente',
                    "calles.csv, registro {$numero}: localidad_censal_id {$localidadId} no existe en localidades_censales.csv."
                );

                continue;
            }

            if (
                $provinciaValida
                && isset($localidades['provincias'][$claveLocalidad])
                && $localidades['provincias'][$claveLocalidad] !== $provinciaId
            ) {
                $this->agregarError(
                    $errores,
                    $totalEjemplos,
                    'provincia_localidad_inconsistente',
                    "calles.csv, registro {$numero}: provincia_id {$provinciaId} no corresponde a la localidad {$localidadId}."
                );
            }

            if (! $provinciaValida) {
                continue;
            }

            $claveProvincia = $this->claveGeoref($provinciaId);

            if (
                isset($provincias['nombres'][$claveProvincia])
                && $fila['provincia_nombre'] !== $provincias['nombres'][$claveProvincia]
            ) {
                $this->agregarError(
                    $errores,
                    $totalEjemplos,
                    'provincia_nombre_inconsistente',
                    "calles.csv, registro {$numero}: provincia_nombre no coincide con provincia_id {$provinciaId}."
                );
            }
        }

        return compact('filas', 'ids');
    }

    /**
     * @param  array{ruta: string, tamano: int, modificado: int}  $archivo
     * @param  list<string>  $advertencias
     * @param  array<string, array{total: int, ejemplos: list<string>}>  $errores
     * @return Generator<int, array{registro: int, datos: array<string, string>}>
     */
    private function leerCsv(
        array $archivo,
        string $catalogo,
        array &$advertencias,
        array &$errores,
        int &$totalEjemplos,
        int &$filas
    ): Generator {
        $this->verificarArchivoSinCambios($archivo);
        $handle = @fopen($archivo['ruta'], 'rb');

        if ($handle === false) {
            $this->agregarError(
                $errores,
                $totalEjemplos,
                'apertura_csv',
                basename($archivo['ruta']).': no fue posible abrir el archivo.'
            );

            return;
        }

        try {
            $header = fgetcsv($handle, null, ',', '"', '');

            if ($header === false) {
                $this->agregarError(
                    $errores,
                    $totalEjemplos,
                    'header_invalido',
                    basename($archivo['ruta']).': no contiene un header legible.'
                );

                return;
            }

            if (isset($header[0]) && is_string($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
            }

            $mapa = [];

            foreach ($header as $indice => $nombre) {
                if (! is_string($nombre) || ! mb_check_encoding($nombre, 'UTF-8')) {
                    $this->agregarError(
                        $errores,
                        $totalEjemplos,
                        'utf8_invalido',
                        basename($archivo['ruta']).', header columna '.($indice + 1).': UTF-8 invalido.'
                    );

                    continue;
                }

                if (array_key_exists($nombre, $mapa)) {
                    $this->agregarError(
                        $errores,
                        $totalEjemplos,
                        'header_duplicado',
                        basename($archivo['ruta']).": header duplicado {$nombre}."
                    );

                    continue;
                }

                $mapa[$nombre] = $indice;
            }

            foreach (self::HEADERS[$catalogo] as $requerido) {
                if (! array_key_exists($requerido, $mapa)) {
                    $this->agregarError(
                        $errores,
                        $totalEjemplos,
                        'header_faltante',
                        basename($archivo['ruta']).": falta el header {$requerido}."
                    );
                }
            }

            $extras = array_values(array_diff(array_keys($mapa), self::HEADERS[$catalogo]));

            if ($extras !== []) {
                $advertencias[] = basename($archivo['ruta'])
                    .': columnas adicionales ignoradas: '.implode(', ', $extras).'.';
            }

            $registro = 1;

            while (($valores = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $registro++;
                $filas++;

                if (count($valores) !== count($header)) {
                    $this->agregarError(
                        $errores,
                        $totalEjemplos,
                        'cantidad_columnas_invalida',
                        basename($archivo['ruta']).", registro {$registro}: se esperaban "
                            .count($header).' columnas y se leyeron '.count($valores).'.'
                    );

                    continue;
                }

                $utf8Valido = true;

                foreach ($valores as $indice => $valor) {
                    if (! is_string($valor) || ! mb_check_encoding($valor, 'UTF-8')) {
                        $utf8Valido = false;
                        $this->agregarError(
                            $errores,
                            $totalEjemplos,
                            'utf8_invalido',
                            basename($archivo['ruta']).", registro {$registro}, columna ".($indice + 1).': UTF-8 invalido.'
                        );
                    }
                }

                if (! $utf8Valido) {
                    continue;
                }

                $datos = [];

                foreach (self::HEADERS[$catalogo] as $requerido) {
                    $indice = $mapa[$requerido] ?? null;
                    $datos[$requerido] = $indice === null
                        ? ''
                        : (string) $valores[$indice];
                }

                yield [
                    'registro' => $registro,
                    'datos' => $datos,
                ];
            }

            if (! feof($handle)) {
                $this->agregarError(
                    $errores,
                    $totalEjemplos,
                    'lectura_csv',
                    basename($archivo['ruta']).': ocurrio un error durante la lectura.'
                );
            }
        } finally {
            fclose($handle);
            $this->verificarArchivoSinCambios($archivo);
        }
    }

    /**
     * @param  array<string, array{total: int, ejemplos: list<string>}>  $errores
     */
    private function validarId(
        string $valor,
        int $longitud,
        string $archivo,
        int $registro,
        string $categoria,
        array &$errores,
        int &$totalEjemplos
    ): bool {
        if (preg_match('/^\d{'.$longitud.'}$/D', $valor) === 1) {
            return true;
        }

        $this->agregarError(
            $errores,
            $totalEjemplos,
            $categoria,
            "{$archivo}, registro {$registro}: debe contener exactamente {$longitud} digitos."
        );

        return false;
    }

    /**
     * @param  array<string, array{total: int, ejemplos: list<string>}>  $errores
     */
    private function validarTexto(
        string $valor,
        ?int $longitudMaxima,
        string $archivo,
        int $registro,
        string $categoria,
        array &$errores,
        int &$totalEjemplos
    ): bool {
        if (trim($valor) === '') {
            $this->agregarError(
                $errores,
                $totalEjemplos,
                $categoria,
                "{$archivo}, registro {$registro}: el valor obligatorio esta vacio."
            );

            return false;
        }

        if ($longitudMaxima !== null && mb_strlen($valor, 'UTF-8') > $longitudMaxima) {
            $this->agregarError(
                $errores,
                $totalEjemplos,
                $categoria,
                "{$archivo}, registro {$registro}: supera {$longitudMaxima} caracteres."
            );

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, array{total: int, ejemplos: list<string>}>  $errores
     */
    private function agregarError(
        array &$errores,
        int &$totalEjemplos,
        string $categoria,
        string $ejemplo
    ): void {
        $errores[$categoria] ??= [
            'total' => 0,
            'ejemplos' => [],
        ];
        $errores[$categoria]['total']++;

        if (
            count($errores[$categoria]['ejemplos']) < self::MAX_ERROR_EXAMPLES_PER_CATEGORY
            && $totalEjemplos < self::MAX_ERROR_EXAMPLES_TOTAL
        ) {
            $errores[$categoria]['ejemplos'][] = $ejemplo;
            $totalEjemplos++;
        }
    }

    /**
     * @param  array<string, array{total: int, ejemplos: list<string>}>  $errores
     */
    private function lanzarErroresPreflight(array $errores): void
    {
        if ($errores === []) {
            return;
        }

        $lineas = ['El preflight Georef detecto errores:'];

        foreach ($errores as $categoria => $detalle) {
            $lineas[] = "- {$categoria}: {$detalle['total']} error(es).";

            foreach ($detalle['ejemplos'] as $ejemplo) {
                $lineas[] = "  * {$ejemplo}";
            }
        }

        throw new RuntimeException(implode(PHP_EOL, $lineas));
    }

    /**
     * @param  array<string, array{ruta: string, tamano: int, modificado: int}>  $archivos
     */
    private function verificarArchivosSinCambios(array $archivos): void
    {
        foreach ($archivos as $archivo) {
            $this->verificarArchivoSinCambios($archivo);
        }
    }

    /**
     * @param  array{ruta: string, tamano: int, modificado: int}  $esperado
     */
    private function verificarArchivoSinCambios(array $esperado): void
    {
        $actual = $this->estadoArchivo($esperado['ruta']);

        if ($actual !== $esperado) {
            throw new RuntimeException(
                'Un archivo Georef cambio durante la operacion: '
                .basename($esperado['ruta']).'.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $preflight
     * @return array{insertadas: int, actualizadas: int, sin_cambios: int}
     */
    private function importarProvincias(
        array $preflight,
        ?callable $informarProgreso
    ): array {
        $estadisticas = $this->estadisticasVacias();
        $archivo = $preflight['archivos']['provincias'];

        DB::transaction(function () use (
            $archivo,
            $preflight,
            $informarProgreso,
            &$estadisticas
        ): void {
            $ahora = now();
            $lote = [];
            $procesadas = 0;

            foreach ($this->filasPersistencia($archivo, 'provincias') as $fila) {
                $lote[] = [
                    'georef_id' => $fila['id'],
                    'nombre' => $fila['nombre'],
                ];

                if (count($lote) === self::PROVINCIAS_BATCH_SIZE) {
                    $this->verificarArchivoSinCambios($archivo);
                    $this->persistirLote(
                        'provincias',
                        $lote,
                        ['nombre'],
                        ['nombre', 'updated_at'],
                        $ahora,
                        $estadisticas
                    );
                    $procesadas += count($lote);
                    $this->notificar(
                        $informarProgreso,
                        'importacion',
                        'provincias',
                        $procesadas,
                        $preflight['filas']['provincias']
                    );
                    $lote = [];
                }
            }

            if ($lote !== []) {
                $this->verificarArchivoSinCambios($archivo);
                $this->persistirLote(
                    'provincias',
                    $lote,
                    ['nombre'],
                    ['nombre', 'updated_at'],
                    $ahora,
                    $estadisticas
                );
                $procesadas += count($lote);
                $this->notificar(
                    $informarProgreso,
                    'importacion',
                    'provincias',
                    $procesadas,
                    $preflight['filas']['provincias']
                );
            }
        });

        return $estadisticas;
    }

    /**
     * @param  array<string, mixed>  $preflight
     * @param  array<string, int>  $mapaProvincias
     * @return array{insertadas: int, actualizadas: int, sin_cambios: int}
     */
    private function importarLocalidades(
        array $preflight,
        array $mapaProvincias,
        ?callable $informarProgreso
    ): array {
        $estadisticas = $this->estadisticasVacias();
        $archivo = $preflight['archivos']['localidades'];

        DB::transaction(function () use (
            $archivo,
            $preflight,
            $mapaProvincias,
            $informarProgreso,
            &$estadisticas
        ): void {
            $ahora = now();
            $lote = [];
            $procesadas = 0;

            foreach ($this->filasPersistencia($archivo, 'localidades') as $fila) {
                $provinciaId = $mapaProvincias[$this->claveGeoref($fila['provincia_id'])]
                    ?? throw new RuntimeException(
                        "No se pudo resolver la provincia {$fila['provincia_id']} durante la importacion."
                    );
                $lote[] = [
                    'georef_id' => $fila['id'],
                    'provincia_id' => $provinciaId,
                    'nombre' => $fila['nombre'],
                    'departamento_nombre' => $fila['departamento_nombre'],
                ];

                if (count($lote) === self::LOCALIDADES_BATCH_SIZE) {
                    $this->verificarArchivoSinCambios($archivo);
                    $this->persistirLote(
                        'localidades',
                        $lote,
                        ['provincia_id', 'nombre', 'departamento_nombre'],
                        ['provincia_id', 'nombre', 'departamento_nombre', 'updated_at'],
                        $ahora,
                        $estadisticas
                    );
                    $procesadas += count($lote);
                    $this->notificar(
                        $informarProgreso,
                        'importacion',
                        'localidades',
                        $procesadas,
                        $preflight['filas']['localidades']
                    );
                    $lote = [];
                }
            }

            if ($lote !== []) {
                $this->verificarArchivoSinCambios($archivo);
                $this->persistirLote(
                    'localidades',
                    $lote,
                    ['provincia_id', 'nombre', 'departamento_nombre'],
                    ['provincia_id', 'nombre', 'departamento_nombre', 'updated_at'],
                    $ahora,
                    $estadisticas
                );
                $procesadas += count($lote);
                $this->notificar(
                    $informarProgreso,
                    'importacion',
                    'localidades',
                    $procesadas,
                    $preflight['filas']['localidades']
                );
            }
        });

        return $estadisticas;
    }

    /**
     * @param  array<string, mixed>  $preflight
     * @param  array<string, int>  $mapaLocalidades
     * @return array{insertadas: int, actualizadas: int, sin_cambios: int}
     */
    private function importarCalles(
        array $preflight,
        array $mapaLocalidades,
        ?callable $informarProgreso
    ): array {
        $estadisticas = $this->estadisticasVacias();
        $archivo = $preflight['archivos']['calles'];
        $lote = [];
        $procesadas = 0;

        foreach ($this->filasPersistencia($archivo, 'calles') as $fila) {
            $localidadId = $mapaLocalidades[$this->claveGeoref($fila['localidad_censal_id'])]
                ?? throw new RuntimeException(
                    "No se pudo resolver la localidad {$fila['localidad_censal_id']} durante la importacion."
                );
            $lote[] = [
                'georef_id' => $fila['id'],
                'localidad_id' => $localidadId,
                'nombre' => $fila['nombre'],
            ];

            if (count($lote) === self::CALLES_BATCH_SIZE) {
                $this->verificarArchivoSinCambios($archivo);
                $this->persistirLoteEnTransaccion(
                    'calles',
                    $lote,
                    ['localidad_id', 'nombre'],
                    ['localidad_id', 'nombre', 'updated_at'],
                    $estadisticas
                );
                $procesadas += count($lote);
                $this->notificar(
                    $informarProgreso,
                    'importacion',
                    'calles',
                    $procesadas,
                    $preflight['filas']['calles']
                );
                $lote = [];
            }
        }

        if ($lote !== []) {
            $this->verificarArchivoSinCambios($archivo);
            $this->persistirLoteEnTransaccion(
                'calles',
                $lote,
                ['localidad_id', 'nombre'],
                ['localidad_id', 'nombre', 'updated_at'],
                $estadisticas
            );
            $procesadas += count($lote);
            $this->notificar(
                $informarProgreso,
                'importacion',
                'calles',
                $procesadas,
                $preflight['filas']['calles']
            );
        }

        return $estadisticas;
    }

    /**
     * @param  array{ruta: string, tamano: int, modificado: int}  $archivo
     * @return Generator<int, array<string, string>>
     */
    private function filasPersistencia(array $archivo, string $catalogo): Generator
    {
        $advertencias = [];
        $errores = [];
        $totalEjemplos = 0;
        $filas = 0;

        foreach ($this->leerCsv(
            $archivo,
            $catalogo,
            $advertencias,
            $errores,
            $totalEjemplos,
            $filas
        ) as $registro) {
            yield $registro['datos'];
        }

        $this->lanzarErroresPreflight($errores);
    }

    /**
     * @param  list<array<string, mixed>>  $lote
     * @param  list<string>  $camposComparables
     * @param  list<string>  $columnasActualizables
     * @param  array{insertadas: int, actualizadas: int, sin_cambios: int}  $estadisticas
     */
    private function persistirLoteEnTransaccion(
        string $tabla,
        array $lote,
        array $camposComparables,
        array $columnasActualizables,
        array &$estadisticas
    ): void {
        DB::transaction(function () use (
            $tabla,
            $lote,
            $camposComparables,
            $columnasActualizables,
            &$estadisticas
        ): void {
            $this->persistirLote(
                $tabla,
                $lote,
                $camposComparables,
                $columnasActualizables,
                now(),
                $estadisticas
            );
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lote
     * @param  list<string>  $camposComparables
     * @param  list<string>  $columnasActualizables
     * @param  array{insertadas: int, actualizadas: int, sin_cambios: int}  $estadisticas
     */
    private function persistirLote(
        string $tabla,
        array $lote,
        array $camposComparables,
        array $columnasActualizables,
        mixed $ahora,
        array &$estadisticas
    ): void {
        $ids = array_column($lote, 'georef_id');
        $columnasConsulta = array_values(array_unique([
            'georef_id',
            ...$camposComparables,
        ]));
        $existentes = DB::table($tabla)
            ->whereIn('georef_id', $ids)
            ->get($columnasConsulta);
        $mapaExistentes = [];

        foreach ($existentes as $existente) {
            $mapaExistentes[$this->claveGeoref((string) $existente->georef_id)] = $existente;
        }

        $aPersistir = [];

        foreach ($lote as $fila) {
            $existente = $mapaExistentes[$this->claveGeoref($fila['georef_id'])] ?? null;

            if ($existente === null) {
                $estadisticas['insertadas']++;
                $aPersistir[] = [
                    ...$fila,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];

                continue;
            }

            $cambio = false;

            foreach ($camposComparables as $campo) {
                if ((string) $existente->{$campo} !== (string) $fila[$campo]) {
                    $cambio = true;
                    break;
                }
            }

            if (! $cambio) {
                $estadisticas['sin_cambios']++;

                continue;
            }

            $estadisticas['actualizadas']++;
            $aPersistir[] = [
                ...$fila,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        if ($aPersistir !== []) {
            DB::table($tabla)->upsert(
                $aPersistir,
                ['georef_id'],
                $columnasActualizables
            );
        }
    }

    /**
     * @param  array<string, true>  $idsSource
     * @return array<string, int>
     */
    private function cargarMapaInterno(string $tabla, array $idsSource): array
    {
        $mapa = [];

        foreach ($this->lotesIds($idsSource, self::QUERY_BATCH_SIZE) as $lote) {
            $registros = DB::table($tabla)
                ->whereIn('georef_id', $lote)
                ->get(['id', 'georef_id']);

            foreach ($registros as $registro) {
                $mapa[$this->claveGeoref((string) $registro->georef_id)] = (int) $registro->id;
            }
        }

        if (count($mapa) !== count($idsSource)) {
            throw new RuntimeException(
                "No se pudo construir completamente el mapa interno de {$tabla}."
            );
        }

        return $mapa;
    }

    /**
     * @param  array<string, mixed>  $preflight
     * @param  array<string, array{insertadas: int, actualizadas: int, sin_cambios: int}>  $estadisticas
     * @return array{
     *     catalogos: array<string, array{ausentes_conservadas: int, total_bd: int}>,
     *     localidades_huerfanas: int,
     *     calles_huerfanas: int
     * }
     */
    private function validarPosteriormente(
        array $preflight,
        array $estadisticas
    ): array {
        $sets = [
            'provincias' => $preflight['provincias_ids'],
            'localidades' => $preflight['localidades_ids'],
            'calles' => $preflight['calles_ids'],
        ];
        $catalogos = [];

        foreach ($sets as $tabla => $idsSource) {
            $faltantes = $this->contarFaltantesEnBd($tabla, $idsSource);

            if ($faltantes !== 0) {
                throw new RuntimeException(
                    "La validacion posterior detecto {$faltantes} registros de {$tabla} ausentes en la BD."
                );
            }

            $procesadas = array_sum($estadisticas[$tabla]);

            if ($procesadas !== count($idsSource)) {
                throw new RuntimeException(
                    "Las estadisticas de {$tabla} no coinciden con el source procesado."
                );
            }

            $catalogos[$tabla] = [
                'ausentes_conservadas' => $this->contarAusentesConservados(
                    $tabla,
                    $idsSource
                ),
                'total_bd' => DB::table($tabla)->count(),
            ];
        }

        $localidadesHuerfanas = DB::table('localidades as l')
            ->leftJoin('provincias as p', 'p.id', '=', 'l.provincia_id')
            ->whereNull('p.id')
            ->count();
        $callesHuerfanas = DB::table('calles as c')
            ->leftJoin('localidades as l', 'l.id', '=', 'c.localidad_id')
            ->whereNull('l.id')
            ->count();

        if ($localidadesHuerfanas !== 0 || $callesHuerfanas !== 0) {
            throw new RuntimeException(
                'La validacion posterior detecto relaciones geograficas huerfanas.'
            );
        }

        return [
            'catalogos' => $catalogos,
            'localidades_huerfanas' => $localidadesHuerfanas,
            'calles_huerfanas' => $callesHuerfanas,
        ];
    }

    /**
     * @param  array<string, true>  $idsSource
     */
    private function contarFaltantesEnBd(string $tabla, array $idsSource): int
    {
        $encontrados = 0;

        foreach ($this->lotesIds($idsSource, self::QUERY_BATCH_SIZE) as $lote) {
            $encontrados += DB::table($tabla)
                ->whereIn('georef_id', $lote)
                ->count();
        }

        return count($idsSource) - $encontrados;
    }

    /**
     * @param  array<string, true>  $idsSource
     */
    private function contarAusentesConservados(string $tabla, array $idsSource): int
    {
        $ausentes = 0;

        DB::table($tabla)
            ->select(['id', 'georef_id'])
            ->orderBy('id')
            ->chunkById(self::QUERY_BATCH_SIZE, function ($registros) use (
                $idsSource,
                &$ausentes
            ): void {
                foreach ($registros as $registro) {
                    if (! isset($idsSource[$this->claveGeoref((string) $registro->georef_id)])) {
                        $ausentes++;
                    }
                }
            }, 'id');

        return $ausentes;
    }

    /**
     * @param  array<string, true>  $ids
     * @return Generator<int, list<string>>
     */
    private function lotesIds(array $ids, int $tamano): Generator
    {
        $lote = [];

        foreach ($ids as $clave => $_presente) {
            $lote[] = substr($clave, 2);

            if (count($lote) === $tamano) {
                yield $lote;
                $lote = [];
            }
        }

        if ($lote !== []) {
            yield $lote;
        }
    }

    /**
     * @return array{insertadas: int, actualizadas: int, sin_cambios: int}
     */
    private function estadisticasVacias(): array
    {
        return [
            'insertadas' => 0,
            'actualizadas' => 0,
            'sin_cambios' => 0,
        ];
    }

    private function claveGeoref(string $georefId): string
    {
        return 'g:'.$georefId;
    }

    private function notificar(
        ?callable $callback,
        string $fase,
        ?string $catalogo,
        int $procesadas,
        int $total
    ): void {
        if ($callback === null) {
            return;
        }

        $callback([
            'fase' => $fase,
            'catalogo' => $catalogo,
            'procesadas' => $procesadas,
            'total' => $total,
        ]);
    }

    /**
     * @param  array<string, mixed>  $preflight
     * @return array<string, mixed>
     */
    private function crearResumenValidacion(array $preflight): array
    {
        $catalogos = [];

        foreach (array_keys(self::ARCHIVOS) as $catalogo) {
            $catalogos[$catalogo] = [
                'filas_source' => $preflight['filas'][$catalogo],
                'advertencias' => $preflight['advertencias'][$catalogo],
            ];
        }

        return [
            'modo' => 'validacion',
            'directorio' => $preflight['directorio'],
            'catalogos' => $catalogos,
            'advertencias' => $this->aplanarAdvertencias($preflight['advertencias']),
        ];
    }

    /**
     * @param  array<string, mixed>  $preflight
     * @param  array<string, array{insertadas: int, actualizadas: int, sin_cambios: int}>  $estadisticas
     * @param  array<string, mixed>  $posterior
     * @return array<string, mixed>
     */
    private function crearResumenImportacion(
        array $preflight,
        array $estadisticas,
        array $posterior
    ): array {
        $catalogos = [];

        foreach (array_keys(self::ARCHIVOS) as $catalogo) {
            $catalogos[$catalogo] = [
                'filas_source' => $preflight['filas'][$catalogo],
                ...$estadisticas[$catalogo],
                ...$posterior['catalogos'][$catalogo],
                'advertencias' => $preflight['advertencias'][$catalogo],
            ];
        }

        return [
            'modo' => 'importacion',
            'directorio' => $preflight['directorio'],
            'catalogos' => $catalogos,
            'validacion_posterior' => [
                'localidades_huerfanas' => $posterior['localidades_huerfanas'],
                'calles_huerfanas' => $posterior['calles_huerfanas'],
            ],
            'advertencias' => $this->aplanarAdvertencias($preflight['advertencias']),
        ];
    }

    /**
     * @param  array<string, list<string>>  $advertencias
     * @return list<string>
     */
    private function aplanarAdvertencias(array $advertencias): array
    {
        return array_values(array_merge(...array_values($advertencias)));
    }
}
