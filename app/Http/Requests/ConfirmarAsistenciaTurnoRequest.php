<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ConfirmarAsistenciaTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::authorize('confirmar', $this->route('turno'))->allowed();
    }

    public function rules(): array
    {
        return ['referencia_programacion' => ['required', 'string']] + array_fill_keys([
            'paciente_id', 'actor_id', 'user_id', 'canal', 'estado', 'tipo',
            'kinesiologa_id', 'inicio_at', 'fin_at', 'motivo',
        ], ['prohibited']);
    }

    public function messages(): array
    {
        return [
            'referencia_programacion.required' => 'Actualizá el detalle antes de realizar la operación.',
            'referencia_programacion.string' => 'La referencia del turno no es válida.',
        ];
    }
}
