<?php

namespace App\Http\Requests\PerfilPaciente;

use Illuminate\Validation\Validator;

class ValidarPaso2PerfilPacienteRequest extends PerfilPacienteRequestBase
{
    public function rules(): array
    {
        return $this->reglasPaso2();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarItemsClinicos($validator);
        });
    }
}
