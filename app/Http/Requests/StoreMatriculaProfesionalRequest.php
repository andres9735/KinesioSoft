<?php

namespace App\Http\Requests;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMatriculaProfesionalRequest extends FormRequest
{
    /**
     * Conserva la restricción actual de acceso al módulo.
     *
     * La autorización sobre recursos concretos será trasladada
     * a Policies en el Incremento 10.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->hasRole(RoleEnum::KINESIOLOGO->value)
            && $user->kinesiologa !== null;
    }

    /**
     * Reglas para registrar una nueva matrícula profesional.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        $kinesiologa = $user->kinesiologa;

        return [
            'jurisdiccion_id' => [
                'required',
                'integer',

                Rule::exists('jurisdicciones', 'id')
                    ->where(function ($query) {
                        $query->where('activo', true);
                    }),

                Rule::unique(
                    'matriculas_profesionales_kinesiologia',
                    'jurisdiccion_id'
                )->where(function ($query) use ($kinesiologa) {
                    $query->where(
                        'kinesiologa_id',
                        $kinesiologa->id
                    );
                }),
            ],

            'colegio_emisor_id' => [
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
            ],

            'nro_matricula_profesional' => [
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
