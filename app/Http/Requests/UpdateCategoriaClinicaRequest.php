<?php

namespace App\Http\Requests;

use App\Enums\TipoCategoriaClinicaEnum;
use App\Models\CategoriaClinica;
use App\Services\Catalogos\JerarquiaCategoriaClinicaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class UpdateCategoriaClinicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalizados = [];

        foreach (['codigo', 'nombre', 'tipo'] as $campo) {
            if (is_string($this->input($campo))) {
                $normalizados[$campo] = trim($this->input($campo));
            }
        }

        $this->merge($normalizados);
    }

    public function rules(): array
    {
        $categoriaClinica = $this->route('categoriaClinica');

        return [
            'codigo' => [
                'required', 'string', 'max:255',
                Rule::unique('categorias_clinicas', 'codigo')
                    ->ignore($categoriaClinica instanceof CategoriaClinica ? $categoriaClinica->getKey() : null),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoCategoriaClinicaEnum::class)],
            'categoria_padre_id' => ['nullable', 'integer', Rule::exists('categorias_clinicas', 'id')],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['tipo', 'categoria_padre_id'])) {
                    return;
                }

                $categoriaClinica = $this->route('categoriaClinica');

                if (! $categoriaClinica instanceof CategoriaClinica) {
                    return;
                }

                try {
                    app(JerarquiaCategoriaClinicaService::class)->validarParaActualizar(
                        $categoriaClinica,
                        (string) $this->input('tipo'),
                        $this->input('categoria_padre_id')
                    );
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $campo => $mensajes) {
                        foreach ($mensajes as $mensaje) {
                            $validator->errors()->add($campo, $mensaje);
                        }
                    }
                }
            },
        ];
    }
}
