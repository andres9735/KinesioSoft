<?php

namespace App\Actions\Catalogos;

use App\Models\ColegioEmisor;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class ActualizarColegioEmisorAction
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
    public function ejecutar(
        ColegioEmisor $colegio,
        array $data
    ): ColegioEmisor {
        return DB::transaction(
            function () use ($colegio, $data): ColegioEmisor {
                $nombreAnterior = (string) $colegio->getRawOriginal(
                    'nombre'
                );
                $siglaAnterior = (string) $colegio->getRawOriginal(
                    'sigla'
                );
                $jurisdiccionIdAnterior = (int) $colegio->getRawOriginal(
                    'jurisdiccion_id'
                );

                $nombreNuevo = preg_replace(
                    '/\s+/',
                    ' ',
                    trim($data['nombre'])
                );

                $siglaNueva = mb_strtoupper(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        trim($data['sigla'])
                    )
                );

                $jurisdiccionIdNuevo = (int) $data['jurisdiccion_id'];

                $valoresAnteriores = [];
                $valoresNuevos = [];

                if ($nombreAnterior !== $nombreNuevo) {
                    $valoresAnteriores['nombre'] = $nombreAnterior;
                    $valoresNuevos['nombre'] = $nombreNuevo;
                }

                if ($siglaAnterior !== $siglaNueva) {
                    $valoresAnteriores['sigla'] = $siglaAnterior;
                    $valoresNuevos['sigla'] = $siglaNueva;
                }

                if ($jurisdiccionIdAnterior !== $jurisdiccionIdNuevo) {
                    $valoresAnteriores['jurisdiccion_id'] =
                        $jurisdiccionIdAnterior;
                    $valoresNuevos['jurisdiccion_id'] =
                        $jurisdiccionIdNuevo;
                }

                if ($valoresNuevos === []) {
                    return $colegio;
                }

                $colegio->update([
                    'nombre' => $nombreNuevo,
                    'sigla' => $siglaNueva,
                    'jurisdiccion_id' => $jurisdiccionIdNuevo,
                ]);

                $this->auditoriaService->registrar(
                    evento: 'colegio_emisor_actualizado',
                    modulo: 'Catálogos',
                    auditable: $colegio,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos
                );

                return $colegio;
            }
        );
    }
}
