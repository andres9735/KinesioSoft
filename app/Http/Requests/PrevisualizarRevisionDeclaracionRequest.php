<?php

namespace App\Http\Requests;

use App\Models\Turno;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

final class PrevisualizarRevisionDeclaracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('revisarDeclaracionSalud', [Turno::class, (int) $this->route('turno')]);
    }

    public function rules(): array
    {
        return ['contexto' => ['required', 'string', 'max:4096'], 'propuesta' => ['required', 'string', 'json', 'max:2000000']];
    }

    protected function failedValidation(Validator $validator): void
    {
        // No flash de datos clínicos a sesión ni eco del contenido inválido.
        throw new HttpResponseException(response()->view('historia-clinica.revision-peticion-invalida', [
            'turnoId' => $this->route('turno'),
        ], 422)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer'));
    }
}
