<?php

namespace App\Actions\Catalogos;

use App\Models\TecnicaTerapeutica;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class ActualizarTecnicaTerapeuticaAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(TecnicaTerapeutica $tecnicaTerapeutica, array $data): TecnicaTerapeutica
    {
        return DB::transaction(function () use ($tecnicaTerapeutica, $data): TecnicaTerapeutica {
            $valoresAnteriores = [];
            $valoresNuevos = [];

            foreach (['sistema_origen', 'codigo', 'nombre', 'descripcion'] as $campo) {
                $valorAnterior = $tecnicaTerapeutica->getRawOriginal($campo);
                $valorNuevo = $data[$campo] ?? null;

                if ($valorAnterior !== $valorNuevo) {
                    $valoresAnteriores[$campo] = $valorAnterior;
                    $valoresNuevos[$campo] = $valorNuevo;
                }
            }

            if ($valoresNuevos === []) {
                return $tecnicaTerapeutica;
            }

            $tecnicaTerapeutica->fill($valoresNuevos);
            $tecnicaTerapeutica->save();

            $this->auditoriaService->registrar(
                evento: 'tecnica_terapeutica_actualizada',
                modulo: 'Catálogos',
                auditable: $tecnicaTerapeutica,
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $valoresNuevos
            );

            return $tecnicaTerapeutica;
        });
    }
}
