<?php

namespace App\Http\Requests;

use App\Enums\SistemaOrigenTecnicaTerapeuticaEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTecnicaTerapeuticaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalizados = [];

        foreach (['sistema_origen', 'codigo', 'nombre', 'descripcion'] as $campo) {
            if (is_string($this->input($campo))) {
                $normalizados[$campo] = trim($this->input($campo));
            }
        }

        $this->merge($normalizados);
    }

    public function rules(): array
    {
        return [
            'sistema_origen' => ['required', Rule::enum(SistemaOrigenTecnicaTerapeuticaEnum::class)],
            'codigo' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tecnicas_terapeuticas', 'codigo')
                    ->where('sistema_origen', $this->input('sistema_origen')),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
        ];
    }
}
