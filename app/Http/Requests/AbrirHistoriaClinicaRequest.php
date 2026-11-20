<?php

namespace App\Http\Requests;

use App\Models\Turno;
use App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AbrirHistoriaClinicaRequest extends FormRequest
{
    private array $datosApertura;

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('abrirHistoriaClinica', [Turno::class, (int) $this->route('turno')]);
    }

    public function rules(): array
    {
        return ['contexto' => ['required', 'string', 'max:4096']];
    }

    protected function passedValidation(): void
    {
        $this->datosApertura = app(ValidarDatosAperturaHistoriaClinica::class)->validar($this->all());
    }

    public function datosApertura(): array
    {
        return $this->datosApertura;
    }
}
