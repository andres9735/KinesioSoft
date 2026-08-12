<?php

namespace App\Actions\Catalogos;

use App\Models\TituloProfesional;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CrearTituloProfesionalAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * @param  array{
     *     nombre: string
     * }  $data
     */
    public function ejecutar(array $data): TituloProfesional
    {
        return DB::transaction(function () use ($data): TituloProfesional {
            $nombre = $data['nombre'];

            $titulo = TituloProfesional::create([
                'nombre' => $nombre,
                'slug' => Str::slug($nombre),
                'activo' => true,
            ]);

            $this->auditoriaService->registrar(
                evento: 'titulo_profesional_creado',
                modulo: 'Catálogos',
                auditable: $titulo,
                valoresNuevos: [
                    'nombre' => $titulo->nombre,
                    'activo' => (bool) $titulo->activo,
                ]
            );

            return $titulo;
        });
    }
}
