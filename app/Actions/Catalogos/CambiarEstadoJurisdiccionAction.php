<?php

namespace App\Actions\Catalogos;

use App\Models\Jurisdiccion;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CambiarEstadoJurisdiccionAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(
        Jurisdiccion $jurisdiccion
    ): Jurisdiccion {
        return DB::transaction(
            function () use ($jurisdiccion): Jurisdiccion {
                $jurisdiccionActual = Jurisdiccion::query()
                    ->whereKey($jurisdiccion->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $estadoAnterior = (bool) $jurisdiccionActual->activo;

                $jurisdiccionActual->activo = ! $estadoAnterior;
                $jurisdiccionActual->save();

                $this->auditoriaService->registrar(
                    evento: $jurisdiccionActual->activo
                        ? 'jurisdiccion_activada'
                        : 'jurisdiccion_inactivada',
                    modulo: 'Catálogos',
                    auditable: $jurisdiccionActual,
                    valoresAnteriores: [
                        'activo' => $estadoAnterior,
                    ],
                    valoresNuevos: [
                        'activo' => (bool) $jurisdiccionActual->activo,
                    ]
                );

                return $jurisdiccionActual;
            }
        );
    }
}
