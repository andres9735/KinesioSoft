<?php

namespace App\Actions\Catalogos;

use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use App\Services\AuditoriaService;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CambiarEstadoColegioEmisorAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(
        ColegioEmisor $colegio,
        bool $activoObjetivo
    ): ColegioEmisor {
        return DB::transaction(
            function () use ($colegio, $activoObjetivo): ColegioEmisor {
                $colegioActual = ColegioEmisor::query()
                    ->whereKey($colegio->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $estadoAnterior = (bool) $colegioActual->activo;

                if ($estadoAnterior === $activoObjetivo) {
                    return $colegioActual;
                }

                if ($activoObjetivo && ! Jurisdiccion::query()
                    ->whereKey($colegioActual->jurisdiccion_id)
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->first(['id'])) {
                    throw new DomainException(
                        'No se puede reactivar el colegio emisor porque su jurisdicción está inactiva.'
                    );
                }

                $colegioActual->activo = $activoObjetivo;
                $colegioActual->save();

                $this->auditoriaService->registrar(
                    evento: $activoObjetivo
                        ? 'colegio_emisor_reactivado'
                        : 'colegio_emisor_inactivado',
                    modulo: 'Catálogos',
                    auditable: $colegioActual,
                    valoresAnteriores: [
                        'activo' => $estadoAnterior,
                    ],
                    valoresNuevos: [
                        'activo' => $activoObjetivo,
                    ]
                );

                return $colegioActual;
            }
        );
    }
}
