<?php

namespace App\Actions\Catalogos;

use App\Models\EspecialidadKinesiologia;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CambiarEstadoEspecialidadKinesiologiaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(
        EspecialidadKinesiologia $especialidad,
        bool $activoObjetivo
    ): EspecialidadKinesiologia {
        return DB::transaction(
            function () use ($especialidad, $activoObjetivo): EspecialidadKinesiologia {
                $especialidadActual = EspecialidadKinesiologia::query()
                    ->whereKey($especialidad->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $estadoAnterior = (bool) $especialidadActual->activo;

                if ($estadoAnterior === $activoObjetivo) {
                    return $especialidadActual;
                }

                $especialidadActual->activo = $activoObjetivo;
                $especialidadActual->save();

                $this->auditoriaService->registrar(
                    evento: $activoObjetivo
                        ? 'especialidad_kinesiologia_reactivada'
                        : 'especialidad_kinesiologia_inactivada',
                    modulo: 'Catálogos',
                    auditable: $especialidadActual,
                    valoresAnteriores: [
                        'activo' => $estadoAnterior,
                    ],
                    valoresNuevos: [
                        'activo' => $activoObjetivo,
                    ]
                );

                return $especialidadActual;
            }
        );
    }
}
