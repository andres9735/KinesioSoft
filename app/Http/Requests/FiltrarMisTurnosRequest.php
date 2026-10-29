<?php

namespace App\Http\Requests;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrarMisTurnosRequest extends FormRequest
{
    protected $redirectRoute = 'turnos.mis-turnos.index';

    public function authorize(): bool
    {
        return true; // Se preservan los middleware de paciente existentes.
    }

    public function rules(): array
    {
        return [
            'profesional' => ['nullable', 'string', 'max:255'],
            'tipo' => ['bail', 'nullable', 'string', Rule::enum(TipoTurnoEnum::class)],
            'estado' => ['bail', 'nullable', 'string', Rule::enum(EstadoTurnoEnum::class)],
            'fecha_desde' => ['bail', 'nullable', 'string', 'date_format:Y-m-d'],
            'fecha_hasta' => ['bail', 'nullable', 'string', 'date_format:Y-m-d', ...(is_string($this->input('fecha_desde')) && $this->filled('fecha_desde') ? ['after_or_equal:fecha_desde'] : [])],
            'aplicar' => ['nullable', Rule::in(['1'])],
        ];
    }

    public function filtros(): array
    {
        return array_filter($this->safe()->only(['profesional', 'tipo', 'estado', 'fecha_desde', 'fecha_hasta']),
            static fn ($valor) => $valor !== null && $valor !== '');
    }

    protected function prepareForValidation(): void
    {
        foreach (['profesional', 'tipo', 'estado', 'fecha_desde', 'fecha_hasta'] as $campo) {
            if (is_string($this->input($campo))) {
                $this->merge([$campo => trim($this->input($campo))]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'profesional.string' => 'Ingresá un nombre o apellido válido.',
            'profesional.max' => 'La búsqueda de profesional no puede superar los 255 caracteres.',
            'tipo.enum' => 'Seleccioná un tipo de turno válido.',
            'tipo.string' => 'Seleccioná un tipo de turno válido.',
            'estado.enum' => 'Seleccioná un estado válido.',
            'estado.string' => 'Seleccioná un estado válido.',
            'fecha_desde.string' => 'Ingresá una fecha desde válida.',
            'fecha_hasta.string' => 'Ingresá una fecha hasta válida.',
            'fecha_desde.date_format' => 'Ingresá una fecha desde válida.',
            'fecha_hasta.date_format' => 'Ingresá una fecha hasta válida.',
            'fecha_hasta.after_or_equal' => 'La fecha desde no puede ser posterior a la fecha hasta.',
            'aplicar.in' => 'La solicitud de filtros no es válida.',
        ];
    }
}
