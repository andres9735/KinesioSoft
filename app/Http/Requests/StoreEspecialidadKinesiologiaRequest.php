<?php

namespace App\Http\Requests;

use App\Models\EspecialidadKinesiologia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEspecialidadKinesiologiaRequest extends FormRequest
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
     * Reglas para registrar una especialidad de kinesiología.
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
                Rule::unique(
                    'especialidades_kinesiologia',
                    'nombre'
                ),
            ],
        ];
    }

    /**
     * Validación adicional del slug generado.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('nombre')) {
                    return;
                }

                $nombre = preg_replace(
                    '/\s+/',
                    ' ',
                    trim((string) $this->input('nombre'))
                );

                $slug = Str::slug($nombre);

                $slugExiste = EspecialidadKinesiologia::query()
                    ->where('slug', $slug)
                    ->exists();

                if ($slugExiste) {
                    $validator->errors()->add(
                        'nombre',
                        'El nombre ingresado genera un slug que ya existe. Intente con una variante distinta.'
                    );
                }
            },
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
            'nombre.required' =>
            'Debe ingresar el nombre de la especialidad.',

            'nombre.max' =>
            'El nombre no puede superar los 255 caracteres.',

            'nombre.regex' =>
            'El nombre solo puede contener letras y espacios.',

            'nombre.unique' =>
            'Ya existe una especialidad de kinesiología con ese nombre.',
        ];
    }
}
