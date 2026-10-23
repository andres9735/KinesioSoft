<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConsultarProfesionalesDisponiblesPrimeraConsultaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['inicio_at' => ['bail', 'required', 'string', 'date_format:Y-m-d H:i:s']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->all()) as $campo) {
                if (! in_array($campo, ['inicio_at', '_token'], true)) {
                    $validator->errors()->add($campo, 'Este campo no está permitido.');
                }
            }
        }];
    }

    public function attributes(): array
    {
        return ['inicio_at' => 'fecha y hora de inicio'];
    }
}
