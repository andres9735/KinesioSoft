<?php

namespace App\Actions\Catalogos;

use App\Models\EspecialidadKinesiologia;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CrearEspecialidadKinesiologiaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * @param  array{nombre: string}  $data
     */
    public function ejecutar(array $data): EspecialidadKinesiologia
    {
        return DB::transaction(
            function () use ($data): EspecialidadKinesiologia {
                $nombre = preg_replace(
                    '/\s+/',
                    ' ',
                    trim($data['nombre'])
                );

                $especialidad = EspecialidadKinesiologia::create([
                    'nombre' => $nombre,
                    'slug' => Str::slug($nombre),
                    'activo' => true,
                ]);

                $this->auditoriaService->registrar(
                    evento: 'especialidad_kinesiologia_creada',
                    modulo: 'Catálogos',
                    auditable: $especialidad,
                    valoresNuevos: [
                        'nombre' => $especialidad->nombre,
                        'activo' => (bool) $especialidad->activo,
                    ]
                );

                return $especialidad;
            }
        );
    }
}
