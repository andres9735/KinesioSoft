<?php

namespace App\Actions\Catalogos;

use App\Models\PruebaFuncional;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CrearPruebaFuncionalAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(array $data): PruebaFuncional
    {
        return DB::transaction(function () use ($data): PruebaFuncional {
            $pruebaFuncional = PruebaFuncional::create([
                'sistema_origen' => $data['sistema_origen'],
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'unidad_medida' => $data['unidad_medida'] ?? null,
                'activo' => true,
            ]);

            $this->auditoriaService->registrar(
                evento: 'prueba_funcional_creada',
                modulo: 'Catálogos',
                auditable: $pruebaFuncional,
                valoresNuevos: [
                    'sistema_origen' => $pruebaFuncional->sistema_origen->value,
                    'codigo' => $pruebaFuncional->codigo,
                    'nombre' => $pruebaFuncional->nombre,
                    'descripcion' => $pruebaFuncional->descripcion,
                    'unidad_medida' => $pruebaFuncional->unidad_medida,
                    'activo' => (bool) $pruebaFuncional->activo,
                ]
            );

            return $pruebaFuncional;
        });
    }
}
