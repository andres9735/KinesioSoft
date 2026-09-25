<?php

namespace App\Actions\Catalogos;

use App\Models\PruebaFuncional;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class ActualizarPruebaFuncionalAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(PruebaFuncional $pruebaFuncional, array $data): PruebaFuncional
    {
        return DB::transaction(function () use ($pruebaFuncional, $data): PruebaFuncional {
            $valoresAnteriores = [];
            $valoresNuevos = [];

            foreach (['sistema_origen', 'codigo', 'nombre', 'descripcion', 'unidad_medida'] as $campo) {
                $valorAnterior = $pruebaFuncional->getRawOriginal($campo);
                $valorNuevo = $data[$campo] ?? null;

                if ($valorAnterior !== $valorNuevo) {
                    $valoresAnteriores[$campo] = $valorAnterior;
                    $valoresNuevos[$campo] = $valorNuevo;
                }
            }

            if ($valoresNuevos === []) {
                return $pruebaFuncional;
            }

            $pruebaFuncional->fill($valoresNuevos);
            $pruebaFuncional->save();

            $this->auditoriaService->registrar(
                evento: 'prueba_funcional_actualizada',
                modulo: 'Catálogos',
                auditable: $pruebaFuncional,
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $valoresNuevos
            );

            return $pruebaFuncional;
        });
    }
}
