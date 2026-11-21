<?php

namespace App\Services\HistoriaClinica;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as Validador;

final class ValidarDatosAperturaHistoriaClinica
{
    public const TIPOS = [
        'personales' => 'Antecedentes personales', 'familiares' => 'Antecedentes familiares',
        'cirugias' => 'Cirugías', 'alergias' => 'Alergias', 'medicacion' => 'Medicación habitual',
    ];

    public const RELEVAMIENTOS = [
        'INFORMADO' => 'Presenta antecedentes', 'NO_PRESENTA' => 'No presenta',
        'DESCONOCIDO' => 'Se desconoce', 'NO_RELEVADO' => 'No se relevó',
    ];

    public const FAMILIA = [
        'INFORMADO' => 'Informado', 'SIN_INTEGRANTES' => 'Declara que no tiene integrantes',
        'INFORMACION_NO_DISPONIBLE' => 'Información no disponible', 'NO_RELEVADO' => 'No relevado',
    ];

    public const PROCEDENCIAS = [
        'REFERIDO_PACIENTE' => 'Referido por el paciente',
        'RELEVAMIENTO_PROFESIONAL' => 'Relevamiento profesional',
    ];

    public function validar(array $entrada): array
    {
        return $this->validarDatos($entrada, true);
    }

    /** Valida un resultado propuesto; no representa confirmación ni escritura. */
    public function validarResultadoProfesional(array $entrada): array
    {
        return $this->validarDatos($entrada, false);
    }

    /** Reutiliza las reglas del bloque sin fabricar respuestas para los demás. */
    public function validarBloque(string $tipo, array $entrada): array
    {
        if (! array_key_exists($tipo, self::TIPOS)) {
            throw new \InvalidArgumentException('Tipo de antecedente no reconocido.');
        }
        $datos = $this->normalizar(['bloques' => [$tipo => $entrada]]);
        $prefijo = 'bloques.'.$tipo;
        $validador = Validator::make($datos, $this->reglasBloque($prefijo));
        $validador->after(fn (Validador $v) => $this->coherenciaBloque($v, $datos['bloques'][$tipo], $tipo));

        return $validador->validate()['bloques'][$tipo];
    }

    private function validarDatos(array $entrada, bool $confirmacionRequerida): array
    {
        $datos = $this->normalizar($entrada);
        $reglas = [
            'especialidad_id' => ['required', 'integer', 'min:1'],
            'procedencia_relevamiento' => ['required', Rule::in(array_keys(self::PROCEDENCIAS))],
            'bloques' => ['required', 'array:'.implode(',', array_keys(self::TIPOS))],
            'familia' => ['required', 'array:resultado,motivo,integrantes,incluir_contacto,vinculo_contacto'],
            'familia.resultado' => ['required', Rule::in(array_keys(self::FAMILIA))],
            'familia.motivo' => ['nullable', 'string', 'max:2000'],
            'familia.integrantes' => ['present', 'array', 'max:30'],
            'familia.integrantes.*' => ['array:nombre_apellido,vinculo'],
            'familia.integrantes.*.nombre_apellido' => ['nullable', 'string', 'max:255'],
            'familia.integrantes.*.vinculo' => ['required', 'string', 'max:100'],
            'familia.incluir_contacto' => ['required', 'boolean'],
            'familia.vinculo_contacto' => ['nullable', 'string', 'max:100'],
            'objetivo_salud' => ['nullable', 'string', 'max:5000'],
            'peso_kg' => ['nullable', 'numeric', 'decimal:0,2', 'between:1,500'],
            'altura_cm' => ['nullable', 'numeric', 'decimal:0,2', 'between:30,250'],
            'ocupacion' => ['nullable', 'string', 'max:255'],
            'nivel_actividad_fisica' => ['nullable', Rule::in(['SEDENTARIO', 'MODERADO', 'ACTIVO'])],
            'detalle_actividad_fisica' => ['nullable', 'string', 'max:5000'],
        ];
        if ($confirmacionRequerida) {
            $reglas['confirmar'] = ['accepted'];
        }
        foreach (self::TIPOS as $tipo => $nombre) {
            $reglas += $this->reglasBloque('bloques.'.$tipo);
        }
        $validador = Validator::make($datos, $reglas);
        $validador->after(function (Validador $v) use ($datos): void {
            foreach (self::TIPOS as $tipo => $nombre) {
                $this->coherenciaBloque($v, $datos['bloques'][$tipo] ?? [], $tipo);
            }
            $familia = $datos['familia'] ?? [];
            $resultado = $familia['resultado'] ?? null;
            $tieneIntegrantes = ! empty($familia['integrantes']) || ! empty($familia['incluir_contacto']);
            if ($resultado === 'NO_RELEVADO') {
                $v->errors()->add('familia.resultado', 'Completá el relevamiento del núcleo familiar.');
            }
            if (($resultado === 'INFORMADO' && ! $tieneIntegrantes) || ($resultado !== 'INFORMADO' && $tieneIntegrantes)) {
                $v->errors()->add('familia.integrantes', 'Los integrantes deben corresponder al resultado del relevamiento familiar.');
            }
            if (! empty($familia['incluir_contacto']) && empty($familia['vinculo_contacto'])) {
                $v->errors()->add('familia.vinculo_contacto', 'Indicá el vínculo del contacto confirmado como integrante.');
            }
        });

        return $validador->validate();
    }

