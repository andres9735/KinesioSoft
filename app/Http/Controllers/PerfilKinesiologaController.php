<?php

namespace App\Http\Controllers;

use App\Actions\Kinesiologas\GuardarPerfilKinesiologaAction;
use App\Http\Requests\UpdatePerfilKinesiologaRequest;
use App\Models\ColegioEmisor;
use App\Models\EspecialidadKinesiologia;
use App\Models\Jurisdiccion;
use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PerfilKinesiologaController extends Controller
{
    public function __construct(
        private readonly GuardarPerfilKinesiologaAction $guardarPerfilKinesiologaAction
    ) {}

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

        $tieneMatriculas = $user->kinesiologa
            ?->matriculasProfesionales()
            ->exists() ?? false;

        $data = $request->validated();

        $this->guardarPerfilKinesiologaAction->ejecutar(
            $user,
            $data
        );

        $mensaje = $tieneMatriculas
            ? 'Perfil profesional actualizado correctamente. Para administrar sus matrículas, use el módulo "Mis matrículas profesionales".'
            : 'Perfil profesional completado correctamente.';

        return redirect()
            ->route('dashboard')
            ->with('success', $mensaje);
    }
}
