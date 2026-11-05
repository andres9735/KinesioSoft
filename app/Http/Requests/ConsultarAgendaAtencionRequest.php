<?php

namespace App\Http\Requests;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConsultarAgendaAtencionRequest extends FormRequest
{
    protected $redirectRoute = 'turnos.agenda-atencion.index';

    public function authorize(): bool
    {
        // Los middleware profesionales preceden a la validación; el servicio autoriza el ámbito.
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['sometimes', 'bail', 'required', 'string', 'date_format:Y-m-d', 'prohibits:desde,hasta'],
            'desde' => ['bail', Rule::requiredIf($this->hasAny(['desde', 'hasta'])), 'string', 'date_format:Y-m-d'],
            'hasta' => ['bail', Rule::requiredIf($this->hasAny(['desde', 'hasta'])), 'string', 'date_format:Y-m-d'],
            'paciente' => ['nullable', 'string'],
            'hora_desde' => ['nullable', 'bail', 'string', 'date_format:H:i'],
            'hora_hasta' => ['nullable', 'bail', 'string', 'date_format:H:i'],
            'estado' => ['nullable', 'bail', 'string', Rule::in(array_column(EstadoTurnoEnum::cases(), 'value'))],
            'tipo' => ['nullable', 'bail', 'string', Rule::in(array_column(TipoTurnoEnum::cases(), 'value'))],
            'page' => ['sometimes', 'bail', 'required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Comparar sólo strings cuyo formato ya fue validado; un array manipulado
            // en el otro extremo del intervalo debe producir validación, nunca un 500.
            foreach ([['desde', 'hasta'], ['hora_desde', 'hora_hasta']] as [$desde, $hasta]) {
                if (! $validator->errors()->hasAny([$desde, $hasta])
                    && $this->filled($desde) && $this->filled($hasta)
                    && $this->input($hasta) < $this->input($desde)) {
                    $validator->errors()->add($hasta, $this->messages()[$hasta.'.after_or_equal']);
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'Ingresá una fecha válida con formato AAAA-MM-DD.',
            'fecha.string' => 'Ingresá una fecha válida con formato AAAA-MM-DD.',
            'fecha.date_format' => 'Ingresá una fecha válida con formato AAAA-MM-DD.',
            'fecha.prohibits' => 'Elegí una fecha o un período, no ambos.',
            'desde.required' => 'Indicá ambas fechas del período.',
            'hasta.required' => 'Indicá ambas fechas del período.',
            'desde.date_format' => 'Ingresá una fecha inicial válida con formato AAAA-MM-DD.',
            'hasta.date_format' => 'Ingresá una fecha final válida con formato AAAA-MM-DD.',
            'desde.string' => 'Ingresá una única fecha inicial válida.',
            'hasta.string' => 'Ingresá una única fecha final válida.',
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'hora_desde.date_format' => 'Ingresá una hora de inicio válida con formato HH:MM.',
            'hora_hasta.date_format' => 'Ingresá una hora de inicio válida con formato HH:MM.',
            'hora_hasta.after_or_equal' => 'La hora hasta debe ser igual o posterior a la hora desde.',
            'hora_desde.string' => 'Ingresá una única hora desde válida.',
            'hora_hasta.string' => 'Ingresá una única hora hasta válida.',
            'paciente.string' => 'Ingresá un texto para buscar por nombre o apellido del paciente.',
            'estado.string' => 'Seleccioná un único estado válido.',
            'estado.in' => 'Seleccioná uno de los seis estados de turno.',
            'tipo.string' => 'Seleccioná un único tipo de consulta válido.',
            'tipo.in' => 'Seleccioná Primera consulta o Seguimiento.',
            'page.required' => 'Indicá un número de página válido.',
            'page.integer' => 'La página debe ser un número entero positivo.',
            'page.min' => 'La página debe ser un número entero positivo.',
        ];
    }

    public function attributes(): array
    {
        return [
            'desde' => 'fecha inicial', 'hasta' => 'fecha final', 'paciente' => 'nombre del paciente',
            'hora_desde' => 'hora desde', 'hora_hasta' => 'hora hasta',
            'estado' => 'estado', 'tipo' => 'tipo de consulta', 'page' => 'página',
        ];
    }
}
