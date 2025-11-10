<?php

namespace App\Http\Requests;

use App\Models\ExcepcionDisponibilidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreExcepcionDisponibilidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ajustá si querés chequear roles/permissions aquí.
        return true;
    }

    public function rules(): array
    {
        return [
            'profesional_id' => ['required', 'integer', 'exists:users,id'],
            'fecha'          => ['required', 'date'],
            'bloqueado'      => ['nullable', 'boolean'], // ej. 1 = bloquea, 0 = habilita
            'hora_desde'     => ['nullable', 'date_format:H:i'],
            'hora_hasta'     => ['nullable', 'date_format:H:i'],
            'motivo'         => ['nullable', 'string', 'max:150'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $profId    = (int) $this->input('profesional_id');
            $fecha     = $this->input('fecha');
            $horaDesde = $this->input('hora_desde');
            $horaHasta = $this->input('hora_hasta');

            $isFullDay = blank($horaDesde) && blank($horaHasta);

            // 1) Reglas de consistencia horaria cuando NO es full day
            if (! $isFullDay) {
                if (blank($horaDesde) xor blank($horaHasta)) {
                    $v->errors()->add('hora_desde', 'Debés completar ambas horas o ninguna.');
                    $v->errors()->add('hora_hasta', 'Debés completar ambas horas o ninguna.');
                    return;
                }

                if ($horaDesde && $horaHasta && $horaDesde >= $horaHasta) {
                    $v->errors()->add('hora_hasta', 'La hora hasta debe ser mayor que la hora desde.');
                    return;
                }
            }

            // 2) Validación anti-duplicado de “día completo”
            if ($isFullDay && ExcepcionDisponibilidad::yaExisteFullDay($profId, $fecha)) {
                $v->errors()->add('fecha', 'Ya existe una excepción de día completo para esa fecha.');
            }
        });
    }
}
