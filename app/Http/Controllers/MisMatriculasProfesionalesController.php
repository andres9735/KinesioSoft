<?php

namespace App\Http\Controllers;

use App\Actions\Kinesiologas\ActualizarMatriculaProfesionalAction;
use App\Actions\Kinesiologas\CrearMatriculaProfesionalAction;
use App\Http\Requests\StoreMatriculaProfesionalRequest;
use App\Http\Requests\UpdateMatriculaProfesionalRequest;
use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MisMatriculasProfesionalesController extends Controller
{
    public function __construct(
        private readonly CrearMatriculaProfesionalAction $crearMatriculaProfesionalAction,
        private readonly ActualizarMatriculaProfesionalAction $actualizarMatriculaProfesionalAction
    ) {}

    public function index(): View
    {
        Gate::authorize(
            'viewAny',
            MatriculaProfesionalKinesiologia::class
        );

        $kinesiologa = $this->obtenerKinesiologaAutenticada();

        $matriculas = $kinesiologa->matriculasProfesionales()
            ->with(['jurisdiccion', 'colegioEmisor'])
            ->orderByDesc('activa')
            ->orderBy('jurisdiccion_id')
            ->orderBy('nro_matricula_profesional')
            ->paginate(10);

        return view('perfil.matriculas.index', compact('matriculas'));
    }

    public function create(): View
    {
        Gate::authorize(
            'create',
            MatriculaProfesionalKinesiologia::class
        );

        $jurisdicciones = Jurisdiccion::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $colegiosEmisores = ColegioEmisor::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view(
            'perfil.matriculas.create',
            compact('jurisdicciones', 'colegiosEmisores')
        );
    }

    public function store(
        StoreMatriculaProfesionalRequest $request
    ): RedirectResponse {
        Gate::authorize(
            'create',
            MatriculaProfesionalKinesiologia::class
        );

        $kinesiologa = $this->obtenerKinesiologaAutenticada();
        $data = $request->validated();

        $this->crearMatriculaProfesionalAction->ejecutar(
            $kinesiologa,
            $data
        );

        return redirect()
            ->route('perfil.kinesiologa.matriculas.index')
            ->with(
                'success',
                'Matrícula profesional creada correctamente.'
            );
    }

    public function edit(
        MatriculaProfesionalKinesiologia $matricula
    ): View {
        Gate::authorize('update', $matricula);

        $jurisdicciones = Jurisdiccion::query()
            ->where(function ($query) use ($matricula) {
                $query->where('activo', true)
                    ->orWhere('id', $matricula->jurisdiccion_id);
            })
            ->orderBy('nombre')
            ->get();

        $colegiosEmisores = ColegioEmisor::query()
            ->where(function ($query) use ($matricula) {
                $query->where('activo', true)
                    ->orWhere('id', $matricula->colegio_emisor_id);
            })
            ->orderBy('nombre')
            ->get();

        return view(
            'perfil.matriculas.edit',
            compact('matricula', 'jurisdicciones', 'colegiosEmisores')
        );
    }

    public function update(
        UpdateMatriculaProfesionalRequest $request,
        MatriculaProfesionalKinesiologia $matricula
    ): RedirectResponse {
        Gate::authorize('update', $matricula);

        $data = $request->validated();

        $this->actualizarMatriculaProfesionalAction->ejecutar(
            $matricula,
            $data
        );

        return redirect()
            ->route('perfil.kinesiologa.matriculas.index')
            ->with(
                'success',
                'Matrícula profesional actualizada correctamente.'
            );
    }

    public function toggleActiva(
        MatriculaProfesionalKinesiologia $matricula
    ): RedirectResponse {
        Gate::authorize('toggleActiva', $matricula);

        $kinesiologa = $this->obtenerKinesiologaAutenticada();

        if ($matricula->activa) {
            $cantidadActivas = $kinesiologa->matriculasProfesionales()
                ->where('activa', true)
                ->count();

            if ($cantidadActivas <= 1) {
                return redirect()
                    ->route('perfil.kinesiologa.matriculas.index')
                    ->with(
                        'warning',
                        'No puede inactivar su última matrícula activa.'
                    );
            }
        }

        $matricula->update([
            'activa' => ! $matricula->activa,
        ]);

        $mensaje = $matricula->activa
            ? 'Matrícula profesional activada correctamente.'
            : 'Matrícula profesional inactivada correctamente.';

        return redirect()
            ->route('perfil.kinesiologa.matriculas.index')
            ->with('success', $mensaje);
    }

    private function obtenerKinesiologaAutenticada(): Kinesiologa
    {
        /** @var User $user */
        $user = Auth::user();

        $kinesiologa = $user?->kinesiologa;

        abort_unless($kinesiologa, 403);

        return $kinesiologa;
    }
}
