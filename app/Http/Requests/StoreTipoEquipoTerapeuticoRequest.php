<?php

namespace App\Http\Requests;

use App\Enums\TipoCategoriaClinicaEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTipoEquipoTerapeuticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalizados = [];

        foreach (['codigo', 'nombre', 'descripcion'] as $campo) {
            if (is_string($this->input($campo))) {
                $normalizados[$campo] = trim($this->input($campo));
            }
        }

        $this->merge($normalizados);
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:255', Rule::unique('tipos_equipo_terapeutico', 'codigo')],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'categoria_clinica_id' => [
                'required',
                'integer',
                Rule::exists('categorias_clinicas', 'id')->where(
                    fn ($query) => $query
                        ->where('activo', true)
                        ->where('tipo', TipoCategoriaClinicaEnum::EQUIPAMIENTO->value)
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categoria_clinica_id.exists' => 'La categoría clínica debe estar activa y ser de tipo EQUIPAMIENTO.',
        ];
    }
}
