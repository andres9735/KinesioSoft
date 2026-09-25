<?php

namespace App\Actions\Catalogos;

use App\Enums\TipoCategoriaClinicaEnum;
use App\Models\CategoriaClinica;
use App\Services\AuditoriaService;
use App\Services\Catalogos\JerarquiaCategoriaClinicaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ActualizarCategoriaClinicaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService,
        private readonly JerarquiaCategoriaClinicaService $jerarquiaCategoriaClinicaService
    ) {}

    public function ejecutar(CategoriaClinica $categoriaClinica, array $data): CategoriaClinica
    {
        return DB::transaction(function () use ($categoriaClinica, $data): CategoriaClinica {
            $datosResultantes = [
                'codigo' => $data['codigo'] ?? $categoriaClinica->getRawOriginal('codigo'),
                'nombre' => $data['nombre'] ?? $categoriaClinica->getRawOriginal('nombre'),
                'tipo' => $data['tipo'] ?? $categoriaClinica->getRawOriginal('tipo'),
                'categoria_padre_id' => array_key_exists('categoria_padre_id', $data)
                    ? $data['categoria_padre_id']
                    : $categoriaClinica->categoria_padre_id,
            ];

            if ($datosResultantes['categoria_padre_id'] !== null) {
                $datosResultantes['categoria_padre_id'] = (int) $datosResultantes['categoria_padre_id'];
            }

            $this->jerarquiaCategoriaClinicaService->validarParaActualizar(
                $categoriaClinica,
                $datosResultantes['tipo'],
                $datosResultantes['categoria_padre_id']
            );

            $tipoAnterior = (string) $categoriaClinica->getRawOriginal('tipo');

            if ($tipoAnterior !== $datosResultantes['tipo']) {
                $this->validarDependenciasParaCambioDeTipo(
                    $categoriaClinica,
                    $tipoAnterior,
                    $datosResultantes['tipo']
                );
            }

            $valoresAnteriores = [];
            $valoresNuevos = [];

            foreach (['codigo', 'nombre', 'tipo', 'categoria_padre_id'] as $campo) {
                $valorAnterior = $categoriaClinica->getRawOriginal($campo);
                $valorNuevo = $datosResultantes[$campo];

                if ($valorAnterior !== $valorNuevo) {
                    $valoresAnteriores[$campo] = $valorAnterior;
                    $valoresNuevos[$campo] = $valorNuevo;
                }
            }

            if ($valoresNuevos === []) {
                return $categoriaClinica;
            }

            $categoriaClinica->fill($valoresNuevos);
            $categoriaClinica->save();

            $this->auditoriaService->registrar(
                evento: 'categoria_clinica_actualizada',
                modulo: 'Catálogos',
                auditable: $categoriaClinica,
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $valoresNuevos
            );

            return $categoriaClinica;
        });
    }

    private function validarDependenciasParaCambioDeTipo(
        CategoriaClinica $categoriaClinica,
        string $tipoAnterior,
        string $tipoNuevo
    ): void {
        if ($categoriaClinica->hijas()->where('tipo', '!=', $tipoNuevo)->exists()) {
            $this->lanzarError(
                'No se puede cambiar el tipo de la categoría clínica porque quedarían hijas con un tipo distinto.'
            );
        }

        if (
            $tipoNuevo === TipoCategoriaClinicaEnum::EQUIPAMIENTO->value
            && $categoriaClinica->ejercicios()
                ->where('ejercicios_domiciliarios.activo', true)
                ->exists()
        ) {
            $this->lanzarError(
                'No se puede cambiar el tipo de la categoría clínica a EQUIPAMIENTO porque tiene ejercicios activos asociados.'
            );
        }

        if (
            $categoriaClinica->activo
            && $tipoAnterior === TipoCategoriaClinicaEnum::ANATOMICA->value
            && $tipoNuevo !== TipoCategoriaClinicaEnum::ANATOMICA->value
            && $this->existeEjercicioActivoSinOtraCategoriaAnatomicaActiva($categoriaClinica)
        ) {
            $this->lanzarError(
                'No se puede cambiar el tipo de la categoría clínica porque un ejercicio activo quedaría sin una categoría anatómica activa.'
            );
        }

        if (
            $tipoNuevo !== TipoCategoriaClinicaEnum::EQUIPAMIENTO->value
            && $categoriaClinica->tiposEquipo()->where('activo', true)->exists()
        ) {
            $this->lanzarError(
                'No se puede cambiar el tipo de la categoría clínica porque tiene tipos de equipo terapéutico activos asociados.'
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
            'tipo' => $mensaje,
        ]);
    }
}
