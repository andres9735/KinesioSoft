<?php

namespace App\Http\Requests;

use App\Services\Turnos\PoliticaDocumentosClinicosService;
use App\Services\Turnos\ReplayPrimeraConsultaService;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class ConfirmarPrimeraConsultaRequest extends FormRequest
{
    private const CAMPOS = ['motivo_solicitud', 'kinesiologa_id', 'inicio_at', 'clave_idempotencia', 'adjuntos'];

    protected function prepareForValidation(): void
    {
        $adjuntos = $this->input('adjuntos');
        if (! is_array($adjuntos)) {
            return;
        }

        foreach ($adjuntos as $indice => $adjunto) {
            if (is_array($adjunto) && is_string($adjunto['descripcion'] ?? null)) {
                $adjuntos[$indice]['descripcion'] = trim($adjunto['descripcion']);
            }
        }

        $this->merge(['adjuntos' => $adjuntos]);
    }

    public function authorize(): bool
    {
        // Como los Requests de perfil del paciente, delega rol/perfil a los middleware de DT-07.
        return true;
    }

    public function rules(): array
    {
        return ['clave_idempotencia' => ['bail', 'required', 'string', 'uuid']];
    }

    /** El hook nativo de FormRequest construye el validador final sólo después de resolver la fase 1. */
    public function validator(Factory $factory, ReplayPrimeraConsultaService $replays, PoliticaDocumentosClinicosService $politica): Validator
    {
        $identidad = $factory->make($this->validationData(), $this->rules(), [], $this->attributes());
        $identidad->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $campo) {
                if (! in_array($campo, [...self::CAMPOS, '_token'], true)) {
                    $validator->errors()->add($campo, 'Este campo no está permitido.');
                }
            }
        });
        if ($identidad->fails()) {
            return $identidad;
        }

        $paciente = $this->user()?->paciente()->first();
        if ($paciente === null) {
            throw new InvalidArgumentException('El actor debe tener un paciente asociado.');
        }
        if ($replays->resolver($paciente, $this->input('clave_idempotencia')) !== null) {
            // validated()/safe() sólo entregan la UUID: no promover a validados los campos ignorados del retry.
            return $identidad;
        }

        $adjuntos = $this->all()['adjuntos'] ?? [];
        $erroresDocumentales = $politica->validar(is_array($adjuntos)
            ? array_map(static fn (mixed $adjunto): mixed => is_array($adjunto) ? ($adjunto['archivo'] ?? null) : null, $adjuntos)
            : []);
        $reglasDescripcion = [];
        if (is_array($adjuntos)) {
            foreach ($adjuntos as $indice => $adjunto) {
                if (is_array($adjunto) && ! isset($erroresDocumentales['archivos'][$indice])) {
                    $reglasDescripcion['adjuntos.'.$indice.'.descripcion'] = ['bail', 'required', 'string', 'max:500'];
                }
            }
        }

        $creacion = $factory->make($this->validationData(), [
            ...$this->rules(),
            'motivo_solicitud' => ['bail', 'required', 'string', 'max:500'],
            'kinesiologa_id' => ['bail', 'required', 'integer', 'exists:kinesiologas,id'],
            'inicio_at' => ['bail', 'required', 'date_format:Y-m-d H:i:s'],
            'adjuntos' => ['sometimes', 'array'],
            'adjuntos.*' => ['bail', 'array:archivo,descripcion'],
            ...$reglasDescripcion,
        ], [], $this->attributes());
        $creacion->after(function (Validator $validator) use ($erroresDocumentales): void {
            if ($validator->errors()->has('adjuntos')) {
                return;
            }
            foreach ($erroresDocumentales['coleccion'] as $codigo) {
                $validator->errors()->add('adjuntos', $this->mensajeDocumental($codigo));
            }
            foreach ($erroresDocumentales['archivos'] as $indice => $codigos) {
                foreach ($codigos as $codigo) {
                    $validator->errors()->add('adjuntos.'.$indice.'.archivo', $this->mensajeDocumental($codigo));
                }
            }
        });

        return $creacion;
    }

    public function attributes(): array
    {
        return [
            'clave_idempotencia' => 'clave de intención', 'motivo_solicitud' => 'motivo de consulta',
            'kinesiologa_id' => 'profesional', 'inicio_at' => 'fecha y hora de inicio', 'adjuntos' => 'adjuntos',
            'adjuntos.*.descripcion' => 'descripción del adjunto',
        ];
    }

    private function mensajeDocumental(string $codigo): string
    {
        return match ($codigo) {
            'cantidad_excedida' => 'La cantidad de adjuntos supera el máximo permitido.',
            'tamanio_total_excedido' => 'El tamaño total de los adjuntos supera el máximo permitido.',
            'tamanio_individual_excedido' => 'El adjunto supera el tamaño máximo permitido.',
            'tipo_invalido', 'upload_invalido' => 'El adjunto debe ser un archivo cargado correctamente.',
            'tamanio_ilegible' => 'No se pudo determinar el tamaño del adjunto.',
            'mime_no_permitido', 'mime_ilegible' => 'El contenido del adjunto debe ser PDF, JPEG o PNG.',
            default => 'El adjunto no cumple la política documental.',
        };
    }
}
