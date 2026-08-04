<?php

namespace App\Http\Controllers;

use App\Models\ColegioEmisor;
use App\Models\EspecialidadKinesiologia;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PerfilKinesiologaController extends Controller
{
    /**
     * Muestra el formulario de perfil profesional.
     */
    public function edit(): View
    {
        $titulosProfesionales = TituloProfesional::query()
            ->where('activo', true)
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

        $especialidades = EspecialidadKinesiologia::query()
            ->where('activo', true)
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
    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $kinesiologaActual = $user->kinesiologa;

        $tieneMatriculas = $kinesiologaActual
            ?->matriculasProfesionales()
            ->exists() ?? false;

        $rules = [
            'dni' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'dni')->ignore($user->id),
            ],

            'fecha_nacimiento' => [
                'required',
                'date',
                'before:today',
            ],

            'sexo' => [
                'required',
                Rule::in([
                    'masculino',
                    'femenino',
                    'otro',
                    'prefiero_no_decir',
                ]),
            ],

            'telefono' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]+$/',
            ],

            'titulo_profesional_id' => [
                'required',
                'integer',

                Rule::exists('titulos_profesionales', 'id')
                    ->where(function ($query) {
                        return $query->where('activo', true);
                    }),
            ],

            'especialidades' => [
                'required',
                'array',
                'min:1',
            ],

            'especialidades.*' => [
                'distinct',
                'integer',

                Rule::exists('especialidades_kinesiologia', 'id')
                    ->where(function ($query) {
                        return $query->where('activo', true);
                    }),
            ],
        ];

        /*
         * Los datos de la primera matrícula solamente son obligatorios
         * cuando la kinesióloga todavía no posee matrículas registradas.
         */
        if (! $tieneMatriculas) {
            $rules['jurisdiccion_id'] = [
                'required',
                'integer',

                Rule::exists('jurisdicciones', 'id')
                    ->where(function ($query) {
                        return $query->where('activo', true);
                    }),
            ];

            $rules['colegio_emisor_id'] = [
                'required',
                'integer',

                Rule::exists('colegios_emisores', 'id')
                    ->where(function ($query) use ($request) {
                        return $query
                            ->where(
                                'jurisdiccion_id',
                                $request->jurisdiccion_id
                            )
                            ->where('activo', true);
                    }),
            ];

            $rules['nro_matricula_profesional'] = [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9\s\-\.\/]+$/',

                Rule::unique(
                    'matriculas_profesionales_kinesiologia',
                    'nro_matricula_profesional'
                )->where(function ($query) use ($request) {
                    return $query->where(
                        'jurisdiccion_id',
                        $request->jurisdiccion_id
                    );
                }),
            ];
        }

        $messages = [
            'titulo_profesional_id.exists' =>
            'El título profesional seleccionado no existe o se encuentra inactivo.',

            'jurisdiccion_id.exists' =>
            'La jurisdicción seleccionada no existe o se encuentra inactiva.',

            'colegio_emisor_id.exists' =>
            'El colegio emisor seleccionado no pertenece a la jurisdicción elegida o se encuentra inactivo.',

            'especialidades.*.exists' =>
            'Una de las especialidades seleccionadas no existe o se encuentra inactiva.',

            'especialidades.*.distinct' =>
            'No puede seleccionar la misma especialidad más de una vez.',

            'nro_matricula_profesional.unique' =>
            'Ya existe una matrícula profesional con ese número en la jurisdicción seleccionada.',
        ];

        $data = $request->validate($rules, $messages);

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
