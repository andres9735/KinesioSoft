<?php

namespace App\Actions\Catalogos;

use App\Enums\TipoCategoriaClinicaEnum;
use App\Models\CategoriaClinica;
use App\Models\EjercicioDomiciliario;
use App\Models\RecursoEjercicio;
use App\Services\AuditoriaService;
use App\Services\Catalogos\GestionRecursosEjercicioService;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CrearEjercicioDomiciliarioAction
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

    public function ejecutar(array $datos): EjercicioDomiciliario
    {
        $archivosNuevos = [];
        $archivosAntiguos = [];

        try {
            $ejercicio = DB::transaction(function () use (
                $datos,
                &$archivosNuevos,
                &$archivosAntiguos
            ): EjercicioDomiciliario {
                $categorias = $this->obtenerCategoriasValidas($datos['categorias'] ?? null);
                $recursos = $datos['recursos'] ?? [];

                if (! is_array($recursos)) {
                    throw ValidationException::withMessages([
                        'recursos' => 'Los recursos deben enviarse como una colección.',
                    ]);
                }

                $atributos = [];

                foreach (self::CAMPOS_ESCALARES as $campo) {
                    $atributos[$campo] = $this->valorPersistible($datos[$campo] ?? null);
                }

                $atributos['activo'] = true;
                $ejercicio = EjercicioDomiciliario::create($atributos);
                $ejercicio->categorias()->sync($categorias->modelKeys());

                foreach ($recursos as $indice => $datosRecurso) {
                    if (! is_array($datosRecurso)) {
                        throw ValidationException::withMessages([
                            "recursos.$indice" => 'Cada recurso debe enviarse como una colección de datos.',
                        ]);
                    }

                    $preparado = $this->gestionRecursos->prepararNuevo($datosRecurso);
                    $archivosNuevos = array_merge($archivosNuevos, $preparado['archivos_nuevos']);
                    $archivosAntiguos = array_merge(
                        $archivosAntiguos,
                        $preparado['archivos_antiguos_a_eliminar']
                    );
                    $ejercicio->recursos()->create($preparado['atributos']);
                }

                $this->auditoriaService->registrar(
                    evento: 'ejercicio_domiciliario_creado',
                    modulo: 'Catálogos',
                    auditable: $ejercicio,
                    valoresNuevos: $this->snapshotAgregado($ejercicio)
                );

                return $ejercicio;
            });
        } catch (Throwable $exception) {
            $this->gestionRecursos->limpiarArchivosNuevos($archivosNuevos);

            throw $exception;
        }

        $this->gestionRecursos->eliminarArchivosAntiguos($archivosAntiguos);

        return $ejercicio;
    }

    private function obtenerCategoriasValidas(mixed $valor): Collection
    {
        $ids = $this->normalizarIdsCategorias($valor);
        $categorias = CategoriaClinica::query()
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get();

        if (count($ids) !== $categorias->count()) {
            $this->lanzarErrorCategorias('Todas las categorías seleccionadas deben existir.');
        }

        $tieneAnatomicaActiva = false;

        foreach ($categorias as $categoria) {
            if (! $categoria->activo) {
                $this->lanzarErrorCategorias('Todas las categorías seleccionadas deben estar activas.');
            }

            if ($categoria->tipo === TipoCategoriaClinicaEnum::EQUIPAMIENTO) {
                $this->lanzarErrorCategorias('No se permiten categorías de EQUIPAMIENTO.');
            }

            if ($categoria->tipo === TipoCategoriaClinicaEnum::ANATOMICA) {
                $tieneAnatomicaActiva = true;
            }
        }

        if (! $tieneAnatomicaActiva) {
            $this->lanzarErrorCategorias(
                'El ejercicio activo requiere al menos una categoría ANATOMICA activa.'
            );
        }

        return $categorias;
    }

    /**
     * @return list<int>
     */
    private function normalizarIdsCategorias(mixed $valor): array
    {
        if (! is_array($valor) || $valor === []) {
            $this->lanzarErrorCategorias('Debe seleccionar al menos una categoría.');
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

    /**
     * @return array<string, mixed>
     */
    private function snapshotAgregado(EjercicioDomiciliario $ejercicio): array
    {
        $snapshot = [];

        foreach (self::CAMPOS_ESCALARES as $campo) {
            $snapshot[$campo] = $this->valorPersistible($ejercicio->{$campo});
        }

        $snapshot['activo'] = (bool) $ejercicio->activo;
        $snapshot['categorias'] = $ejercicio->categorias()
            ->orderBy('categorias_clinicas.id')
            ->pluck('categorias_clinicas.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $snapshot['recursos'] = $ejercicio->recursos()
            ->orderBy('id')
            ->get()
            ->map(fn (RecursoEjercicio $recurso): array => [
                'id' => (int) $recurso->id,
                'tipo' => $this->valorPersistible($recurso->tipo),
                'origen' => $this->valorPersistible($recurso->origen),
                'ruta_storage' => $recurso->ruta_storage,
                'url_externa' => $recurso->url_externa,
                'titulo' => $recurso->titulo,
                'orden' => (int) $recurso->orden,
                'activo' => (bool) $recurso->activo,
            ])
            ->all();

        return $snapshot;
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
}
