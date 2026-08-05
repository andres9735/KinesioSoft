<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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

    public function store(Request $request): RedirectResponse
    {
        $kinesiologa = $this->obtenerKinesiologaAutenticada();

        $data = $this->validateData($request, $kinesiologa);

        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $kinesiologa->id,
            'nro_matricula_profesional' => $data['nro_matricula_profesional'],
            'jurisdiccion_id' => $data['jurisdiccion_id'],
            'colegio_emisor_id' => $data['colegio_emisor_id'],
            'activa' => true,
        ]);

        return redirect()
            ->route('perfil.kinesiologa.matriculas.index')
            ->with('success', 'Matrícula profesional creada correctamente.');
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

    public function update(Request $request, MatriculaProfesionalKinesiologia $matricula): RedirectResponse
    {
        $kinesiologa = $this->obtenerKinesiologaAutenticada();

        abort_unless($matricula->kinesiologa_id === $kinesiologa->id, 403);

        $data = $this->validateData($request, $kinesiologa, $matricula);

        $matricula->update([
            'nro_matricula_profesional' => $data['nro_matricula_profesional'],
            'jurisdiccion_id' => $data['jurisdiccion_id'],
            'colegio_emisor_id' => $data['colegio_emisor_id'],
        ]);

        return redirect()
            ->route('perfil.kinesiologa.matriculas.index')
            ->with('success', 'Matrícula profesional actualizada correctamente.');
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

    private function validateData(
        Request $request,
        Kinesiologa $kinesiologa,
        ?MatriculaProfesionalKinesiologia $matricula = null
    ): array {
        $matriculaId = $matricula?->id;

        return $request->validate([
            'jurisdiccion_id' => [
                'required',
                'integer',

                Rule::exists('jurisdicciones', 'id')
                    ->where(function ($query) use ($matricula) {
                        $query->where(function ($subquery) use ($matricula) {
                            $subquery->where('activo', true);

                            if ($matricula) {
                                $subquery->orWhere(
                                    'id',
                                    $matricula->jurisdiccion_id
                                );
                            }
                        });
                    }),

                Rule::unique(
                    'matriculas_profesionales_kinesiologia',
                    'jurisdiccion_id'
                )
                    ->where(function ($query) use ($kinesiologa) {
                        return $query->where(
                            'kinesiologa_id',
                            $kinesiologa->id
                        );
                    })
                    ->ignore($matriculaId),
            ],

            'colegio_emisor_id' => [
                'required',
                'integer',

                Rule::exists('colegios_emisores', 'id')
                    ->where(function ($query) use ($request, $matricula) {
                        $query
                            ->where(
                                'jurisdiccion_id',
                                $request->jurisdiccion_id
                            )
                            ->where(function ($subquery) use ($matricula) {
                                $subquery->where('activo', true);

                                if ($matricula) {
                                    $subquery->orWhere(
                                        'id',
                                        $matricula->colegio_emisor_id
                                    );
                                }
                            });
                    }),
            ],

            'nro_matricula_profesional' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9\s\-\.\/]+$/',

                Rule::unique(
                    'matriculas_profesionales_kinesiologia',
                    'nro_matricula_profesional'
                )
                    ->where(function ($query) use ($request) {
                        return $query->where(
                            'jurisdiccion_id',
                            $request->jurisdiccion_id
                        );
                    })
                    ->ignore($matriculaId),
            ],
        ], [
            'jurisdiccion_id.required' =>
            'Debe seleccionar una jurisdicción.',

            'jurisdiccion_id.exists' =>
            'La jurisdicción seleccionada no existe o se encuentra inactiva.',

            'jurisdiccion_id.unique' =>
            'Ya tiene una matrícula registrada para la jurisdicción seleccionada.',

            'colegio_emisor_id.required' =>
            'Debe seleccionar un colegio emisor.',

            'colegio_emisor_id.exists' =>
            'El colegio emisor seleccionado no pertenece a la jurisdicción elegida o se encuentra inactivo.',

            'nro_matricula_profesional.required' =>
            'Debe ingresar el número de matrícula profesional.',

            'nro_matricula_profesional.max' =>
            'El número de matrícula no puede superar los 50 caracteres.',

            'nro_matricula_profesional.regex' =>
            'El número de matrícula contiene caracteres no permitidos.',

            'nro_matricula_profesional.unique' =>
            'Ya existe una matrícula profesional con ese número en la jurisdicción seleccionada.',
        ]);
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
