<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConsultarDisponibilidadPrimeraConsultaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth, verificación y perfil se conectan mediante los middleware de DT-07 en 7.18.
        return true;
    }

    public function rules(): array
    {
        return [
            'kinesiologa_id' => ['bail', 'required', 'integer', 'exists:kinesiologas,id'],
            'desde' => ['bail', 'required', 'date_format:Y-m-d'],
            'hasta' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->all()) as $campo) {
                if (! in_array($campo, ['kinesiologa_id', 'desde', 'hasta', '_token'], true)) {
                    $validator->errors()->add($campo, 'Este campo no está permitido.');
                }
            }
            if ($validator->errors()->hasAny(['desde', 'hasta'])) {
                return;
            }
            $desde = CarbonImmutable::createFromFormat('!Y-m-d', $this->input('desde'), config('app.timezone'));
            $hasta = CarbonImmutable::createFromFormat('!Y-m-d', $this->input('hasta'), config('app.timezone'));
            if ($desde->diffInDays($hasta) + 1 > config('turnos.disponibilidad.max_dias_por_consulta')) {
                $validator->errors()->add('hasta', 'La ventana supera la cantidad máxima de fechas por consulta.');
            }
        }];
    }

    public function messages(): array
    {
        return ['hasta.after_or_equal' => 'La fecha hasta no puede ser anterior a la fecha desde.'];
    }

    public function attributes(): array
    {
        return ['kinesiologa_id' => 'profesional', 'desde' => 'fecha desde', 'hasta' => 'fecha hasta'];
    }
}
