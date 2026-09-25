<?php

namespace App\Actions\Catalogos;

use App\Models\DiagnosticoClinico;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CambiarEstadoDiagnosticoClinicoAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(
        DiagnosticoClinico $diagnosticoClinico,
        bool $activoObjetivo
    ): DiagnosticoClinico {
        return DB::transaction(function () use ($diagnosticoClinico, $activoObjetivo): DiagnosticoClinico {
            $diagnosticoActual = DiagnosticoClinico::query()
                ->whereKey($diagnosticoClinico->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = (bool) $diagnosticoActual->activo;

            if ($estadoAnterior === $activoObjetivo) {
                return $diagnosticoActual;
            }

            $diagnosticoActual->activo = $activoObjetivo;
            $diagnosticoActual->save();

            $this->auditoriaService->registrar(
                evento: $activoObjetivo
                    ? 'diagnostico_clinico_reactivado'
                    : 'diagnostico_clinico_inactivado',
                modulo: 'Catálogos',
                auditable: $diagnosticoActual,
                valoresAnteriores: ['activo' => $estadoAnterior],
                valoresNuevos: ['activo' => $activoObjetivo]
            );

            return $diagnosticoActual;
        });
    }
}
