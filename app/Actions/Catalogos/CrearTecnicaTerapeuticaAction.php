<?php

namespace App\Actions\Catalogos;

use App\Models\TecnicaTerapeutica;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CrearTecnicaTerapeuticaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(array $data): TecnicaTerapeutica
    {
        return DB::transaction(function () use ($data): TecnicaTerapeutica {
            $tecnicaTerapeutica = TecnicaTerapeutica::create([
                'sistema_origen' => $data['sistema_origen'],
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'activo' => true,
            ]);

            $this->auditoriaService->registrar(
                evento: 'tecnica_terapeutica_creada',
                modulo: 'Catálogos',
                auditable: $tecnicaTerapeutica,
                valoresNuevos: [
                    'sistema_origen' => $tecnicaTerapeutica->sistema_origen->value,
                    'codigo' => $tecnicaTerapeutica->codigo,
                    'nombre' => $tecnicaTerapeutica->nombre,
                    'descripcion' => $tecnicaTerapeutica->descripcion,
                    'activo' => (bool) $tecnicaTerapeutica->activo,
                ]
            );

            return $tecnicaTerapeutica;
        });
    }
}
