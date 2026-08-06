<?php

namespace App\Http\Requests;

use App\Models\ColegioEmisor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateColegioEmisorRequest extends FormRequest
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
     * Reglas para actualizar un colegio emisor.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $colegio = $this->route('colegio');

        $colegioId = $colegio instanceof ColegioEmisor
            ? $colegio->id
            : null;

        $jurisdiccionActualId = $colegio instanceof ColegioEmisor
            ? $colegio->jurisdiccion_id
            : null;

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\pL\pN\s\.\,\-\(\)]+$/u',
                Rule::unique(
                    'colegios_emisores',
                    'nombre'
                )->ignore($colegioId),
            ],

            'sigla' => [
                'required',
                'string',
                'min:2',
                'max:20',
                'regex:/^[\pL\pN\s\-\.]+$/u',
                Rule::unique(
                    'colegios_emisores',
                    'sigla'
                )->ignore($colegioId),
            ],

            'jurisdiccion_id' => [
                'required',
                'integer',
                Rule::exists('jurisdicciones', 'id')
                    ->where(function ($query) use (
                        $jurisdiccionActualId
                    ): void {
                        $query->where(function ($subquery) use (
                            $jurisdiccionActualId
                        ): void {
                            $subquery->where('activo', true);

                            if ($jurisdiccionActualId !== null) {
                                $subquery->orWhere(
                                    'id',
                                    $jurisdiccionActualId
                                );
                            }
                        });
                    }),
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
            'nombre.required' =>
            'Debe ingresar el nombre del colegio emisor.',

            'nombre.max' =>
            'El nombre no puede superar los 255 caracteres.',

            'nombre.regex' =>
            'El nombre contiene caracteres no permitidos.',

            'nombre.unique' =>
            'Ya existe un colegio emisor con ese nombre.',

            'sigla.required' =>
            'Debe ingresar la sigla del colegio emisor.',

            'sigla.min' =>
            'La sigla debe tener al menos 2 caracteres.',

            'sigla.max' =>
            'La sigla no puede superar los 20 caracteres.',

            'sigla.regex' =>
            'La sigla solo puede contener letras, números, espacios, puntos y guiones.',

            'sigla.unique' =>
            'Ya existe un colegio emisor con esa sigla.',

            'jurisdiccion_id.required' =>
            'Debe seleccionar una jurisdicción.',

            'jurisdiccion_id.integer' =>
            'La jurisdicción seleccionada no es válida.',

            'jurisdiccion_id.exists' =>
            'La jurisdicción seleccionada no existe, se encuentra inactiva o no puede utilizarse para esta operación.',
        ];
    }
}
