<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePerfilKinesiologaRequest;
use App\Models\ColegioEmisor;
use App\Models\EspecialidadKinesiologia;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PerfilKinesiologaController extends Controller
{
    /**
     * Muestra el formulario de perfil profesional.
     */
    public function edit(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $kinesiologaActual = $user->kinesiologa;
        $tituloActualId = $kinesiologaActual?->titulo_profesional_id;

        $especialidadesActualesIds = $kinesiologaActual
            ? $kinesiologaActual
            ->especialidades()
            ->pluck('especialidades_kinesiologia.id')
            ->all()
            : [];

        /*
         * Se muestran los títulos activos y, si corresponde,
         * el título histórico actualmente asociado.
         */
        $titulosProfesionales = TituloProfesional::query()
            ->where(function ($query) use ($tituloActualId) {
                $query->where('activo', true);

                if ($tituloActualId !== null) {
                    $query->orWhere('id', $tituloActualId);
                }
            })
            ->orderBy('nombre')
            ->get();

        $jurisdicciones = Jurisdiccion::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $colegiosEmisores = ColegioEmisor::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        /*
         * Se muestran las especialidades activas y también las
         * especialidades históricas actualmente asociadas.
         */
        $especialidades = EspecialidadKinesiologia::query()
            ->where(function ($query) use ($especialidadesActualesIds) {
                $query->where('activo', true);

                if ($especialidadesActualesIds !== []) {
                    $query->orWhereIn(
                        'id',
                        $especialidadesActualesIds
                    );
                }
            })
            ->orderBy('nombre')
            ->get();

        return view('perfil.completar-kinesiologa', compact(
            'titulosProfesionales',
            'jurisdicciones',
            'colegiosEmisores',
            'especialidades'
        ));
    }

    /**
     * Guarda o actualiza el perfil profesional.
     */
    public function update(
        UpdatePerfilKinesiologaRequest $request
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $kinesiologaActual = $user->kinesiologa;

        $tieneMatriculas = $kinesiologaActual
            ?->matriculasProfesionales()
            ->exists() ?? false;

        $data = $request->validated();

        DB::transaction(function () use (
            $user,
            $kinesiologaActual,
            $tieneMatriculas,
            $data
        ) {
            $user->update([
                'dni' => $data['dni'],
                'fecha_nacimiento' => $data['fecha_nacimiento'],
                'sexo' => $data['sexo'],
                'telefono' => $data['telefono'],
            ]);

            $kinesiologa = Kinesiologa::updateOrCreate(
                [
                    'user_id' => $user->id,
                ],
                [
                    'titulo_profesional_id' =>
                    $data['titulo_profesional_id'],

                    'consultorio_id' =>
                    $kinesiologaActual?->consultorio_id,
                ]
            );

            if (! $tieneMatriculas) {
                MatriculaProfesionalKinesiologia::create([
                    'kinesiologa_id' => $kinesiologa->id,

                    'nro_matricula_profesional' =>
                    $data['nro_matricula_profesional'],

                    'jurisdiccion_id' =>
                    $data['jurisdiccion_id'],

                    'colegio_emisor_id' =>
                    $data['colegio_emisor_id'],

                    'activa' => true,
                ]);
            }

            $kinesiologa
                ->especialidades()
                ->sync($data['especialidades']);
        });

        $mensaje = $tieneMatriculas
            ? 'Perfil profesional actualizado correctamente. Para administrar sus matrículas, use el módulo "Mis matrículas profesionales".'
            : 'Perfil profesional completado correctamente.';

        return redirect()
            ->route('dashboard')
            ->with('success', $mensaje);
    }
}
