<?php

namespace App\Http\Requests\Agenda;

use App\Models\ConfiguracionAgenda;
use Illuminate\Foundation\Http\FormRequest;

class AnularConfiguracionAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $configuracion = $this->route('configuracion');

        return $configuracion instanceof ConfiguracionAgenda
            && ($this->user()?->can('anular', $configuracion) ?? false);
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
            'kinesiologa_id' => ['prohibited'],
            'anulado_at' => ['prohibited'],
            'estado' => ['prohibited'],
            'vigencia_desde' => ['prohibited'],
            'vigencia_hasta' => ['prohibited'],
            'duracion_turno_minutos' => ['prohibited'],
            'franjas' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'motivo_anulacion' => 'motivo de anulación',
        ];
    }
}