    private function reglasBloque(string $prefijo): array
    {
        return [
            $prefijo => ['required', 'array:resultado,items'],
            $prefijo.'.resultado' => ['required', Rule::in(array_keys(self::RELEVAMIENTOS))],
            $prefijo.'.items' => ['present', 'array', 'max:30'],
            $prefijo.'.items.*' => ['array:descripcion,procedencia,estado_codificacion'],
            $prefijo.'.items.*.descripcion' => ['required', 'string', 'max:5000'],
            $prefijo.'.items.*.procedencia' => ['required', Rule::in(array_keys(self::PROCEDENCIAS))],
            // Los cinco tipos admiten diferir. No se inventa una nomenclatura ni una regla descriptiva.
            $prefijo.'.items.*.estado_codificacion' => ['required', Rule::in(['PENDIENTE_CODIFICACION'])],
        ];
    }

    private function coherenciaBloque(Validador $v, mixed $bloque, string $tipo): void
    {
        if (! is_array($bloque)) {
            return;
        }
        $resultado = $bloque['resultado'] ?? null;
        $items = $bloque['items'] ?? [];
        if ($resultado === 'NO_RELEVADO') {
            $v->errors()->add('bloques.'.$tipo.'.resultado', self::TIPOS[$tipo].': completá el relevamiento antes de abrir la historia.');
        }
        if (($resultado === 'INFORMADO' && $items === []) || ($resultado !== 'INFORMADO' && $items !== [])) {
            $v->errors()->add('bloques.'.$tipo.'.items', self::TIPOS[$tipo].': los antecedentes deben corresponder al resultado informado.');
        }
    }

    private function normalizar(array $entrada): array
    {
        $limpiar = function ($valor) use (&$limpiar) {
            if (is_array($valor)) {
                return array_map($limpiar, $valor);
            }

            return is_string($valor) ? (trim($valor) === '' ? null : trim($valor)) : $valor;
        };
        $datos = $limpiar($entrada);
        foreach (self::TIPOS as $tipo => $nombre) {
            if (isset($datos['bloques'][$tipo]) && is_array($datos['bloques'][$tipo])) {
                $items = $datos['bloques'][$tipo]['items'] ?? [];
                if (is_array($items)) {
                    $datos['bloques'][$tipo]['items'] = array_values(array_filter($items, fn ($item) => ! is_array($item) || ! empty($item['descripcion'])));
                }
            }
        }
        if (isset($datos['familia']) && is_array($datos['familia'])) {
            $datos['familia']['incluir_contacto'] ??= false;
            $datos['familia']['integrantes'] ??= [];
            if (is_array($datos['familia']['integrantes'])) {
                $datos['familia']['integrantes'] = array_values(array_filter($datos['familia']['integrantes'], fn ($i) => ! is_array($i) || ! empty($i['nombre_apellido']) || ! empty($i['vinculo'])));
            }
        }
        // Orden y tipos estables para comparar el doble envío, no el orden JSON del cliente.
        $ordenar = function (&$valor) use (&$ordenar): void {
            if (is_array($valor)) {
                if (! array_is_list($valor)) {
                    ksort($valor);
                }
                foreach ($valor as &$hijo) {
                    $ordenar($hijo);
                }
            }
        };
        $ordenar($datos);

        return $datos;
    }
}
