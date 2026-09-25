<?php

namespace App\Actions\Catalogos;

use App\Models\TecnicaTerapeutica;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CambiarEstadoTecnicaTerapeuticaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(
        TecnicaTerapeutica $tecnicaTerapeutica,
        bool $activoObjetivo
    ): TecnicaTerapeutica {
        return DB::transaction(function () use ($tecnicaTerapeutica, $activoObjetivo): TecnicaTerapeutica {
            $tecnicaActual = TecnicaTerapeutica::query()
                ->whereKey($tecnicaTerapeutica->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = (bool) $tecnicaActual->activo;

            if ($estadoAnterior === $activoObjetivo) {
                return $tecnicaActual;
            }

            $tecnicaActual->activo = $activoObjetivo;
            $tecnicaActual->save();

            $this->auditoriaService->registrar(
                evento: $activoObjetivo
                    ? 'tecnica_terapeutica_reactivada'
                    : 'tecnica_terapeutica_inactivada',
                modulo: 'Catálogos',
                auditable: $tecnicaActual,
                valoresAnteriores: ['activo' => $estadoAnterior],
                valoresNuevos: ['activo' => $activoObjetivo]
            );

            return $tecnicaActual;
        });
    }
}
