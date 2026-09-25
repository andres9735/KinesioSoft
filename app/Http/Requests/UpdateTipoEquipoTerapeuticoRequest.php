<?php

namespace App\Http\Requests;

use App\Enums\TipoCategoriaClinicaEnum;
use App\Models\TipoEquipoTerapeutico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTipoEquipoTerapeuticoRequest extends FormRequest
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
        $tipoEquipoTerapeutico = $this->route('tipoEquipoTerapeutico');
        $categoriaSolicitadaId = (int) $this->input('categoria_clinica_id');
        $conservaAsociacionActual = $tipoEquipoTerapeutico instanceof TipoEquipoTerapeutico
            && (int) $tipoEquipoTerapeutico->categoria_clinica_id === $categoriaSolicitadaId;

        $reglaCategoria = Rule::exists('categorias_clinicas', 'id');

        if (! $conservaAsociacionActual) {
            $reglaCategoria->where(
                fn ($query) => $query
                    ->where('activo', true)
                    ->where('tipo', TipoCategoriaClinicaEnum::EQUIPAMIENTO->value)
            );
        }

        return [
            'codigo' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tipos_equipo_terapeutico', 'codigo')->ignore(
                    $tipoEquipoTerapeutico instanceof TipoEquipoTerapeutico
                        ? $tipoEquipoTerapeutico->getKey()
                        : null
                ),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'categoria_clinica_id' => ['required', 'integer', $reglaCategoria],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categoria_clinica_id.exists' => 'La nueva categoría clínica debe estar activa y ser de tipo EQUIPAMIENTO.',
        ];
    }
}
