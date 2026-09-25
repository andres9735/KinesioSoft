<?php

namespace App\Http\Requests;

use App\Enums\SistemaOrigenPruebaFuncionalEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePruebaFuncionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalizados = [];

        foreach (['sistema_origen', 'codigo', 'nombre', 'descripcion', 'unidad_medida'] as $campo) {
            if (is_string($this->input($campo))) {
                $normalizados[$campo] = trim($this->input($campo));
            }
        }

        $this->merge($normalizados);
    }

    public function rules(): array
    {
        return [
            'sistema_origen' => ['required', Rule::enum(SistemaOrigenPruebaFuncionalEnum::class)],
            'codigo' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pruebas_funcionales', 'codigo')
                    ->where('sistema_origen', $this->input('sistema_origen')),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'unidad_medida' => ['nullable', 'string', 'max:255'],
        ];
    }
}
