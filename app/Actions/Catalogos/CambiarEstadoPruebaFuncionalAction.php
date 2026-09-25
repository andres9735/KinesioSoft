<?php

namespace App\Actions\Catalogos;

use App\Models\PruebaFuncional;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CambiarEstadoPruebaFuncionalAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(
        PruebaFuncional $pruebaFuncional,
        bool $activoObjetivo
    ): PruebaFuncional {
        return DB::transaction(function () use ($pruebaFuncional, $activoObjetivo): PruebaFuncional {
            $pruebaActual = PruebaFuncional::query()
                ->whereKey($pruebaFuncional->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = (bool) $pruebaActual->activo;

            if ($estadoAnterior === $activoObjetivo) {
                return $pruebaActual;
            }

            $pruebaActual->activo = $activoObjetivo;
            $pruebaActual->save();

            $this->auditoriaService->registrar(
                evento: $activoObjetivo
                    ? 'prueba_funcional_reactivada'
                    : 'prueba_funcional_inactivada',
                modulo: 'Catálogos',
                auditable: $pruebaActual,
                valoresAnteriores: ['activo' => $estadoAnterior],
                valoresNuevos: ['activo' => $activoObjetivo]
            );

            return $pruebaActual;
        });
    }
}
