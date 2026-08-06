<?php

namespace App\Http\Requests;

use App\Enums\RoleEnum;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMatriculaProfesionalRequest extends FormRequest
{
    /**
     * Conserva temporalmente la autorización actual.
     *
     * Esta comprobación será reemplazada por una Policy
     * en el Incremento 10.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $matricula = $this->route('matricula');

        return $user instanceof User
            && $user->hasRole(RoleEnum::KINESIOLOGO->value)
            && $user->kinesiologa !== null
            && $matricula instanceof MatriculaProfesionalKinesiologia
            && $matricula->kinesiologa_id === $user->kinesiologa->id;
    }

    /**
     * Reglas para actualizar una matrícula profesional.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        /** @var MatriculaProfesionalKinesiologia $matricula */
        $matricula = $this->route('matricula');

        $kinesiologa = $user->kinesiologa;

        return [
            'jurisdiccion_id' => [
                'required',
                'integer',

                /*
                 * Puede seleccionarse una jurisdicción activa o
                 * conservarse la jurisdicción histórica actual.
                 */
                Rule::exists('jurisdicciones', 'id')
                    ->where(function ($query) use ($matricula) {
                        $query->where(
                            function ($subquery) use ($matricula) {
                                $subquery->where('activo', true)
                                    ->orWhere(
                                        'id',
                                        $matricula->jurisdiccion_id
                                    );
                            }
                        );
                    }),

                /*
                 * Una kinesióloga solo puede tener una matrícula
                 * por jurisdicción. Se ignora la matrícula editada.
                 */
                Rule::unique(
                    'matriculas_profesionales_kinesiologia',
                    'jurisdiccion_id'
                )
                    ->where(function ($query) use ($kinesiologa) {
                        $query->where(
                            'kinesiologa_id',
                            $kinesiologa->id
                        );
                    })
                    ->ignore($matricula->id),
            ],

            'colegio_emisor_id' => [
                'required',
                'integer',

                /*
                 * El colegio debe pertenecer a la jurisdicción
                 * seleccionada y encontrarse activo, salvo que sea
                 * el colegio histórico de la matrícula.
                 */
                Rule::exists('colegios_emisores', 'id')
                    ->where(function ($query) use ($matricula) {
                        $query
                            ->where(
                                'jurisdiccion_id',
                                $this->input('jurisdiccion_id')
                            )
                            ->where(
                                function ($subquery) use ($matricula) {
                                    $subquery->where('activo', true)
                                        ->orWhere(
                                            'id',
                                            $matricula->colegio_emisor_id
                                        );
                                }
                            );
                    }),
            ],

            'nro_matricula_profesional' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9\s\-\.\/]+$/',

                /*
                 * El número debe ser único dentro de la jurisdicción.
                 * Se ignora el registro que se está actualizando.
                 */
                Rule::unique(
                    'matriculas_profesionales_kinesiologia',
                    'nro_matricula_profesional'
                )
                    ->where(function ($query) {
                        $query->where(
                            'jurisdiccion_id',
                            $this->input('jurisdiccion_id')
                        );
                    })
                    ->ignore($matricula->id),
            ],
        ];
    }

    /**
     * Mensajes personalizados de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
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
        ];
    }
}
