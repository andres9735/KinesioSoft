<?php

namespace App\Http\Requests;

use App\Models\MatriculaProfesionalKinesiologia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoMatriculaProfesionalRequest extends FormRequest
{
    /**
     * Autoriza la operación utilizando la Policy de matrículas
     * profesionales.
     */
    public function authorize(): bool
    {
        $matricula = $this->route('matricula');
        $user = $this->user();

        return $matricula instanceof MatriculaProfesionalKinesiologia
            && $user !== null
            && $user->can('toggleActiva', $matricula);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'activar' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'activar.required' =>
            'Debe indicar el estado solicitado para la matrícula profesional.',

            'activar.boolean' =>
            'El estado solicitado para la matrícula profesional no es válido.',
        ];
    }
}
