<?php

namespace App\Actions\Catalogos;

use App\Models\CategoriaClinica;
use App\Services\AuditoriaService;
use App\Services\Catalogos\JerarquiaCategoriaClinicaService;
use Illuminate\Support\Facades\DB;

final class CrearCategoriaClinicaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService,
        private readonly JerarquiaCategoriaClinicaService $jerarquiaCategoriaClinicaService
    ) {}

    public function ejecutar(array $data): CategoriaClinica
    {
        return DB::transaction(function () use ($data): CategoriaClinica {
            $this->jerarquiaCategoriaClinicaService->validarParaCrear(
                $data['tipo'],
                $data['categoria_padre_id'] ?? null
            );

            $categoriaClinica = CategoriaClinica::create([
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'tipo' => $data['tipo'],
                'categoria_padre_id' => $data['categoria_padre_id'] ?? null,
                'activo' => true,
            ]);

            $this->auditoriaService->registrar(
                evento: 'categoria_clinica_creada',
                modulo: 'Catálogos',
                auditable: $categoriaClinica,
                valoresNuevos: [
                    'codigo' => $categoriaClinica->codigo,
                    'nombre' => $categoriaClinica->nombre,
                    'tipo' => $categoriaClinica->tipo->value,
                    'categoria_padre_id' => $categoriaClinica->categoria_padre_id,
                    'activo' => (bool) $categoriaClinica->activo,
                ]
            );

            return $categoriaClinica;
        });
    }
}
