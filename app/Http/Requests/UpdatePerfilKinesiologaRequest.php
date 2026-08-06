<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePerfilKinesiologaRequest extends FormRequest
{
    /**
     * La autorización específica se incorporará mediante Policies
     * en el Incremento 10.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas aplicables al alta inicial y a la actualización
     * del perfil profesional.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        $kinesiologaActual = $user->kinesiologa;

        $tieneMatriculas = $kinesiologaActual
            ?->matriculasProfesionales()
            ->exists() ?? false;

        $tituloActualId = $kinesiologaActual
            ?->titulo_profesional_id;

        $especialidadesActualesIds = $kinesiologaActual
            ? $kinesiologaActual
            ->especialidades()
            ->pluck('especialidades_kinesiologia.id')
            ->all()
            : [];

        $rules = [
            'dni' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'dni')
                    ->ignore($user->id),
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

            /*
             * Se permite seleccionar un título activo o conservar
             * el título histórico actualmente asociado.
             */
            'titulo_profesional_id' => [
                'required',
                'integer',

                Rule::exists('titulos_profesionales', 'id')
                    ->where(function ($query) use ($tituloActualId) {
                        $query->where(
                            function ($subquery) use ($tituloActualId) {
                                $subquery->where('activo', true);

                                if ($tituloActualId !== null) {
                                    $subquery->orWhere(
                                        'id',
                                        $tituloActualId
                                    );
                                }
                            }
                        );
                    }),
            ],

            'especialidades' => [
                'required',
                'array',
                'min:1',
            ],

            /*
             * Cada especialidad debe estar activa o formar parte
             * de las asociaciones históricas actuales.
             */
            'especialidades.*' => [
                'distinct',
                'integer',

                Rule::exists('especialidades_kinesiologia', 'id')
                    ->where(function ($query) use (
                        $especialidadesActualesIds
                    ) {
                        $query->where(
                            function ($subquery) use (
                                $especialidadesActualesIds
                            ) {
                                $subquery->where('activo', true);

                                if ($especialidadesActualesIds !== []) {
                                    $subquery->orWhereIn(
                                        'id',
                                        $especialidadesActualesIds
                                    );
                                }
                            }
                        );
                    }),
            ],
        ];

        /*
         * La primera matrícula es obligatoria únicamente cuando
         * la kinesióloga todavía no posee ninguna registrada.
         */
        if (! $tieneMatriculas) {
            $rules['jurisdiccion_id'] = [
                'required',
                'integer',

                Rule::exists('jurisdicciones', 'id')
                    ->where(function ($query) {
                        $query->where('activo', true);
                    }),
            ];

            $rules['colegio_emisor_id'] = [
                'required',
                'integer',

                Rule::exists('colegios_emisores', 'id')
                    ->where(function ($query) {
                        $query
                            ->where(
                                'jurisdiccion_id',
                                $this->input('jurisdiccion_id')
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
                )->where(function ($query) {
                    $query->where(
                        'jurisdiccion_id',
                        $this->input('jurisdiccion_id')
                    );
                }),
            ];
        }

        return $rules;
    }

    /**
     * Mensajes personalizados de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo_profesional_id.exists' =>
            'El título profesional seleccionado no existe, se encuentra inactivo o no puede utilizarse para esta operación.',

            'jurisdiccion_id.exists' =>
            'La jurisdicción seleccionada no existe o se encuentra inactiva.',

            'colegio_emisor_id.exists' =>
            'El colegio emisor seleccionado no pertenece a la jurisdicción elegida o se encuentra inactivo.',

            'especialidades.*.exists' =>
            'Una de las especialidades seleccionadas no existe, se encuentra inactiva o no puede utilizarse para esta operación.',

            'especialidades.*.distinct' =>
            'No puede seleccionar la misma especialidad más de una vez.',

            'nro_matricula_profesional.unique' =>
            'Ya existe una matrícula profesional con ese número en la jurisdicción seleccionada.',
        ];
    }
}
