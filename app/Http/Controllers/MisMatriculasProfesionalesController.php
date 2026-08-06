<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Http\Requests\StoreMatriculaProfesionalRequest;
use App\Http\Requests\UpdateMatriculaProfesionalRequest;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MisMatriculasProfesionalesController extends Controller
{
    public function index(): View
    {
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
        $this->obtenerKinesiologaAutenticada();

        $jurisdicciones = Jurisdiccion::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $colegiosEmisores = ColegioEmisor::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('perfil.matriculas.create', compact('jurisdicciones', 'colegiosEmisores'));
    }

    public function store(
        StoreMatriculaProfesionalRequest $request
    ): RedirectResponse {
        $kinesiologa = $this->obtenerKinesiologaAutenticada();

        $data = $request->validated();

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

        return redirect()
            ->route('perfil.kinesiologa.matriculas.index')
            ->with(
                'success',
                'Matrícula profesional creada correctamente.'
            );
    }

    public function edit(MatriculaProfesionalKinesiologia $matricula): View
    {
        $kinesiologa = $this->obtenerKinesiologaAutenticada();

        abort_unless($matricula->kinesiologa_id === $kinesiologa->id, 403);

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

        return view('perfil.matriculas.edit', compact('matricula', 'jurisdicciones', 'colegiosEmisores'));
    }

    public function update(
        UpdateMatriculaProfesionalRequest $request,
        MatriculaProfesionalKinesiologia $matricula
    ): RedirectResponse {
        $kinesiologa = $this->obtenerKinesiologaAutenticada();

        /*
        * Se conserva temporalmente esta comprobación como defensa
        * adicional hasta incorporar la Policy del Incremento 10.
        */
        abort_unless(
            $matricula->kinesiologa_id === $kinesiologa->id,
            403
        );

        $data = $request->validated();

        $matricula->update([
            'nro_matricula_profesional' =>
            $data['nro_matricula_profesional'],

            'jurisdiccion_id' =>
            $data['jurisdiccion_id'],

            'colegio_emisor_id' =>
            $data['colegio_emisor_id'],
        ]);

        return redirect()
            ->route('perfil.kinesiologa.matriculas.index')
            ->with(
                'success',
                'Matrícula profesional actualizada correctamente.'
            );
    }

    public function toggleActiva(MatriculaProfesionalKinesiologia $matricula): RedirectResponse
    {
        $kinesiologa = $this->obtenerKinesiologaAutenticada();

        abort_unless($matricula->kinesiologa_id === $kinesiologa->id, 403);

        if ($matricula->activa) {
            $cantidadActivas = $kinesiologa->matriculasProfesionales()
                ->where('activa', true)
                ->count();

            if ($cantidadActivas <= 1) {
                return redirect()
                    ->route('perfil.kinesiologa.matriculas.index')
                    ->with('warning', 'No puede inactivar su última matrícula activa.');
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

        abort_unless($user && $user->hasRole(RoleEnum::KINESIOLOGO->value), 403);

        $kinesiologa = $user->kinesiologa;

        abort_unless($kinesiologa, 403);

        return $kinesiologa;
    }
}
