<?php

namespace App\Http\Requests;

use App\Models\Turno;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

final class ConfirmarRevisionDeclaracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('revisarDeclaracionSalud', [Turno::class, (int) $this->route('turno')]);
    }

    public function rules(): array
    {
        return ['confirmacion' => ['required', 'string', 'max:4000000'], 'confirmar' => ['accepted']];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($v): void {
            if (array_diff(array_keys($this->all()), ['_token', 'confirmacion', 'confirmar']) !== []) {
                $v->errors()->add('confirmacion', 'El contenido cambió: generá una nueva vista previa.');
            }
        });
    }

    protected function failedAuthorization(): void
    {
        $this->fallar('autorizacion', 403);
    }

    protected function failedValidation(Validator $validator): void
    {
        // No devolver el comprobante ni almacenar contenido clínico en la sesión.
        $this->fallar('confirmacion', 422);
    }

    private function fallar(string $tipo, int $status): never
    {
        throw new HttpResponseException(response()->view('historia-clinica.revision-error', [
            'turnoId' => $this->route('turno'), 'tipo' => $tipo,
        ], $status)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer'));
    }
}
