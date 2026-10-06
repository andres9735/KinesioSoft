<?php

namespace App\Http\Requests\Agenda;

use App\Services\Agenda\RelojAplicacion;
use App\Services\Agenda\ValidadorMatrizAgenda;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

abstract class ConfiguracionAgendaRequestBase extends FormRequest
{
    /**
     * @var list<array{dia_semana: int, hora_inicio: string, hora_fin: string}>|null
     */
    private ?array $franjasCanonicas = null;

    protected function prepareForValidation(): void
    {
        $normalizados = [];

        foreach (['vigencia_desde', 'vigencia_hasta', 'snapshot'] as $campo) {
            if (is_string($this->input($campo))) {
                $valor = trim($this->input($campo));
                $normalizados[$campo] = $campo === 'vigencia_hasta' && $valor === ''
                    ? null
                    : $valor;
            }
        }

        if (is_array($this->input('franjas'))) {
            $normalizados['franjas'] = array_values(array_filter(
                array_map(fn (mixed $franja): mixed => $this->normalizarFranja($franja), $this->input('franjas')),
                fn (mixed $franja): bool => ! $this->esFranjaCompletamenteVacia($franja)
            ));
        }

        $this->merge($normalizados);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function reglasConfiguracion(
        RelojAplicacion $reloj,
        bool $inicioEstrictamenteFuturo,
        bool $incluyeVigenciaHasta,
        bool $incluyeSnapshot
    ): array {
        $hoy = $reloj->hoy()->toDateString();
        $reglaInicio = ($inicioEstrictamenteFuturo ? 'after:' : 'after_or_equal:').$hoy;

        $reglas = [
            'vigencia_desde' => ['required', 'date_format:Y-m-d', $reglaInicio],
            'duracion_turno_minutos' => ['required', 'integer', 'min:15', 'max:180', 'multiple_of:5'],
            'franjas' => ['required', 'array', 'min:1'],
            'franjas.*' => ['required', 'array:dia_semana,hora_inicio,hora_fin'],
            'franjas.*.dia_semana' => ['required', 'integer', 'between:1,7'],
            'franjas.*.hora_inicio' => ['required', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
            'franjas.*.hora_fin' => ['required', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
            'kinesiologa_id' => ['prohibited'],
            'anulado_at' => ['prohibited'],
            'motivo_anulacion' => ['prohibited'],
            'estado' => ['prohibited'],
        ];

        $reglas['vigencia_hasta'] = $incluyeVigenciaHasta
            ? ['nullable', 'date_format:Y-m-d', 'after_or_equal:vigencia_desde']
            : ['prohibited'];

        $reglas['snapshot'] = $incluyeSnapshot
            ? ['required', 'string']
            : ['prohibited'];

        return $reglas;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(ValidadorMatrizAgenda $validadorMatriz): array
    {
        return [
            function (Validator $validator) use ($validadorMatriz): void {
                if ($this->tieneErroresDeMatriz($validator)) {
                    return;
                }

                try {
                    $this->franjasCanonicas = $validadorMatriz->validar(
                        $this->input('franjas', []),
                        (int) $this->input('duracion_turno_minutos')
                    );
                } catch (InvalidArgumentException $exception) {
                    $campo = $exception->getCode() === ValidadorMatrizAgenda::ERROR_DURACION_INVALIDA
                        ? 'duracion_turno_minutos'
                        : 'franjas';

                    $validator->errors()->add($campo, $exception->getMessage());
                }
            },
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->franjasCanonicas !== null) {
            $this->merge(['franjas' => $this->franjasCanonicas]);
        }
    }

    /**
     * @param  array|int|string|null  $key
     */
    public function validated($key = null, $default = null): mixed
    {
        $validados = parent::validated();

        if ($this->franjasCanonicas !== null) {
            $validados['franjas'] = $this->franjasCanonicas;
        }

        return data_get($validados, $key, $default);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vigencia_desde.after' => 'La fecha de inicio debe ser posterior a hoy.',
            'vigencia_desde.after_or_equal' => 'La fecha de inicio no puede estar en el pasado.',
            'vigencia_hasta.after_or_equal' => 'La fecha de finalización no puede ser anterior a la fecha de inicio.',
            'duracion_turno_minutos.multiple_of' => 'La duración estándar debe ser múltiplo de 5 minutos.',
            'franjas.min' => 'Debe informar al menos una franja horaria.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vigencia_desde' => 'fecha de inicio',
            'vigencia_hasta' => 'fecha de finalización',
            'duracion_turno_minutos' => 'duración estándar',
            'franjas' => 'franjas horarias',
            'franjas.*.dia_semana' => 'día de la semana',
            'franjas.*.hora_inicio' => 'hora de inicio',
            'franjas.*.hora_fin' => 'hora de finalización',
        ];
    }

    private function normalizarFranja(mixed $franja): mixed
    {
        if (! is_array($franja)) {
            return $franja;
        }

        foreach (['dia_semana', 'hora_inicio', 'hora_fin'] as $campo) {
            if (array_key_exists($campo, $franja) && is_string($franja[$campo])) {
                $franja[$campo] = trim($franja[$campo]);
            }
        }

        return $franja;
    }

    private function esFranjaCompletamenteVacia(mixed $franja): bool
    {
        if (! is_array($franja)) {
            return false;
        }

        foreach (['dia_semana', 'hora_inicio', 'hora_fin'] as $campo) {
            $valor = $franja[$campo] ?? null;

            if ($valor !== null && (! is_string($valor) || trim($valor) !== '')) {
                return false;
            }
        }

        return true;
    }

    private function tieneErroresDeMatriz(Validator $validator): bool
    {
        foreach ($validator->errors()->keys() as $campo) {
            if ($campo === 'duracion_turno_minutos'
                || $campo === 'franjas'
                || str_starts_with($campo, 'franjas.')) {
                return true;
            }
        }

        return false;
    }
}
