<?php

namespace App\Http\Requests\Agenda;

use App\Models\BloqueoAgenda;
use Illuminate\Foundation\Http\FormRequest;

class AnularBloqueoAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bloqueo = $this->route('bloqueo');

        return $bloqueo instanceof BloqueoAgenda
            && ($this->user()?->can('anular', $bloqueo) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $normalizados = [];

        foreach (['motivo_anulacion', 'snapshot'] as $campo) {
            if (is_string($this->input($campo))) {
                $normalizados[$campo] = trim($this->input($campo));
            }
        }

        $this->merge($normalizados);
    }

    public function rules(): array
    {
        return [
            'motivo_anulacion' => ['required', 'string', 'max:500'],
            'snapshot' => ['required', 'string'],
            'anulado_at' => ['prohibited'],
            'kinesiologa_id' => ['prohibited'],
            'inicio_at' => ['prohibited'],
            'fin_at' => ['prohibited'],
            'motivo' => ['prohibited'],
            'estado' => ['prohibited'],
            'tipo' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'motivo_anulacion' => 'motivo de anulación',
            'snapshot' => 'snapshot del bloqueo',
        ];
    }
}
