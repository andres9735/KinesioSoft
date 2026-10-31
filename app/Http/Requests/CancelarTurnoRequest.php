<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;

class CancelarTurnoRequest extends ConfirmarAsistenciaTurnoRequest
{
    public function authorize(): bool
    {
        return Gate::authorize('cancelar', $this->route('turno'))->allowed();
    }

    public function rules(): array
    {
        return array_replace(parent::rules(), ['motivo' => ['nullable', 'string']]);
    }
}
