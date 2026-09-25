<?php

namespace App\Actions\Catalogos;

use App\Enums\TipoCategoriaClinicaEnum;
use App\Models\EjercicioDomiciliario;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CambiarEstadoEjercicioDomiciliarioAction
{
    public function __construct(private readonly AuditoriaService $auditoriaService) {}

    public function ejecutar(
        EjercicioDomiciliario $ejercicioDomiciliario,
        bool $activoObjetivo
    ): EjercicioDomiciliario {
        return DB::transaction(function () use (
            $ejercicioDomiciliario,
            $activoObjetivo
        ): EjercicioDomiciliario {
            $ejercicioActual = EjercicioDomiciliario::query()
                ->whereKey($ejercicioDomiciliario->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $estadoAnterior = (bool) $ejercicioActual->activo;

            if ($estadoAnterior === $activoObjetivo) {
                return $ejercicioActual;
            }

            if ($activoObjetivo) {
                $this->validarCategoriasParaReactivar($ejercicioActual);
            }

            $ejercicioActual->activo = $activoObjetivo;
            $ejercicioActual->save();

            $this->auditoriaService->registrar(
                evento: $activoObjetivo
                    ? 'ejercicio_domiciliario_reactivado'
                    : 'ejercicio_domiciliario_inactivado',
                modulo: 'Catálogos',
                auditable: $ejercicioActual,
                valoresAnteriores: ['activo' => $estadoAnterior],
                valoresNuevos: ['activo' => $activoObjetivo]
            );

            return $ejercicioActual;
        });
    }

    private function validarCategoriasParaReactivar(EjercicioDomiciliario $ejercicio): void
    {
        $categorias = $ejercicio->categorias()->lockForUpdate()->get();
        $tieneEquipamiento = $categorias->contains(
            fn ($categoria): bool => $categoria->tipo === TipoCategoriaClinicaEnum::EQUIPAMIENTO
        );
        $tieneAnatomicaActiva = $categorias->contains(
            fn ($categoria): bool => $categoria->activo
                && $categoria->tipo === TipoCategoriaClinicaEnum::ANATOMICA
        );

        if ($tieneEquipamiento) {
            $this->lanzarErrorCategorias(
                'No se puede reactivar el ejercicio porque posee una categoría de EQUIPAMIENTO.'
            );
        }

        if (! $tieneAnatomicaActiva) {
            $this->lanzarErrorCategorias(
                'No se puede reactivar el ejercicio sin una categoría ANATOMICA activa.'
            );
        }
    }

    private function lanzarErrorCategorias(string $mensaje): never
    {
        throw ValidationException::withMessages([
            'categorias' => $mensaje,
        ]);
    }
}
