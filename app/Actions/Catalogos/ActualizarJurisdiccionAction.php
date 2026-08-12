<?php

namespace App\Actions\Catalogos;

use App\Models\Jurisdiccion;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ActualizarJurisdiccionAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * @param  array{
     *     nombre: string
     * }  $data
     */
    public function ejecutar(
        Jurisdiccion $jurisdiccion,
        array $data
    ): Jurisdiccion {
        return DB::transaction(
            function () use ($jurisdiccion, $data): Jurisdiccion {
                $nombreAnterior = (string) $jurisdiccion->getRawOriginal(
                    'nombre'
                );
                $nombreNuevo = $data['nombre'];

                if ($nombreAnterior === $nombreNuevo) {
                    return $jurisdiccion;
                }

                $jurisdiccion->update([
                    'nombre' => $nombreNuevo,
                    'slug' => Str::slug($nombreNuevo),
                ]);

                $this->auditoriaService->registrar(
                    evento: 'jurisdiccion_actualizada',
                    modulo: 'Catálogos',
                    auditable: $jurisdiccion,
                    valoresAnteriores: [
                        'nombre' => $nombreAnterior,
                    ],
                    valoresNuevos: [
                        'nombre' => $nombreNuevo,
                    ]
                );

                return $jurisdiccion;
            }
        );
    }
}
