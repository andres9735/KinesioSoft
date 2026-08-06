<?php

namespace App\Http\Requests;

use App\Models\Jurisdiccion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class UpdateJurisdiccionRequest extends FormRequest
{
    /**
     * La autorización continúa a cargo de los middlewares
     * del módulo de catálogos.
     *
     * Será centralizada mediante Policies en el Incremento 10.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza el nombre antes de validarlo.
     */
    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre');

        if (is_string($nombre)) {
            $this->merge([
                'nombre' => trim($nombre),
            ]);
        }
    }

    /**
     * Reglas para actualizar una jurisdicción.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\pL\s]+$/u',
            ],
        ];
    }

    /**
     * Mensajes personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.regex' =>
            'El nombre solo puede contener letras y espacios.',
        ];
    }

    /**
     * Comprueba las colisiones del nombre normalizado y del slug,
     * excluyendo la jurisdicción que se está editando.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('nombre')) {
                return;
            }

            $jurisdiccion = $this->route('jurisdiccion');

            if (! $jurisdiccion instanceof Jurisdiccion) {
                return;
            }

            $nombre = (string) $this->input('nombre');

            $existeNombre = Jurisdiccion::query()
                ->where('nombre', $nombre)
                ->where('id', '!=', $jurisdiccion->id)
                ->exists();

            if ($existeNombre) {
                $validator->errors()->add(
                    'nombre',
                    'Ya existe una jurisdicción con ese nombre.'
                );

                return;
            }

            $slug = Str::slug($nombre);

            $existeSlug = Jurisdiccion::query()
                ->where('slug', $slug)
                ->where('id', '!=', $jurisdiccion->id)
                ->exists();

            if ($existeSlug) {
                $validator->errors()->add(
                    'nombre',
                    'El nombre ingresado genera un slug que ya existe. Intente con una variante distinta.'
                );
            }
        });
    }
}
