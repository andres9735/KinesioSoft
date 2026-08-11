<?php

namespace App\Actions\Kinesiologas;

use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class GuardarPerfilKinesiologaAction
{
    public function __construct(
        private readonly CrearMatriculaProfesionalAction $crearMatriculaProfesionalAction,
        private readonly AuditoriaService $auditoriaService
    ) {}

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
            $esCreacion = $kinesiologaActual === null;

            $tieneMatriculas = $kinesiologaActual
                ?->matriculasProfesionales()
                ->exists() ?? false;

            $especialidadesAnteriores = $kinesiologaActual
                ? $this->snapshotEspecialidades($kinesiologaActual)
                : [];

            $valoresAnteriores = [];
            $valoresNuevos = [];

            if (! $esCreacion) {
                foreach (
                    ['dni', 'fecha_nacimiento', 'sexo', 'telefono']
                    as $campo
                ) {
                    $valorAnterior = $user->getRawOriginal($campo);
                    $valorNuevo = $data[$campo];

                    if ($valorAnterior !== $valorNuevo) {
                        $valoresAnteriores[$campo] = $valorAnterior;
                        $valoresNuevos[$campo] = $valorNuevo;
                    }
                }

                $tituloAnterior = $kinesiologaActual->titulo_profesional_id;
                $tituloNuevo = (int) $data['titulo_profesional_id'];

                if ((int) $tituloAnterior !== $tituloNuevo) {
                    $valoresAnteriores['titulo_profesional_id'] =
                        (int) $tituloAnterior;

                    $valoresNuevos['titulo_profesional_id'] =
                        $tituloNuevo;
                }
            }

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
                $this->crearMatriculaProfesionalAction->ejecutar(
                    $kinesiologa,
                    [
                        'nro_matricula_profesional' =>
                        $data['nro_matricula_profesional'],
                        'jurisdiccion_id' => $data['jurisdiccion_id'],
                        'colegio_emisor_id' => $data['colegio_emisor_id'],
                    ]
                );
            }

            $kinesiologa
                ->especialidades()
                ->sync($data['especialidades']);

            $especialidadesNuevas =
                $this->snapshotEspecialidades($kinesiologa);

            if ($esCreacion) {
                $this->auditoriaService->registrar(
                    evento: 'perfil_kinesiologa_creado',
                    modulo: 'Perfiles',
                    auditable: $kinesiologa,
                    valoresNuevos: [
                        'dni' => $data['dni'],
                        'fecha_nacimiento' => $data['fecha_nacimiento'],
                        'sexo' => $data['sexo'],
                        'telefono' => $data['telefono'],
                        'titulo_profesional_id' =>
                        (int) $data['titulo_profesional_id'],
                        'especialidades' => $especialidadesNuevas,
                    ]
                );

                return $kinesiologa;
            }

            if ($valoresAnteriores !== []) {
                $this->auditoriaService->registrar(
                    evento: 'perfil_kinesiologa_actualizado',
                    modulo: 'Perfiles',
                    auditable: $kinesiologa,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos
                );
            }

            if ($especialidadesAnteriores !== $especialidadesNuevas) {
                $this->auditoriaService->registrar(
                    evento: 'especialidades_kinesiologa_actualizadas',
                    modulo: 'Perfiles',
                    auditable: $kinesiologa,
                    valoresAnteriores: [
                        'especialidades' => $especialidadesAnteriores,
                    ],
                    valoresNuevos: [
                        'especialidades' => $especialidadesNuevas,
                    ]
                );
            }

            return $kinesiologa;
        });
    }

    /**
     * @return array<int, array{id: int, nombre: string}>
     */
    private function snapshotEspecialidades(
        Kinesiologa $kinesiologa
    ): array {
        return $kinesiologa
            ->especialidades()
            ->get()
            ->sortBy('id')
            ->map(fn($especialidad): array => [
                'id' => (int) $especialidad->getKey(),
                'nombre' => $especialidad->nombre,
            ])
            ->values()
            ->all();
    }
}
