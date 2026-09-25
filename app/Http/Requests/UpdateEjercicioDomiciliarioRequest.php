<?php

namespace App\Http\Requests;

use App\Enums\DificultadEjercicioEnum;
use App\Enums\FrecuenciaPeriodoEjercicioEnum;
use App\Enums\OrigenRecursoEjercicioEnum;
use App\Enums\TipoCategoriaClinicaEnum;
use App\Enums\TipoRecursoEjercicioEnum;
use App\Models\CategoriaClinica;
use App\Models\EjercicioDomiciliario;
use App\Models\RecursoEjercicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEjercicioDomiciliarioRequest extends FormRequest
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
        $ejercicio = $this->route('ejercicioDomiciliario');

        return [
            'codigo' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ejercicios_domiciliarios', 'codigo')->ignore(
                    $ejercicio instanceof EjercicioDomiciliario ? $ejercicio->getKey() : null
                ),
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
            'categorias' => ['present', 'array'],
            'categorias.*' => ['required', 'integer', 'distinct'],
            'recursos' => ['nullable', 'array'],
            'recursos.*' => ['array'],
            'recursos.*.id' => ['nullable', 'integer'],
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
                $ejercicio = $this->route('ejercicioDomiciliario');

                if (! $ejercicio instanceof EjercicioDomiciliario) {
                    return;
                }

                $this->validarCategorias($validator, $ejercicio);
                $this->validarRecursos($validator, $ejercicio);
            },
        ];
    }

    private function validarCategorias(Validator $validator, EjercicioDomiciliario $ejercicio): void
    {
        if ($this->hayErroresConPrefijo($validator, 'categorias')) {
            return;
        }

        $idsSolicitados = collect($this->input('categorias', []))
            ->map(fn ($id): int => (int) $id)
            ->values();
        $idsActuales = $ejercicio->categorias()
            ->pluck('categorias_clinicas.id')
            ->map(fn ($id): int => (int) $id);
        $categorias = CategoriaClinica::query()
            ->whereIn('id', $idsSolicitados)
            ->get()
            ->keyBy('id');

        foreach ($idsSolicitados as $indice => $id) {
            $categoria = $categorias->get($id);

            if (! $categoria instanceof CategoriaClinica) {
                $validator->errors()->add("categorias.$indice", 'La categoría seleccionada no existe.');

                continue;
            }

            if (! $idsActuales->contains($id)
                && (! $categoria->activo || $categoria->tipo === TipoCategoriaClinicaEnum::EQUIPAMIENTO)) {
                $validator->errors()->add(
                    "categorias.$indice",
                    'Una asociación nueva requiere una categoría activa que no sea de EQUIPAMIENTO.'
                );
            }
        }

        if (! $ejercicio->activo) {
            return;
        }

        $tieneEquipamiento = $idsSolicitados->contains(
            fn (int $id): bool => $categorias->get($id)?->tipo === TipoCategoriaClinicaEnum::EQUIPAMIENTO
        );
        $tieneAnatomicaActiva = $idsSolicitados->contains(
            fn (int $id): bool => ($categoria = $categorias->get($id)) instanceof CategoriaClinica
                && $categoria->activo
                && $categoria->tipo === TipoCategoriaClinicaEnum::ANATOMICA
        );

        if ($tieneEquipamiento) {
            $validator->errors()->add('categorias', 'Un ejercicio activo no puede conservar categorías de EQUIPAMIENTO.');
        }

        if (! $tieneAnatomicaActiva) {
            $validator->errors()->add('categorias', 'El ejercicio activo requiere al menos una categoría ANATOMICA activa.');
        }
    }

    private function validarRecursos(Validator $validator, EjercicioDomiciliario $ejercicio): void
    {
        $recursosSolicitados = $this->input('recursos', []) ?? [];

        if (! is_array($recursosSolicitados)) {
            return;
        }

        $ids = collect($recursosSolicitados)
            ->pluck('id')
            ->filter(fn ($id): bool => $id !== null && $id !== '')
            ->map(fn ($id): int => (int) $id)
            ->values();
        $recursosPersistidos = RecursoEjercicio::query()
            ->whereIn('id', $ids->unique())
            ->get()
            ->keyBy('id');
        $indicesPorId = [];

        foreach ($recursosSolicitados as $indice => $recurso) {
            if (! is_array($recurso)) {
                continue;
            }

            $id = $recurso['id'] ?? null;
            $persistido = null;

            if ($id !== null && $id !== '' && ! $validator->errors()->has("recursos.$indice.id")) {
                $id = (int) $id;

                if (array_key_exists($id, $indicesPorId)) {
                    $validator->errors()->add("recursos.$indice.id", 'El mismo recurso no puede repetirse en el payload.');
                } else {
                    $indicesPorId[$id] = $indice;
                }

                $candidato = $recursosPersistidos->get($id);

                if (! $candidato instanceof RecursoEjercicio || (int) $candidato->ejercicio_id !== (int) $ejercicio->getKey()) {
                    $validator->errors()->add("recursos.$indice.id", 'El recurso no pertenece al ejercicio editado.');
                } else {
                    $persistido = $candidato;
                }
            }

            if ($validator->errors()->has("recursos.$indice.origen")) {
                continue;
            }

            $origen = $recurso['origen'] ?? null;
            $tieneArchivo = $this->hasFile("recursos.$indice.archivo");
            $urlExterna = $recurso['url_externa'] ?? null;
            $esNuevo = $id === null || $id === '';

            if ($origen === OrigenRecursoEjercicioEnum::LOCAL->value) {
                if ($urlExterna !== null && $urlExterna !== '') {
                    $validator->errors()->add("recursos.$indice.url_externa", 'Un recurso LOCAL no puede incluir una URL externa.');
                }

                $cambiaDesdeExterno = $persistido instanceof RecursoEjercicio
                    && $persistido->origen === OrigenRecursoEjercicioEnum::EXTERNO;

                if (($esNuevo || $cambiaDesdeExterno) && ! $tieneArchivo) {
                    $validator->errors()->add("recursos.$indice.archivo", 'Se requiere un archivo nuevo para usar origen LOCAL.');
                }
            }

            if ($origen === OrigenRecursoEjercicioEnum::EXTERNO->value) {
                if ($tieneArchivo) {
                    $validator->errors()->add("recursos.$indice.archivo", 'Un recurso EXTERNO no puede incluir un archivo.');
                }

                $cambiaDesdeLocal = $persistido instanceof RecursoEjercicio
                    && $persistido->origen === OrigenRecursoEjercicioEnum::LOCAL;

                if (($esNuevo || $cambiaDesdeLocal) && ($urlExterna === null || $urlExterna === '')) {
                    $validator->errors()->add("recursos.$indice.url_externa", 'Se requiere una URL nueva para usar origen EXTERNO.');
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
