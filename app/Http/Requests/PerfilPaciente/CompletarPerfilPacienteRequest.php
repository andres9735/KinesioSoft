<?php

namespace App\Http\Requests\PerfilPaciente;

use Illuminate\Validation\Validator;

class CompletarPerfilPacienteRequest extends PerfilPacienteRequestBase
{
    public function rules(): array
    {
        return array_merge(
            $this->reglasPaso1(),
            $this->reglasPaso2()
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarItemsClinicos($validator);
        });
    }
}
