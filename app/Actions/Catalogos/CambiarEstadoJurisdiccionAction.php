<?php

namespace App\Actions\Catalogos;

use App\Models\Jurisdiccion;
use App\Services\AuditoriaService;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CambiarEstadoJurisdiccionAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(
        Jurisdiccion $jurisdiccion,
        bool $activoObjetivo
    ): Jurisdiccion {
        return DB::transaction(
            function () use ($jurisdiccion, $activoObjetivo): Jurisdiccion {
                $jurisdiccionActual = Jurisdiccion::query()
                    ->whereKey($jurisdiccion->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $estadoAnterior = (bool) $jurisdiccionActual->activo;

                if ($estadoAnterior === $activoObjetivo) {
                    return $jurisdiccionActual;
                }

                if (! $activoObjetivo && $jurisdiccionActual->colegiosEmisores()
                    ->where('activo', true)
                    ->exists()) {
                    throw new DomainException(
                        'No se puede inactivar la jurisdicción porque tiene colegios emisores activos asociados.'
                    );
                }

                $jurisdiccionActual->activo = $activoObjetivo;
                $jurisdiccionActual->save();

                $this->auditoriaService->registrar(
                    evento: $activoObjetivo
                        ? 'jurisdiccion_reactivada'
                        : 'jurisdiccion_inactivada',
                    modulo: 'Catálogos',
                    auditable: $jurisdiccionActual,
                    valoresAnteriores: [
                        'activo' => $estadoAnterior,
                    ],
                    valoresNuevos: [
                        'activo' => $activoObjetivo,
                    ]
                );

                return $jurisdiccionActual;
            }
        );
    }
}
