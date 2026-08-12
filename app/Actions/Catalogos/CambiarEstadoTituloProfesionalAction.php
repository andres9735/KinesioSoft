<?php

namespace App\Actions\Catalogos;

use App\Models\TituloProfesional;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CambiarEstadoTituloProfesionalAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(
        TituloProfesional $titulo
    ): TituloProfesional {
        return DB::transaction(
            function () use ($titulo): TituloProfesional {
                $tituloActual = TituloProfesional::query()
                    ->whereKey($titulo->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $estadoAnterior = (bool) $tituloActual->activo;

                $tituloActual->activo = ! $estadoAnterior;
                $tituloActual->save();

                $this->auditoriaService->registrar(
                    evento: $tituloActual->activo
                        ? 'titulo_profesional_activado'
                        : 'titulo_profesional_inactivado',
                    modulo: 'Catálogos',
                    auditable: $tituloActual,
                    valoresAnteriores: [
                        'activo' => $estadoAnterior,
                    ],
                    valoresNuevos: [
                        'activo' => (bool) $tituloActual->activo,
                    ]
                );

                return $tituloActual;
            }
        );
    }
}
