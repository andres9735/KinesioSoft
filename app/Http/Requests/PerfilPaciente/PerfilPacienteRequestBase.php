<?php

namespace App\Http\Requests\PerfilPaciente;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class PerfilPacienteRequestBase extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas correspondientes al paso 1:
     * datos personales, contacto y emergencia.
     */
    protected function reglasPaso1(): array
    {
        $userId = $this->user()?->getAuthIdentifier();

        return [
            'dni' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'dni')->ignore($userId),
            ],

            'fecha_nacimiento' => [
                'required',
                'date',
                'before:today',
            ],

            'sexo' => [
                'required',
                Rule::in([
                    'masculino',
                    'femenino',
                    'otro',
                    'prefiero_no_decir',
                ]),
            ],

            'telefono' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]+$/',
            ],

            'direccion' => [
                'required',
                'string',
                'max:255',
            ],

            'contacto_emergencia_nombre' => [
                'required',
                'string',
                'max:255',
            ],

            'contacto_emergencia_telefono' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]+$/',
            ],
        ];
    }

    /**
     * Reglas correspondientes al paso 2:
     * declaración inicial de salud.
     */
    protected function reglasPaso2(): array
    {
        return [
            'objetivo_salud' => [
                'required',
                'string',
            ],

            'peso_kg' => [
                'required',
                'numeric',
                'min:1',
                'max:500',
            ],

            'altura_cm' => [
                'required',
                'numeric',
                'min:30',
                'max:250',
            ],

            'ocupacion' => [
                'required',
                'string',
                'max:255',
            ],

            'nivel_actividad_fisica' => [
                'required',
                Rule::in([
                    'SEDENTARIO',
                    'MODERADO',
                    'ACTIVO',
                ]),
            ],

            'detalle_actividad_fisica' => [
                'required',
                'string',
            ],

            'antecedentes_respuesta' => [
                'required',
                Rule::in(['si', 'no']),
            ],

            'antecedentes' => [
                'nullable',
                'array',
            ],

            'antecedentes.*' => [
                'nullable',
                'string',
            ],

            'cirugias_respuesta' => [
                'required',
                Rule::in(['si', 'no']),
            ],

            'cirugias' => [
                'nullable',
                'array',
            ],

            'cirugias.*' => [
                'nullable',
                'string',
            ],

            'alergias_respuesta' => [
                'required',
                Rule::in(['si', 'no']),
            ],

            'alergias' => [
                'nullable',
                'array',
            ],

            'alergias.*' => [
                'nullable',
                'string',
            ],

            'medicacion_respuesta' => [
                'required',
                Rule::in(['si', 'no']),
            ],

            'medicaciones' => [
                'nullable',
                'array',
            ],

            'medicaciones.*' => [
                'nullable',
                'string',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $camposTexto = [
            'dni',
            'telefono',
            'direccion',
            'contacto_emergencia_nombre',
            'contacto_emergencia_telefono',
            'objetivo_salud',
            'ocupacion',
            'detalle_actividad_fisica',
        ];

        $normalizados = [];

        foreach ($camposTexto as $campo) {
            $valor = $this->input($campo);

            if (is_string($valor)) {
                $normalizados[$campo] = trim($valor);
            }
        }

        $nivelActividad = $this->input(
            'nivel_actividad_fisica'
        );

        if (is_string($nivelActividad)) {
            $normalizados['nivel_actividad_fisica'] =
                strtoupper(trim($nivelActividad));
        }

        foreach (
            [
                'antecedentes_respuesta',
                'cirugias_respuesta',
                'alergias_respuesta',
                'medicacion_respuesta',
            ] as $campo
        ) {
            $valor = $this->input($campo);

            if (is_string($valor)) {
                $normalizados[$campo] =
                    strtolower(trim($valor));
            }
        }

        foreach (
            [
                'antecedentes',
                'cirugias',
                'alergias',
                'medicaciones',
            ] as $campo
        ) {
            $valores = $this->input($campo);

            if (! is_array($valores)) {
                continue;
            }

            $valoresNormalizados = array_map(
                static fn($valor) => is_string($valor)
                    ? trim($valor)
                    : $valor,
                $valores
            );

            $normalizados[$campo] = array_values(
                array_filter(
                    $valoresNormalizados,
                    static fn($valor) =>
                    ! is_string($valor)
                        || $valor !== ''
                )
            );
        }

        if ($normalizados !== []) {
            $this->merge($normalizados);
        }
    }

    /**
     * Comprueba la coherencia entre las respuestas Sí/No
     * y los hechos clínicos informados.
     */
    protected function validarItemsClinicos(
        Validator $validator
    ): void {
        $configuracion = [
            'antecedentes_respuesta' => 'antecedentes',
            'cirugias_respuesta' => 'cirugias',
            'alergias_respuesta' => 'alergias',
            'medicacion_respuesta' => 'medicaciones',
        ];

        foreach ($configuracion as $respuesta => $items) {
            if (
                $validator->errors()->has($respuesta)
                || $validator->errors()->has($items)
                || $validator->errors()->has($items . '.*')
            ) {
                continue;
            }

            $valorRespuesta = $this->input($respuesta);

            $valores = collect(
                $this->input($items, [])
            )
                ->filter(
                    static fn($valor) =>
                    is_string($valor)
                        && trim($valor) !== ''
                );

            if (
                $valorRespuesta === 'si'
                && $valores->isEmpty()
            ) {
                $validator->errors()->add(
                    $items,
                    'Debe ingresar al menos un detalle.'
                );
            }

            if (
                $valorRespuesta === 'no'
                && $valores->isNotEmpty()
            ) {
                $validator->errors()->add(
                    $items,
                    'No debe ingresar detalles cuando la respuesta es No.'
                );
            }
        }
    }
}
