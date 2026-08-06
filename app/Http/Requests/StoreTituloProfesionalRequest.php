<?php

namespace App\Http\Requests;

use App\Models\TituloProfesional;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreTituloProfesionalRequest extends FormRequest
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
     * Reglas para registrar un título profesional.
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
            ],
        ];
    }

    /**
     * Comprueba las colisiones del nombre normalizado y del slug.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('nombre')) {
                return;
            }

            $nombre = (string) $this->input('nombre');

            $existeNombre = TituloProfesional::query()
                ->where('nombre', $nombre)
                ->exists();

            if ($existeNombre) {
                $validator->errors()->add(
                    'nombre',
                    'Ya existe un título profesional con ese nombre.'
                );

                return;
            }

            $slug = Str::slug($nombre);

            $existeSlug = TituloProfesional::query()
                ->where('slug', $slug)
                ->exists();

            if ($existeSlug) {
                $validator->errors()->add(
                    'nombre',
                    'El nombre ingresado genera un slug duplicado.'
                );
            }
        });
    }
}
