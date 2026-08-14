<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FiltrarAuditoriasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglasFechaHasta = [
            'nullable',
            'date_format:Y-m-d',
        ];

        if ($this->filled('fecha_desde')) {
            $reglasFechaHasta[] = 'after_or_equal:fecha_desde';
        }

        return [
            'fecha_desde' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'fecha_hasta' => $reglasFechaHasta,
            'evento' => [
                'nullable',
                'string',
                'max:100',
            ],
            'modulo' => [
                'nullable',
                'string',
                'max:100',
            ],
            'actor' => [
                'nullable',
                'string',
                'max:255',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_desde.date_format' =>
            'La fecha desde debe tener un formato válido.',
            'fecha_hasta.date_format' =>
            'La fecha hasta debe tener un formato válido.',
            'fecha_hasta.after_or_equal' =>
            'La fecha inicial no puede ser posterior a la fecha final.',
            'evento.string' =>
            'El evento seleccionado no es válido.',
            'evento.max' =>
            'El evento no puede superar los 100 caracteres.',
            'modulo.string' =>
            'El módulo seleccionado no es válido.',
            'modulo.max' =>
            'El módulo no puede superar los 100 caracteres.',
            'actor.string' =>
            'El criterio de usuario no es válido.',
            'actor.max' =>
            'El criterio de usuario no puede superar los 255 caracteres.',
            'page.integer' =>
            'La página solicitada no es válida.',
            'page.min' =>
            'La página solicitada no es válida.',
        ];
    }
}
