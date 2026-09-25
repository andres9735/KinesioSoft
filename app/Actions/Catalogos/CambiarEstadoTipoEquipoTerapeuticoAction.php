<?php

namespace App\Actions\Catalogos;

use App\Enums\TipoCategoriaClinicaEnum;
use App\Models\CategoriaClinica;
use App\Models\TipoEquipoTerapeutico;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CambiarEstadoTipoEquipoTerapeuticoAction
{
    public function __construct(private readonly AuditoriaService $auditoriaService) {}

    public function ejecutar(
        TipoEquipoTerapeutico $tipoEquipoTerapeutico,
        bool $activoObjetivo
    ): TipoEquipoTerapeutico {
        return DB::transaction(function () use ($tipoEquipoTerapeutico, $activoObjetivo): TipoEquipoTerapeutico {
            $tipoActual = TipoEquipoTerapeutico::query()
                ->whereKey($tipoEquipoTerapeutico->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = (bool) $tipoActual->activo;

            if ($estadoAnterior === $activoObjetivo) {
                return $tipoActual;
            }

            if ($activoObjetivo) {
                $this->validarCategoriaParaReactivar($tipoActual);
            }

            $tipoActual->activo = $activoObjetivo;
            $tipoActual->save();

            $this->auditoriaService->registrar(
                evento: $activoObjetivo
                    ? 'tipo_equipo_terapeutico_reactivado'
                    : 'tipo_equipo_terapeutico_inactivado',
                modulo: 'Catálogos',
                auditable: $tipoActual,
                valoresAnteriores: ['activo' => $estadoAnterior],
                valoresNuevos: ['activo' => $activoObjetivo]
            );

            return $tipoActual;
        });
    }

    private function validarCategoriaParaReactivar(TipoEquipoTerapeutico $tipoEquipo): void
    {
        $categoria = CategoriaClinica::query()
            ->whereKey($tipoEquipo->categoria_clinica_id)
            ->lockForUpdate()
            ->first();

        if (
            ! $categoria
            || ! $categoria->activo
            || $categoria->tipo !== TipoCategoriaClinicaEnum::EQUIPAMIENTO
        ) {
            throw ValidationException::withMessages([
                'activo' => 'No se puede reactivar el tipo de equipo porque su categoría clínica debe estar activa y ser de tipo EQUIPAMIENTO.',
            ]);
        }
    }
}
