<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ConsultarDetalleTurnoRequest extends FiltrarMisTurnosRequest
{
    public function authorize(): bool
    {
        return Gate::authorize('view', $this->route('turno'))->allowed();
    }

    public function rules(): array
    {
        return parent::rules() + ['seccion' => ['nullable', Rule::in(['proximos', 'historicos'])]];
    }
}
