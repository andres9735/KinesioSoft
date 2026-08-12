<?php

namespace App\Actions\Catalogos;

use App\Models\ColegioEmisor;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CambiarEstadoColegioEmisorAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(
        ColegioEmisor $colegio
    ): ColegioEmisor {
        return DB::transaction(
            function () use ($colegio): ColegioEmisor {
                $colegioActual = ColegioEmisor::query()
                    ->whereKey($colegio->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $estadoAnterior = (bool) $colegioActual->activo;

                $colegioActual->activo = ! $estadoAnterior;
                $colegioActual->save();

                $this->auditoriaService->registrar(
                    evento: $colegioActual->activo
                        ? 'colegio_emisor_activado'
                        : 'colegio_emisor_inactivado',
                    modulo: 'Catálogos',
                    auditable: $colegioActual,
                    valoresAnteriores: [
                        'activo' => $estadoAnterior,
                    ],
                    valoresNuevos: [
                        'activo' => (bool) $colegioActual->activo,
                    ]
                );

                return $colegioActual;
            }
        );
    }
}
