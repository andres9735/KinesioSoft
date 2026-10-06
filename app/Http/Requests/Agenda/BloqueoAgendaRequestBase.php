<?php

namespace App\Http\Requests\Agenda;

use App\Services\Agenda\RelojAplicacion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Throwable;

abstract class BloqueoAgendaRequestBase extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalizados = [];

        foreach (['inicio_at', 'fin_at', 'motivo', 'snapshot'] as $campo) {
            if (is_string($this->input($campo))) {
                $normalizados[$campo] = trim($this->input($campo));
            }
        }

        $this->merge($normalizados);
    }

    /** @return array<string, array<int, mixed>> */
    protected function reglasCandidato(bool $incluyeSnapshot): array
    {
        return [
            'inicio_at' => ['required', 'date'],
            'fin_at' => ['required', 'date', 'after:inicio_at'],
            'motivo' => ['required', 'string', 'max:500'],
            'snapshot' => $incluyeSnapshot
                ? ['required', 'string']
                : ['prohibited'],
            'kinesiologa_id' => ['prohibited'],
            'anulado_at' => ['prohibited'],
            'motivo_anulacion' => ['prohibited'],
            'estado' => ['prohibited'],
            'tipo' => ['prohibited'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(RelojAplicacion $reloj): array
    {
        return [
            function (Validator $validator) use ($reloj): void {
                if ($validator->errors()->has('fin_at')) {
                    return;
                }

                try {
                    $fin = CarbonImmutable::parse(
                        (string) $this->input('fin_at'),
                        config('app.timezone')
                    );
                } catch (Throwable) {
                    return;
                }

                if (! $fin->isAfter($reloj->ahora())) {
                    $validator->errors()->add(
                        'fin_at',
                        'La fecha y hora de finalización debe ser posterior al momento actual.'
                    );
                }
            },
        ];
    }

    protected function passedValidation(): void
    {
        $this->merge([
            'inicio_at' => $this->fechaCanonica((string) $this->input('inicio_at')),
            'fin_at' => $this->fechaCanonica((string) $this->input('fin_at')),
            'motivo' => trim((string) $this->input('motivo')),
        ]);
    }

    /** @param array|int|string|null $key */
    public function validated($key = null, $default = null): mixed
    {
        $validados = parent::validated();
        $validados['inicio_at'] = $this->fechaCanonica((string) $validados['inicio_at']);
        $validados['fin_at'] = $this->fechaCanonica((string) $validados['fin_at']);
        $validados['motivo'] = trim((string) $validados['motivo']);

        return data_get($validados, $key, $default);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fin_at.after' => 'La fecha y hora de finalización debe ser posterior a la de inicio.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'inicio_at' => 'fecha y hora de inicio',
            'fin_at' => 'fecha y hora de finalización',
            'motivo' => 'motivo',
            'snapshot' => 'snapshot de revisión',
        ];
    }

    private function fechaCanonica(string $valor): string
    {
        return CarbonImmutable::parse($valor, config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }
}
