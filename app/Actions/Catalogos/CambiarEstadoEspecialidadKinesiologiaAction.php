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
        EspecialidadKinesiologia $especialidad
    ): EspecialidadKinesiologia {
        return DB::transaction(
            function () use ($especialidad): EspecialidadKinesiologia {
                $especialidadActual = EspecialidadKinesiologia::query()
                    ->whereKey($especialidad->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $estadoAnterior = (bool) $especialidadActual->activo;

                $especialidadActual->activo = ! $estadoAnterior;
                $especialidadActual->save();

                $this->auditoriaService->registrar(
                    evento: $especialidadActual->activo
                        ? 'especialidad_kinesiologia_activada'
                        : 'especialidad_kinesiologia_inactivada',
                    modulo: 'Catálogos',
                    auditable: $especialidadActual,
                    valoresAnteriores: [
                        'activo' => $estadoAnterior,
                    ],
                    valoresNuevos: [
                        'activo' => (bool) $especialidadActual->activo,
                    ]
                );

                return $especialidadActual;
            }
        );
    }
}
