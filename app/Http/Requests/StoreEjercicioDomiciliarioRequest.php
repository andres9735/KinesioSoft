<?php

namespace App\Http\Requests;

use App\Enums\DificultadEjercicioEnum;
use App\Enums\FrecuenciaPeriodoEjercicioEnum;
use App\Enums\OrigenRecursoEjercicioEnum;
use App\Enums\TipoCategoriaClinicaEnum;
use App\Enums\TipoRecursoEjercicioEnum;
use App\Models\CategoriaClinica;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEjercicioDomiciliarioRequest extends FormRequest
{
    private const UNSIGNED_INTEGER_MAX = 4294967295;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalizados = [];

        foreach ([
            'codigo',
            'nombre',
            'descripcion',
            'frecuencia_periodo_base',
            'carga_resistencia_sugerida',
            'dificultad_base',
            'contraindicaciones',
            'notas_clinicas',
        ] as $campo) {
            if (is_string($this->input($campo))) {
                $normalizados[$campo] = trim($this->input($campo));
            }
        }

        $this->merge($normalizados);
    }

    public function rules(): array
    {
        return [
            'codigo' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ejercicios_domiciliarios', 'codigo'),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string'],
            'series_recomendadas' => ['nullable', 'integer', 'min:1', 'max:'.self::UNSIGNED_INTEGER_MAX],
            'repeticiones_recomendadas' => ['nullable', 'integer', 'min:1', 'max:'.self::UNSIGNED_INTEGER_MAX],
            'duracion_segundos_recomendada' => ['nullable', 'integer', 'min:1', 'max:'.self::UNSIGNED_INTEGER_MAX],
            'frecuencia_cantidad_base' => [
                'nullable',
                'required_with:frecuencia_periodo_base',
                'integer',
                'min:1',
                'max:'.self::UNSIGNED_INTEGER_MAX,
            ],
            'frecuencia_periodo_base' => [
                'nullable',
                'required_with:frecuencia_cantidad_base',
                Rule::enum(FrecuenciaPeriodoEjercicioEnum::class),
            ],
            'descanso_segundos_base' => ['nullable', 'integer', 'min:0', 'max:'.self::UNSIGNED_INTEGER_MAX],
            'carga_resistencia_sugerida' => ['nullable', 'string', 'max:255'],
            'dificultad_base' => ['required', Rule::enum(DificultadEjercicioEnum::class)],
            'contraindicaciones' => ['nullable', 'string'],
            'notas_clinicas' => ['nullable', 'string'],
            'activo' => ['prohibited'],
            'grupo_muscular_zona' => ['prohibited'],
            'categorias' => ['required', 'array', 'min:1'],
            'categorias.*' => ['required', 'integer', 'distinct'],
            'recursos' => ['nullable', 'array'],
            'recursos.*' => ['array'],
            'recursos.*.id' => ['prohibited'],
            'recursos.*.tipo' => ['required', Rule::enum(TipoRecursoEjercicioEnum::class)],
            'recursos.*.origen' => ['required', Rule::enum(OrigenRecursoEjercicioEnum::class)],
            'recursos.*.titulo' => ['required', 'string', 'max:255'],
            'recursos.*.orden' => ['required', 'integer', 'min:0', 'max:'.self::UNSIGNED_INTEGER_MAX],
            'recursos.*.activo' => ['required', 'boolean'],
            'recursos.*.archivo' => ['nullable', 'file'],
            'recursos.*.url_externa' => ['nullable', 'url', 'max:255'],
            'recursos.*.ruta_storage' => ['prohibited'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validarCategorias($validator);
                $this->validarRecursos($validator);
            },
        ];
    }

    private function validarCategorias(Validator $validator): void
    {
        if ($this->hayErroresConPrefijo($validator, 'categorias')) {
            return;
        }

        $ids = collect($this->input('categorias', []))
            ->map(fn ($id): int => (int) $id)
            ->values();
        $categorias = CategoriaClinica::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
        $tieneAnatomicaActiva = false;

        foreach ($ids as $indice => $id) {
            $categoria = $categorias->get($id);

            if (! $categoria instanceof CategoriaClinica) {
                $validator->errors()->add("categorias.$indice", 'La categoría seleccionada no existe.');

                continue;
            }

            if (! $categoria->activo) {
                $validator->errors()->add("categorias.$indice", 'La categoría seleccionada debe estar activa.');
            }

            if ($categoria->tipo === TipoCategoriaClinicaEnum::EQUIPAMIENTO) {
                $validator->errors()->add("categorias.$indice", 'No se permiten categorías de EQUIPAMIENTO.');
            }

            if ($categoria->activo && $categoria->tipo === TipoCategoriaClinicaEnum::ANATOMICA) {
                $tieneAnatomicaActiva = true;
            }
        }

        if (! $tieneAnatomicaActiva) {
            $validator->errors()->add('categorias', 'El ejercicio activo requiere al menos una categoría ANATOMICA activa.');
        }
    }

    private function validarRecursos(Validator $validator): void
    {
        foreach ($this->input('recursos', []) ?? [] as $indice => $recurso) {
            if (! is_array($recurso) || $validator->errors()->has("recursos.$indice.origen")) {
                continue;
            }

            $origen = $recurso['origen'] ?? null;
            $tieneArchivo = $this->hasFile("recursos.$indice.archivo");
            $urlExterna = $recurso['url_externa'] ?? null;

            if ($origen === OrigenRecursoEjercicioEnum::LOCAL->value) {
                if (! $tieneArchivo) {
                    $validator->errors()->add("recursos.$indice.archivo", 'El archivo es obligatorio para un recurso LOCAL nuevo.');
                }

                if ($urlExterna !== null && $urlExterna !== '') {
                    $validator->errors()->add("recursos.$indice.url_externa", 'Un recurso LOCAL no puede incluir una URL externa.');
                }
            }

            if ($origen === OrigenRecursoEjercicioEnum::EXTERNO->value) {
                if ($urlExterna === null || $urlExterna === '') {
                    $validator->errors()->add("recursos.$indice.url_externa", 'La URL externa es obligatoria para un recurso EXTERNO nuevo.');
                }

                if ($tieneArchivo) {
                    $validator->errors()->add("recursos.$indice.archivo", 'Un recurso EXTERNO no puede incluir un archivo.');
                }
            }
        }
    }

    private function hayErroresConPrefijo(Validator $validator, string $prefijo): bool
    {
        foreach ($validator->errors()->keys() as $campo) {
            if ($campo === $prefijo || str_starts_with($campo, $prefijo.'.')) {
                return true;
            }
        }

        return false;
    }
}
