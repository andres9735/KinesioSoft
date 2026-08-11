<?php

namespace App\Actions\Kinesiologas;

use App\Models\MatriculaProfesionalKinesiologia;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class ActualizarMatriculaProfesionalAction
{
    private const CAMPOS_AUDITABLES = [
        'nro_matricula_profesional',
        'jurisdiccion_id',
        'colegio_emisor_id',
    ];

    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * @param  array{
     *     nro_matricula_profesional: string,
     *     jurisdiccion_id: int,
     *     colegio_emisor_id: int
     * }  $data
     */
    public function ejecutar(
        MatriculaProfesionalKinesiologia $matricula,
        array $data
    ): MatriculaProfesionalKinesiologia {
        return DB::transaction(
            function () use ($matricula, $data): MatriculaProfesionalKinesiologia {
                $valoresAnteriores = [];
                $valoresNuevos = [];

                foreach (self::CAMPOS_AUDITABLES as $campo) {
                    $valorAnterior = $matricula->getRawOriginal($campo);
                    $valorNuevo = $data[$campo];

                    if (in_array(
                        $campo,
                        ['jurisdiccion_id', 'colegio_emisor_id'],
                        true
                    )) {
                        $valorAnterior = (int) $valorAnterior;
                        $valorNuevo = (int) $valorNuevo;
                    }

                    if ($valorAnterior !== $valorNuevo) {
                        $valoresAnteriores[$campo] = $valorAnterior;
                        $valoresNuevos[$campo] = $valorNuevo;
                    }
                }

                if ($valoresAnteriores === []) {
                    return $matricula;
                }

                $matricula->update([
                    'nro_matricula_profesional' =>
                    $data['nro_matricula_profesional'],
                    'jurisdiccion_id' => $data['jurisdiccion_id'],
                    'colegio_emisor_id' => $data['colegio_emisor_id'],
                ]);

                $this->auditoriaService->registrar(
                    evento: 'matricula_actualizada',
                    modulo: 'Matrículas profesionales',
                    auditable: $matricula,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos
                );

                return $matricula;
            }
        );
    }
}
