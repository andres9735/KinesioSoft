<?php

namespace App\Actions\Catalogos;

use App\Models\TituloProfesional;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ActualizarTituloProfesionalAction
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
        TituloProfesional $titulo,
        array $data
    ): TituloProfesional {
        return DB::transaction(
            function () use ($titulo, $data): TituloProfesional {
                $nombreAnterior = (string) $titulo->getRawOriginal(
                    'nombre'
                );
                $nombreNuevo = $data['nombre'];

                if ($nombreAnterior === $nombreNuevo) {
                    return $titulo;
                }

                $titulo->update([
                    'nombre' => $nombreNuevo,
                    'slug' => Str::slug($nombreNuevo),
                ]);

                $this->auditoriaService->registrar(
                    evento: 'titulo_profesional_actualizado',
                    modulo: 'Catálogos',
                    auditable: $titulo,
                    valoresAnteriores: [
                        'nombre' => $nombreAnterior,
                    ],
                    valoresNuevos: [
                        'nombre' => $nombreNuevo,
                    ]
                );

                return $titulo;
            }
        );
    }
}
