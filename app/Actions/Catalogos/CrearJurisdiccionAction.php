<?php

namespace App\Actions\Catalogos;

use App\Models\Jurisdiccion;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CrearJurisdiccionAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * @param  array{
     *     nombre: string
     * }  $data
     */
    public function ejecutar(array $data): Jurisdiccion
    {
        return DB::transaction(function () use ($data): Jurisdiccion {
            $nombre = $data['nombre'];

            $jurisdiccion = Jurisdiccion::create([
                'nombre' => $nombre,
                'slug' => Str::slug($nombre),
                'activo' => true,
            ]);

            $this->auditoriaService->registrar(
                evento: 'jurisdiccion_creada',
                modulo: 'Catálogos',
                auditable: $jurisdiccion,
                valoresNuevos: [
                    'nombre' => $jurisdiccion->nombre,
                    'activo' => (bool) $jurisdiccion->activo,
                ]
            );

            return $jurisdiccion;
        });
    }
}
