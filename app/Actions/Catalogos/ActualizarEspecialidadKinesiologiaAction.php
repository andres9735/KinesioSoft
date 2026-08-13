<?php

namespace App\Actions\Catalogos;

use App\Models\EspecialidadKinesiologia;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ActualizarEspecialidadKinesiologiaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * @param  array{nombre: string}  $data
     */
    public function ejecutar(
        EspecialidadKinesiologia $especialidad,
        array $data
    ): EspecialidadKinesiologia {
        return DB::transaction(
            function () use (
                $especialidad,
                $data
            ): EspecialidadKinesiologia {
                $nombreAnterior = (string) $especialidad->getRawOriginal(
                    'nombre'
                );

                $nombreNuevo = preg_replace(
                    '/\s+/',
                    ' ',
                    trim($data['nombre'])
                );

                if ($nombreAnterior === $nombreNuevo) {
                    return $especialidad;
                }

                $especialidad->update([
                    'nombre' => $nombreNuevo,
                    'slug' => Str::slug($nombreNuevo),
                ]);

                $this->auditoriaService->registrar(
                    evento: 'especialidad_kinesiologia_actualizada',
                    modulo: 'Catálogos',
                    auditable: $especialidad,
                    valoresAnteriores: [
                        'nombre' => $nombreAnterior,
                    ],
                    valoresNuevos: [
                        'nombre' => $nombreNuevo,
                    ]
                );

                return $especialidad;
            }
        );
    }
}
