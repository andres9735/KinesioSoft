<?php

namespace App\Actions\Catalogos;

use App\Models\DiagnosticoClinico;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CrearDiagnosticoClinicoAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(array $data): DiagnosticoClinico
    {
        return DB::transaction(function () use ($data): DiagnosticoClinico {
            $diagnosticoClinico = DiagnosticoClinico::create([
                'sistema_origen' => $data['sistema_origen'],
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'activo' => true,
            ]);

            $this->auditoriaService->registrar(
                evento: 'diagnostico_clinico_creado',
                modulo: 'Catálogos',
                auditable: $diagnosticoClinico,
                valoresNuevos: [
                    'sistema_origen' => $diagnosticoClinico->sistema_origen->value,
                    'codigo' => $diagnosticoClinico->codigo,
                    'nombre' => $diagnosticoClinico->nombre,
                    'descripcion' => $diagnosticoClinico->descripcion,
                    'activo' => (bool) $diagnosticoClinico->activo,
                ]
            );

            return $diagnosticoClinico;
        });
    }
}
