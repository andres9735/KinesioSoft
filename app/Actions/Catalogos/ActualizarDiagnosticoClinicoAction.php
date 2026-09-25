<?php

namespace App\Actions\Catalogos;

use App\Models\DiagnosticoClinico;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class ActualizarDiagnosticoClinicoAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(DiagnosticoClinico $diagnosticoClinico, array $data): DiagnosticoClinico
    {
        return DB::transaction(function () use ($diagnosticoClinico, $data): DiagnosticoClinico {
            $valoresAnteriores = [];
            $valoresNuevos = [];

            foreach (['sistema_origen', 'codigo', 'nombre', 'descripcion'] as $campo) {
                $valorAnterior = $diagnosticoClinico->getRawOriginal($campo);
                $valorNuevo = $data[$campo] ?? null;

                if ($valorAnterior !== $valorNuevo) {
                    $valoresAnteriores[$campo] = $valorAnterior;
                    $valoresNuevos[$campo] = $valorNuevo;
                }
            }

            if ($valoresNuevos === []) {
                return $diagnosticoClinico;
            }

            $diagnosticoClinico->fill($valoresNuevos);
            $diagnosticoClinico->save();

            $this->auditoriaService->registrar(
                evento: 'diagnostico_clinico_actualizado',
                modulo: 'Catálogos',
                auditable: $diagnosticoClinico,
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $valoresNuevos
            );

            return $diagnosticoClinico;
        });
    }
}
