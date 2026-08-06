<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePerfilPacienteRequest extends FormRequest
{
    /**
     * La autorización continúa siendo responsabilidad
     * de los middlewares actuales.
     *
     * Será centralizada mediante Policies en el Incremento 10.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas para completar o actualizar el perfil del paciente.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->getAuthIdentifier();

        return [
            'dni' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'dni')->ignore($userId),
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
        ];
    }
}
