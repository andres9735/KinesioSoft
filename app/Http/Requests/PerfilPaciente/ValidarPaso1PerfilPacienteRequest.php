<?php

namespace App\Http\Requests\PerfilPaciente;

class ValidarPaso1PerfilPacienteRequest extends PerfilPacienteRequestBase
{
    public function rules(): array
    {
        return $this->reglasPaso1();
    }
}
