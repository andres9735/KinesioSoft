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
        TituloProfesional $titulo,
        bool $activoObjetivo
    ): TituloProfesional {
        return DB::transaction(
            function () use ($titulo, $activoObjetivo): TituloProfesional {
                $tituloActual = TituloProfesional::query()
                    ->whereKey($titulo->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $estadoAnterior = (bool) $tituloActual->activo;

                if ($estadoAnterior === $activoObjetivo) {
                    return $tituloActual;
                }

                $tituloActual->activo = $activoObjetivo;
                $tituloActual->save();

                $this->auditoriaService->registrar(
                    evento: $activoObjetivo
                        ? 'titulo_profesional_reactivado'
                        : 'titulo_profesional_inactivado',
                    modulo: 'Catálogos',
                    auditable: $tituloActual,
                    valoresAnteriores: [
                        'activo' => $estadoAnterior,
                    ],
                    valoresNuevos: [
                        'activo' => $activoObjetivo,
                    ]
                );

                return $tituloActual;
            }
        );
    }
}
