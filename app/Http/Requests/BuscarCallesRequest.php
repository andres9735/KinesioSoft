<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BuscarCallesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:150'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $termino = $this->input('q');

        if (is_string($termino)) {
            $this->merge(['q' => trim($termino)]);
        }
    }
}
