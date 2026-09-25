<?php

namespace App\Actions\Catalogos;

use App\Enums\TipoCategoriaClinicaEnum;
use App\Models\CategoriaClinica;
use App\Models\EjercicioDomiciliario;
use App\Models\RecursoEjercicio;
use App\Services\AuditoriaService;
use App\Services\Catalogos\GestionRecursosEjercicioService;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ActualizarEjercicioDomiciliarioAction
{
    private const CAMPOS_ESCALARES = [
        'codigo',
        'nombre',
        'descripcion',
        'series_recomendadas',
        'repeticiones_recomendadas',
        'duracion_segundos_recomendada',
        'frecuencia_cantidad_base',
        'frecuencia_periodo_base',
        'descanso_segundos_base',
        'carga_resistencia_sugerida',
        'dificultad_base',
        'contraindicaciones',
        'notas_clinicas',
    ];

    public function __construct(
        private readonly AuditoriaService $auditoriaService,
        private readonly GestionRecursosEjercicioService $gestionRecursos
    ) {}

    public function ejecutar(
        EjercicioDomiciliario $ejercicioDomiciliario,
        array $datos
    ): EjercicioDomiciliario {
        $archivosNuevos = [];
        $archivosAntiguos = [];

        try {
            $ejercicio = DB::transaction(function () use (
                $ejercicioDomiciliario,
                $datos,
                &$archivosNuevos,
                &$archivosAntiguos
            ): EjercicioDomiciliario {
                $ejercicioActual = EjercicioDomiciliario::query()
                    ->whereKey($ejercicioDomiciliario->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $idsActuales = $ejercicioActual->categorias()
                    ->lockForUpdate()
                    ->pluck('categorias_clinicas.id')
                    ->map(fn ($id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all();
                $idsSolicitados = $this->normalizarIdsCategorias($datos['categorias'] ?? null);
                $categoriasSolicitadas = $this->obtenerCategoriasSolicitadas($idsSolicitados);
                $this->validarCategoriasResultantes(
                    $ejercicioActual,
                    $idsActuales,
                    $idsSolicitados,
                    $categoriasSolicitadas
                );

                $recursosSolicitados = $datos['recursos'] ?? [];

                if (! is_array($recursosSolicitados)) {
                    throw ValidationException::withMessages([
                        'recursos' => 'Los recursos deben enviarse como una colección.',
                    ]);
                }

                $recursosPersistidos = $ejercicioActual->recursos()
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(fn (RecursoEjercicio $recurso): int => (int) $recurso->getKey());
                $this->validarRecursosSolicitados($recursosSolicitados, $recursosPersistidos);

                $recursosAnteriores = $this->snapshotRecursos($recursosPersistidos);
                [$valoresAnteriores, $valoresNuevos] = $this->cambiosEscalares(
                    $ejercicioActual,
                    $datos
                );

                if ($valoresNuevos !== []) {
                    $ejercicioActual->fill($valoresNuevos);
                    $ejercicioActual->save();
                }

                $cambiaronCategorias = $idsActuales !== $idsSolicitados;

                if ($cambiaronCategorias) {
                    $ejercicioActual->categorias()->sync($idsSolicitados);
                    $valoresAnteriores['categorias'] = $idsActuales;
                    $valoresNuevos['categorias'] = $idsSolicitados;
                }

                $cambiaronRecursos = false;

                foreach ($recursosSolicitados as $datosRecurso) {
                    $id = $datosRecurso['id'] ?? null;

                    if ($id === null || $id === '') {
                        $preparado = $this->gestionRecursos->prepararNuevo($datosRecurso);
                        $this->acumularArchivos(
                            $preparado,
                            $archivosNuevos,
                            $archivosAntiguos
                        );
                        $ejercicioActual->recursos()->create($preparado['atributos']);
                        $cambiaronRecursos = true;

                        continue;
                    }

                    $recurso = $recursosPersistidos->get((int) $id);
                    $preparado = $this->gestionRecursos->prepararActualizacion(
                        $recurso,
                        $datosRecurso
                    );
                    $this->acumularArchivos(
                        $preparado,
                        $archivosNuevos,
                        $archivosAntiguos
                    );
                    $recurso->fill($preparado['atributos']);

                    if ($recurso->isDirty()) {
                        $recurso->save();
                        $cambiaronRecursos = true;
                    }
                }

                if ($cambiaronRecursos) {
                    $valoresAnteriores['recursos'] = $recursosAnteriores;
                    $valoresNuevos['recursos'] = $this->snapshotRecursos(
                        $ejercicioActual->recursos()->orderBy('id')->get()
                    );
                }

                if ($valoresNuevos === []) {
                    return $ejercicioActual;
                }

                $this->auditoriaService->registrar(
                    evento: 'ejercicio_domiciliario_actualizado',
                    modulo: 'Catálogos',
                    auditable: $ejercicioActual,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos
                );

                return $ejercicioActual;
            });
        } catch (Throwable $exception) {
            $this->gestionRecursos->limpiarArchivosNuevos($archivosNuevos);

            throw $exception;
        }

        $this->gestionRecursos->eliminarArchivosAntiguos($archivosAntiguos);

        return $ejercicio;
    }

    /**
     * @return list<int>
     */
    private function normalizarIdsCategorias(mixed $valor): array
    {
        if (! is_array($valor)) {
            $this->lanzarErrorCategorias('Las categorías deben enviarse como una colección.');
        }

        $ids = [];

        foreach ($valor as $id) {
            $idValidado = filter_var($id, FILTER_VALIDATE_INT);

            if ($idValidado === false || $idValidado < 1) {
                $this->lanzarErrorCategorias('Las categorías seleccionadas deben tener IDs válidos.');
            }

            if (in_array($idValidado, $ids, true)) {
                $this->lanzarErrorCategorias('Una categoría no puede repetirse.');
            }

            $ids[] = $idValidado;
        }

        sort($ids);

        return $ids;
    }

    private function obtenerCategoriasSolicitadas(array $ids): EloquentCollection
    {
        $categorias = CategoriaClinica::query()
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if (count($ids) !== $categorias->count()) {
            $this->lanzarErrorCategorias('Todas las categorías seleccionadas deben existir.');
        }

        return $categorias;
    }

    private function validarCategoriasResultantes(
        EjercicioDomiciliario $ejercicio,
        array $idsActuales,
        array $idsSolicitados,
        EloquentCollection $categorias
    ): void {
        $idsNuevos = array_diff($idsSolicitados, $idsActuales);

        foreach ($idsNuevos as $id) {
            $categoria = $categorias->get($id);

            if (! $categoria->activo) {
                $this->lanzarErrorCategorias('Una asociación nueva requiere una categoría activa.');
            }

            if ($categoria->tipo === TipoCategoriaClinicaEnum::EQUIPAMIENTO) {
                $this->lanzarErrorCategorias(
                    'Una asociación nueva no puede usar una categoría de EQUIPAMIENTO.'
                );
            }
        }

        if (! $ejercicio->activo) {
            return;
        }

        $tieneEquipamiento = false;
        $tieneAnatomicaActiva = false;

        foreach ($idsSolicitados as $id) {
            $categoria = $categorias->get($id);
            $tieneEquipamiento = $tieneEquipamiento
                || $categoria->tipo === TipoCategoriaClinicaEnum::EQUIPAMIENTO;
            $tieneAnatomicaActiva = $tieneAnatomicaActiva
                || ($categoria->activo
                    && $categoria->tipo === TipoCategoriaClinicaEnum::ANATOMICA);
        }

        if ($tieneEquipamiento) {
            $this->lanzarErrorCategorias(
                'Un ejercicio activo no puede conservar categorías de EQUIPAMIENTO.'
            );
        }

        if (! $tieneAnatomicaActiva) {
            $this->lanzarErrorCategorias(
                'El ejercicio activo requiere al menos una categoría ANATOMICA activa.'
            );
        }
    }

    private function validarRecursosSolicitados(
        array $recursosSolicitados,
        EloquentCollection $recursosPersistidos
    ): void {
        $idsProcesados = [];

        foreach ($recursosSolicitados as $indice => $datosRecurso) {
            if (! is_array($datosRecurso)) {
                throw ValidationException::withMessages([
                    "recursos.$indice" => 'Cada recurso debe enviarse como una colección de datos.',
                ]);
            }

            $id = $datosRecurso['id'] ?? null;

            if ($id === null || $id === '') {
                continue;
            }

            $idValidado = filter_var($id, FILTER_VALIDATE_INT);

            if ($idValidado === false || $idValidado < 1) {
                $this->lanzarErrorRecurso($indice, 'El recurso indicado no es válido.');
            }

            if (in_array($idValidado, $idsProcesados, true)) {
                $this->lanzarErrorRecurso($indice, 'El mismo recurso no puede repetirse en el payload.');
            }

            if (! $recursosPersistidos->has($idValidado)) {
                $this->lanzarErrorRecurso(
                    $indice,
                    'El recurso no pertenece al ejercicio editado.'
                );
            }

            $idsProcesados[] = $idValidado;
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function cambiosEscalares(
        EjercicioDomiciliario $ejercicio,
        array $datos
    ): array {
        $anteriores = [];
        $nuevos = [];

        foreach (self::CAMPOS_ESCALARES as $campo) {
            if (! array_key_exists($campo, $datos)) {
                continue;
            }

            $valorAnterior = $ejercicio->getRawOriginal($campo);
            $valorNuevo = $this->valorPersistible($datos[$campo]);

            if ($valorAnterior !== $valorNuevo) {
                $anteriores[$campo] = $valorAnterior;
                $nuevos[$campo] = $valorNuevo;
            }
        }

        return [$anteriores, $nuevos];
    }

    /**
     * @param  iterable<int, RecursoEjercicio>  $recursos
     * @return list<array<string, mixed>>
     */
    private function snapshotRecursos(iterable $recursos): array
    {
        $snapshot = [];

        foreach ($recursos as $recurso) {
            $snapshot[] = [
                'id' => (int) $recurso->id,
                'tipo' => $this->valorPersistible($recurso->tipo),
                'origen' => $this->valorPersistible($recurso->origen),
                'ruta_storage' => $recurso->ruta_storage,
                'url_externa' => $recurso->url_externa,
                'titulo' => $recurso->titulo,
                'orden' => (int) $recurso->orden,
                'activo' => (bool) $recurso->activo,
            ];
        }

        usort($snapshot, fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $snapshot;
    }

    /**
     * @param  array{
     *     atributos: array<string, mixed>,
     *     archivos_nuevos: list<string>,
     *     archivos_antiguos_a_eliminar: list<string>
     * }  $preparado
     * @param  list<string>  $archivosNuevos
     * @param  list<string>  $archivosAntiguos
     */
    private function acumularArchivos(
        array $preparado,
        array &$archivosNuevos,
        array &$archivosAntiguos
    ): void {
        $archivosNuevos = array_merge($archivosNuevos, $preparado['archivos_nuevos']);
        $archivosAntiguos = array_merge(
            $archivosAntiguos,
            $preparado['archivos_antiguos_a_eliminar']
        );
    }

    private function valorPersistible(mixed $valor): mixed
    {
        return $valor instanceof BackedEnum ? $valor->value : $valor;
    }

    private function lanzarErrorCategorias(string $mensaje): never
    {
        throw ValidationException::withMessages([
            'categorias' => $mensaje,
        ]);
    }

    private function lanzarErrorRecurso(int|string $indice, string $mensaje): never
    {
        throw ValidationException::withMessages([
            "recursos.$indice.id" => $mensaje,
        ]);
    }
}
