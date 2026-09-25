<?php

namespace App\Actions\Catalogos;

use App\Enums\TipoCategoriaClinicaEnum;
use App\Models\CategoriaClinica;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CambiarEstadoCategoriaClinicaAction
{
    public function __construct(private readonly AuditoriaService $auditoriaService) {}

    public function ejecutar(CategoriaClinica $categoriaClinica, bool $activoObjetivo): CategoriaClinica
    {
        return DB::transaction(function () use ($categoriaClinica, $activoObjetivo): CategoriaClinica {
            $categoriaActual = CategoriaClinica::query()
                ->whereKey($categoriaClinica->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = (bool) $categoriaActual->activo;

            if ($estadoAnterior === $activoObjetivo) {
                return $categoriaActual;
            }

            if ($activoObjetivo) {
                $this->validarDependenciasParaReactivar($categoriaActual);
            } else {
                $this->validarDependenciasParaInactivar($categoriaActual);
            }

            $categoriaActual->activo = $activoObjetivo;
            $categoriaActual->save();

            $this->auditoriaService->registrar(
                evento: $activoObjetivo ? 'categoria_clinica_reactivada' : 'categoria_clinica_inactivada',
                modulo: 'Catálogos',
                auditable: $categoriaActual,
                valoresAnteriores: ['activo' => $estadoAnterior],
                valoresNuevos: ['activo' => $activoObjetivo]
            );

            return $categoriaActual;
        });
    }

    private function validarDependenciasParaInactivar(CategoriaClinica $categoriaClinica): void
    {
        if ($categoriaClinica->hijas()->where('activo', true)->exists()) {
            $this->lanzarError('No se puede inactivar la categoría clínica porque tiene categorías hijas activas.');
        }

        if (
            $categoriaClinica->tipo === TipoCategoriaClinicaEnum::ANATOMICA
            && $this->existeEjercicioActivoSinOtraCategoriaAnatomicaActiva($categoriaClinica)
        ) {
            $this->lanzarError(
                'No se puede inactivar la categoría clínica porque un ejercicio activo quedaría sin una categoría anatómica activa.'
            );
        }

        if ($categoriaClinica->tiposEquipo()->where('activo', true)->exists()) {
            $this->lanzarError(
                'No se puede inactivar la categoría clínica porque tiene tipos de equipo terapéutico activos asociados.'
            );
        }
    }

    private function validarDependenciasParaReactivar(CategoriaClinica $categoriaClinica): void
    {
        if ($categoriaClinica->categoria_padre_id === null) {
            return;
        }

        $padre = CategoriaClinica::query()
            ->whereKey($categoriaClinica->categoria_padre_id)
            ->lockForUpdate()
            ->first();

        if (! $padre) {
            $this->lanzarError(
                'No se puede reactivar la categoría clínica porque su categoría padre no existe.'
            );
        }

        if (! $padre->activo) {
            $this->lanzarError(
                'No se puede reactivar la categoría clínica porque su categoría padre está inactiva.'
            );
        }

        if ($padre->tipo !== $categoriaClinica->tipo) {
            $this->lanzarError(
                'No se puede reactivar la categoría clínica porque su categoría padre tiene un tipo distinto.'
            );
        }
    }

    private function existeEjercicioActivoSinOtraCategoriaAnatomicaActiva(
        CategoriaClinica $categoriaClinica
    ): bool {
        return $categoriaClinica->ejercicios()
            ->where('ejercicios_domiciliarios.activo', true)
            ->whereDoesntHave('categorias', function ($query) use ($categoriaClinica): void {
                $query
                    ->where('categorias_clinicas.id', '!=', $categoriaClinica->getKey())
                    ->where('categorias_clinicas.tipo', TipoCategoriaClinicaEnum::ANATOMICA->value)
                    ->where('categorias_clinicas.activo', true);
            })
            ->exists();
    }

    private function lanzarError(string $mensaje): never
    {
        throw ValidationException::withMessages([
            'activo' => $mensaje,
        ]);
    }
}
