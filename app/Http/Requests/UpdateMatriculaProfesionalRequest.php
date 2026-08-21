<?php

namespace App\Http\Requests;

use App\Enums\RoleEnum;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMatriculaProfesionalRequest extends FormRequest
{
    /**
     * Autoriza únicamente a la kinesióloga propietaria
     * de la matrícula profesional.
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
     * La edición ordinaria permite modificar únicamente
     * el número de matrícula profesional.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $matricula = $this->route('matricula');

        if (! $matricula instanceof MatriculaProfesionalKinesiologia) {
            return [];
        }

        return [
            'nro_matricula_profesional' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9\s\-\.\/]+$/',

                /*
                 * El número debe ser único dentro de la jurisdicción
                 * propia de la matrícula.
                 *
                 * La jurisdicción utilizada para esta validación se
                 * obtiene del registro persistido y nunca del request.
                 */
                Rule::unique(
                    'matriculas_profesionales_kinesiologia',
                    'nro_matricula_profesional'
                )
                    ->where(function ($query) use ($matricula) {
                        $query->where(
                            'jurisdiccion_id',
                            $matricula->jurisdiccion_id
                        );
                    })
                    ->ignore($matricula->id),
            ],
        ];
    }

    /**
     * Rechaza cualquier intento de modificar los datos
     * inmutables de identificación y procedencia de la matrícula.
     *
     * Se tolera que la interfaz envíe temporalmente sus valores
     * actuales, pero cualquier valor diferente al persistido
     * invalida la operación completa.
     */
    public function withValidator(Validator $validator): void
    {
        $matricula = $this->route('matricula');

        if (! $matricula instanceof MatriculaProfesionalKinesiologia) {
            return;
        }

        $validator->after(function (Validator $validator) use ($matricula): void {
            if (
                $this->has('jurisdiccion_id')
                && (string) $this->input('jurisdiccion_id')
                !== (string) $matricula->jurisdiccion_id
            ) {
                $validator->errors()->add(
                    'jurisdiccion_id',
                    'La jurisdicción de una matrícula profesional no puede modificarse.'
                );
            }

            if (
                $this->has('colegio_emisor_id')
                && (string) $this->input('colegio_emisor_id')
                !== (string) $matricula->colegio_emisor_id
            ) {
                $validator->errors()->add(
                    'colegio_emisor_id',
                    'El colegio emisor de una matrícula profesional no puede modificarse.'
                );
            }
        });
    }

    /**
     * Mensajes personalizados de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nro_matricula_profesional.required' =>
            'Debe ingresar el número de matrícula profesional.',

            'nro_matricula_profesional.max' =>
            'El número de matrícula no puede superar los 50 caracteres.',

            'nro_matricula_profesional.regex' =>
            'El número de matrícula contiene caracteres no permitidos.',

            'nro_matricula_profesional.unique' =>
            'Ya existe una matrícula profesional con ese número en la jurisdicción correspondiente.',
        ];
    }
}
