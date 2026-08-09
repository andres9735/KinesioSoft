<?php

namespace App\Actions\Kinesiologas;

use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class GuardarPerfilKinesiologaAction
{
    /**
     * @param  array{
     *     dni: string,
     *     fecha_nacimiento: string,
     *     sexo: string,
     *     telefono: string,
     *     titulo_profesional_id: int,
     *     especialidades: array<int, int>,
     *     jurisdiccion_id?: int,
     *     colegio_emisor_id?: int,
     *     nro_matricula_profesional?: string
     * }  $data
     */
    public function ejecutar(User $user, array $data): Kinesiologa
    {
        return DB::transaction(function () use ($user, $data): Kinesiologa {
            $kinesiologaActual = $user->kinesiologa()->first();

            $tieneMatriculas = $kinesiologaActual
                ?->matriculasProfesionales()
                ->exists() ?? false;

            $user->update([
                'dni' => $data['dni'],
                'fecha_nacimiento' => $data['fecha_nacimiento'],
                'sexo' => $data['sexo'],
                'telefono' => $data['telefono'],
            ]);

            $kinesiologa = Kinesiologa::updateOrCreate(
                ['user_id' => $user->getKey()],
                [
                    'titulo_profesional_id' => $data['titulo_profesional_id'],
                    'consultorio_id' => $kinesiologaActual?->consultorio_id,
                ]
            );

            if (! $tieneMatriculas) {
                MatriculaProfesionalKinesiologia::create([
                    'kinesiologa_id' => $kinesiologa->getKey(),
                    'nro_matricula_profesional' => $data['nro_matricula_profesional'],
                    'jurisdiccion_id' => $data['jurisdiccion_id'],
                    'colegio_emisor_id' => $data['colegio_emisor_id'],
                    'activa' => true,
                ]);
            }

            $kinesiologa
                ->especialidades()
                ->sync($data['especialidades']);

            return $kinesiologa;
        });
    }
}
