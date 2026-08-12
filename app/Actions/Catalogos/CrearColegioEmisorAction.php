<?php

namespace App\Actions\Catalogos;

use App\Models\ColegioEmisor;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CrearColegioEmisorAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * @param  array{
     *     nombre: string,
     *     sigla: string,
     *     jurisdiccion_id: int|string
     * }  $data
     */
    public function ejecutar(array $data): ColegioEmisor
    {
        return DB::transaction(function () use ($data): ColegioEmisor {
            $nombre = preg_replace(
                '/\s+/',
                ' ',
                trim($data['nombre'])
            );

            $sigla = mb_strtoupper(
                preg_replace(
                    '/\s+/',
                    ' ',
                    trim($data['sigla'])
                )
            );

            $colegio = ColegioEmisor::create([
                'nombre' => $nombre,
                'sigla' => $sigla,
                'jurisdiccion_id' => $data['jurisdiccion_id'],
                'activo' => true,
            ]);

            $this->auditoriaService->registrar(
                evento: 'colegio_emisor_creado',
                modulo: 'Catálogos',
                auditable: $colegio,
                valoresNuevos: [
                    'nombre' => $colegio->nombre,
                    'sigla' => $colegio->sigla,
                    'jurisdiccion_id' => $colegio->jurisdiccion_id,
                    'activo' => (bool) $colegio->activo,
                ]
            );

            return $colegio;
        });
    }
}
