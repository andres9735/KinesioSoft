<?php

namespace App\Actions\Kinesiologas;

use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class CrearMatriculaProfesionalAction
{
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
        Kinesiologa $kinesiologa,
        array $data
    ): MatriculaProfesionalKinesiologia {
        return DB::transaction(
            function () use ($kinesiologa, $data): MatriculaProfesionalKinesiologia {
                $matricula = MatriculaProfesionalKinesiologia::create([
                    'kinesiologa_id' => $kinesiologa->getKey(),
                    'nro_matricula_profesional' =>
                        $data['nro_matricula_profesional'],
                    'jurisdiccion_id' => $data['jurisdiccion_id'],
                    'colegio_emisor_id' => $data['colegio_emisor_id'],
                    'activa' => true,
                ]);

                $this->auditoriaService->registrar(
                    evento: 'matricula_creada',
                    modulo: 'Matrículas profesionales',
                    auditable: $matricula,
                    valoresNuevos: [
                        'nro_matricula_profesional' =>
                            $matricula->nro_matricula_profesional,
                        'jurisdiccion_id' =>
                            (int) $matricula->jurisdiccion_id,
                        'colegio_emisor_id' =>
                            (int) $matricula->colegio_emisor_id,
                        'activa' => (bool) $matricula->activa,
                    ]
                );

                return $matricula;
            }
        );
    }
}
