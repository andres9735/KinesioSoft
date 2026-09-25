<?php

namespace App\Actions\Catalogos;

use App\Enums\TipoCategoriaClinicaEnum;
use App\Models\CategoriaClinica;
use App\Models\TipoEquipoTerapeutico;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ActualizarTipoEquipoTerapeuticoAction
{
    public function __construct(private readonly AuditoriaService $auditoriaService) {}

    public function ejecutar(TipoEquipoTerapeutico $tipoEquipo, array $data): TipoEquipoTerapeutico
    {
        return DB::transaction(function () use ($tipoEquipo, $data): TipoEquipoTerapeutico {
            $tipoActual = TipoEquipoTerapeutico::query()
                ->whereKey($tipoEquipo->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $categoriaSolicitadaId = filter_var(
                $data['categoria_clinica_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $categoriaActualId = (int) $tipoActual->getRawOriginal('categoria_clinica_id');

            if ($categoriaSolicitadaId === false || $categoriaSolicitadaId < 1) {
                $this->lanzarErrorCategoria();
            }

            if ($categoriaSolicitadaId !== $categoriaActualId) {
                $this->validarNuevaCategoria($categoriaSolicitadaId);
            }

            $datosResultantes = [
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'categoria_clinica_id' => $categoriaSolicitadaId,
            ];
            $valoresAnteriores = [];
            $valoresNuevos = [];

            foreach ($datosResultantes as $campo => $valorNuevo) {
                $valorAnterior = $tipoActual->getRawOriginal($campo);

                if ($campo === 'categoria_clinica_id') {
                    $valorAnterior = (int) $valorAnterior;
                }

                if ($valorAnterior !== $valorNuevo) {
                    $valoresAnteriores[$campo] = $valorAnterior;
                    $valoresNuevos[$campo] = $valorNuevo;
                }
            }

            if ($valoresNuevos === []) {
                return $tipoActual;
            }

            $tipoActual->fill($valoresNuevos);
            $tipoActual->save();

            $this->auditoriaService->registrar(
                evento: 'tipo_equipo_terapeutico_actualizado',
                modulo: 'Catálogos',
                auditable: $tipoActual,
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $valoresNuevos
            );

            return $tipoActual;
        });
    }

    private function validarNuevaCategoria(int $categoriaId): void
    {
        $categoria = CategoriaClinica::query()
            ->whereKey($categoriaId)
            ->lockForUpdate()
            ->first();

        if (
            ! $categoria
            || ! $categoria->activo
            || $categoria->tipo !== TipoCategoriaClinicaEnum::EQUIPAMIENTO
        ) {
            $this->lanzarErrorCategoria();
        }
    }

    private function lanzarErrorCategoria(): never
    {
        throw ValidationException::withMessages([
            'categoria_clinica_id' => 'La nueva categoría clínica debe estar activa y ser de tipo EQUIPAMIENTO.',
        ]);
    }
}
