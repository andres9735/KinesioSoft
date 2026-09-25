<?php

namespace App\Actions\Catalogos;

use App\Enums\TipoCategoriaClinicaEnum;
use App\Models\CategoriaClinica;
use App\Models\TipoEquipoTerapeutico;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CrearTipoEquipoTerapeuticoAction
{
    public function __construct(private readonly AuditoriaService $auditoriaService) {}

    public function ejecutar(array $data): TipoEquipoTerapeutico
    {
        return DB::transaction(function () use ($data): TipoEquipoTerapeutico {
            $categoria = $this->obtenerCategoriaValida($data['categoria_clinica_id'] ?? null);

            $tipoEquipo = TipoEquipoTerapeutico::create([
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'categoria_clinica_id' => $categoria->id,
                'activo' => true,
            ]);

            $this->auditoriaService->registrar(
                evento: 'tipo_equipo_terapeutico_creado',
                modulo: 'Catálogos',
                auditable: $tipoEquipo,
                valoresNuevos: [
                    'codigo' => $tipoEquipo->codigo,
                    'nombre' => $tipoEquipo->nombre,
                    'descripcion' => $tipoEquipo->descripcion,
                    'categoria_clinica_id' => $tipoEquipo->categoria_clinica_id,
                    'activo' => (bool) $tipoEquipo->activo,
                ]
            );

            return $tipoEquipo;
        });
    }

    private function obtenerCategoriaValida(mixed $categoriaId): CategoriaClinica
    {
        $categoriaIdValidado = filter_var($categoriaId, FILTER_VALIDATE_INT);
        $categoria = $categoriaIdValidado !== false && $categoriaIdValidado > 0
            ? CategoriaClinica::query()->whereKey($categoriaIdValidado)->lockForUpdate()->first()
            : null;

        if (
            ! $categoria
            || ! $categoria->activo
            || $categoria->tipo !== TipoCategoriaClinicaEnum::EQUIPAMIENTO
        ) {
            throw ValidationException::withMessages([
                'categoria_clinica_id' => 'La categoría clínica debe estar activa y ser de tipo EQUIPAMIENTO.',
            ]);
        }

        return $categoria;
    }
}
