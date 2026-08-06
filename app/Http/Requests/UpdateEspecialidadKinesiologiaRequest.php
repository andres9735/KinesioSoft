<?php

namespace App\Http\Requests;

use App\Models\EspecialidadKinesiologia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEspecialidadKinesiologiaRequest extends FormRequest
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
     * Reglas para actualizar una especialidad de kinesiología.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $especialidad = $this->route('especialidad');

        $especialidadId =
            $especialidad instanceof EspecialidadKinesiologia
            ? $especialidad->id
            : null;

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\pL\s]+$/u',
                Rule::unique(
                    'especialidades_kinesiologia',
                    'nombre'
                )->ignore($especialidadId),
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

                $especialidad = $this->route('especialidad');

                $especialidadId =
                    $especialidad instanceof EspecialidadKinesiologia
                    ? $especialidad->id
                    : null;

                $nombre = preg_replace(
                    '/\s+/',
                    ' ',
                    trim((string) $this->input('nombre'))
                );

                $slug = Str::slug($nombre);

                $slugExiste = EspecialidadKinesiologia::query()
                    ->where('slug', $slug)
                    ->when(
                        $especialidadId !== null,
                        fn($query) => $query->where(
                            'id',
                            '!=',
                            $especialidadId
                        )
                    )
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
