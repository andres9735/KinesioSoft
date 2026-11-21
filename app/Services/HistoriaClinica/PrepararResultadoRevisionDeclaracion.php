<?php

namespace App\Services\HistoriaClinica;

use App\Data\HistoriaClinica\FuenteDeclaracionParaRevision as Fuente;
use App\Data\HistoriaClinica\ResultadoProfesionalRevision;
use App\Enums\BloqueRevisionDeclaracion;
use App\Enums\DictamenRevisionDeclaracion as Dictamen;
use App\Models\VersionDatosClinicosBase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

final class PrepararResultadoRevisionDeclaracion
{
    public function __construct(private readonly ValidarDatosAperturaHistoriaClinica $minimo) {}

    /** No hace SQL ni escrituras: recibe fuentes ya autorizadas y cargadas. */
    public function preparar(Fuente $fuente, array $entrada, ?VersionDatosClinicosBase $base = null): ResultadoProfesionalRevision
    {
        $bloques = array_column(BloqueRevisionDeclaracion::cases(), 'value');
        $destinos = array_keys(ValidarDatosAperturaHistoriaClinica::TIPOS);
        $reglas = [
            'especialidad_id' => ['required', 'integer', 'min:1'],
            'procedencia_relevamiento' => ['required', Rule::in(array_keys(ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS))],
            'dictamenes' => ['required', 'array:'.implode(',', $bloques)],
            'complemento_profesional' => ['sometimes', 'array:bloques,familia'],
            'complemento_profesional.bloques' => ['sometimes', 'array:'.implode(',', $destinos)],
            'complemento_profesional.bloques.*' => ['array:resultado,items'],
            'complemento_profesional.familia' => ['sometimes', 'array'],
            'sustituciones_base' => ['sometimes', 'array', 'max:12'],
            'sustituciones_base.*' => ['string', 'distinct', Rule::in([...$destinos, ...Fuente::CAMPOS, 'familia'])],
        ];
        foreach ($bloques as $bloque) {
            $reglas['dictamenes.'.$bloque] = ['required', 'array:dictamen,justificacion,correccion,clasificacion'];
            $reglas['dictamenes.'.$bloque.'.dictamen'] = ['required', Rule::enum(Dictamen::class)];
            $reglas['dictamenes.'.$bloque.'.justificacion'] = ['nullable', 'string', 'max:5000'];
            $reglas['dictamenes.'.$bloque.'.clasificacion'] = ['sometimes', 'array'];
            $reglas['dictamenes.'.$bloque.'.clasificacion.*'] = [Rule::in(['personales', 'familiares'])];
            $reglas['dictamenes.'.$bloque.'.correccion'] = ['sometimes', 'array'];
        }
        $entrada = Validator::make($entrada, $reglas)->validate();
        $sustituciones = $entrada['sustituciones_base'] ?? [];
        if ($sustituciones !== [] && ! $base) {
            $this->error('sustituciones_base', 'No hay una versión previa para sustituir.');
        }
        $datos = $this->datosBase($fuente, $base);
        $linaje = ['version_base_id' => $base?->id, 'sustituciones_explicitas' => $sustituciones,
            'items_base' => $base?->items->map->getAttributes()->all() ?? [],
            'integrantes_base' => $base?->integrantes->map->getAttributes()->all() ?? [], 'admitidos' => [],
            'complemento_profesional' => $entrada['complemento_profesional'] ?? []];
        foreach ($destinos as $tipo) {
            if (in_array($tipo, $sustituciones, true)) {
                $datos['bloques'][$tipo] = ['resultado' => 'NO_RELEVADO', 'items' => []];
            }
        }
        $dictamenes = [];
        foreach ($bloques as $bloque) {
            $decision = $entrada['dictamenes'][$bloque];
            $dictamen = Dictamen::from($decision['dictamen']);
            $nota = trim($decision['justificacion'] ?? '');
            if ($dictamen->requiereJustificacion() && $nota === '') {
                $this->error('dictamenes.'.$bloque.'.justificacion', 'Observar o descartar requiere una justificación clínica.');
            }
            $esCorreccion = $dictamen === Dictamen::CORREGIR;
            if (! empty($decision['clasificacion']) && ($bloque !== 'antecedentes' || $dictamen !== Dictamen::VALIDAR)) {
                $this->error('dictamenes.'.$bloque.'.clasificacion', 'La clasificación solo corresponde a antecedentes originales validados.');
            }
            if ($esCorreccion !== array_key_exists('correccion', $decision)) {
                $this->error('dictamenes.'.$bloque.'.correccion', 'Corregir exige el resultado corregido; los otros dictámenes conservan el original.');
            }
            $admitidos = [];
            if ($dictamen === Dictamen::VALIDAR || $esCorreccion) {
                if (array_key_exists($bloque, Fuente::LISTAS)) {
                    $admitidos = $this->itemsAdmitidos($fuente, $bloque, $decision, $esCorreccion);
                    foreach ($admitidos as $item) {
                        $tipo = $item['tipo_destino'];
                        $clinico = ['descripcion' => trim($item['descripcion']), 'procedencia' => $item['procedencia'], 'estado_codificacion' => 'PENDIENTE_CODIFICACION'];
                        if (! in_array($clinico, $datos['bloques'][$tipo]['items'], true)) {
                            $datos['bloques'][$tipo]['items'][] = $clinico;
                        }
                        $datos['bloques'][$tipo]['resultado'] = 'INFORMADO';
                    }
                } else {
                    $valor = $esCorreccion ? ($decision['correccion']['valor'] ?? null) : $fuente->bloques[$bloque]['valor'];
                    if ($esCorreccion && (array_keys($decision['correccion']) !== ['valor'])) {
                        $this->error('dictamenes.'.$bloque.'.correccion', 'La corrección de este bloque debe contener únicamente valor, incluso si es null.');
                    }
                    $previo = $datos[$bloque] ?? null;
                    if ($base && $previo !== null && ! $this->equivalentes($bloque, $previo, $valor) && ! in_array($bloque, $sustituciones, true)) {
                        $this->error('sustituciones_base', 'La sustitución de '.$bloque.' debe ser explícita para conservar la trazabilidad del dato previo.');
                    }
                    $datos[$bloque] = $valor;
                    $admitidos = ['valor' => $valor];
                }
            }
            $procedencias = ['declaracion_salud_id' => $fuente->declaracionId,
                'origen' => $esCorreccion ? 'CORRECCION_PROFESIONAL' : 'DECLARACION_PACIENTE', 'version_base_id' => $base?->id];
            $dictamenes[$bloque] = ['bloque' => $bloque, 'dictamen' => $dictamen->value,
                'observacion_profesional' => $nota === '' ? null : $nota, 'datos_originales' => $fuente->bloques[$bloque],
                'correccion_profesional' => $decision['correccion'] ?? null, 'datos_admitidos' => $admitidos, 'procedencias' => $procedencias];
            $linaje['admitidos'][$bloque] = $admitidos;
        }
        foreach ($entrada['complemento_profesional']['bloques'] ?? [] as $tipo => $bloque) {
            // El validador común verifica también los estados y la codificación de este aporte.
            $bloqueValidado = $this->minimo->validarBloque($tipo, $bloque);
            if ($bloqueValidado['resultado'] === 'INFORMADO') {
                foreach ($bloqueValidado['items'] as $item) {
                    if (! in_array($item, $datos['bloques'][$tipo]['items'], true)) {
                        $datos['bloques'][$tipo]['items'][] = $item;
                    }
                }
                $datos['bloques'][$tipo]['resultado'] = 'INFORMADO';
            } elseif ($datos['bloques'][$tipo]['items'] !== []) {
                $this->error('complemento_profesional.bloques.'.$tipo, 'No se pueden eliminar antecedentes admitidos o previos mediante una respuesta de ausencia.');
            } else {
                $datos['bloques'][$tipo] = $bloqueValidado;
            }
        }
        if (isset($entrada['complemento_profesional']['familia'])) {
            $familia = $entrada['complemento_profesional']['familia'];
            if ($base && $familia !== $datos['familia'] && ! in_array('familia', $sustituciones, true)) {
                $this->error('sustituciones_base', 'La modificación del núcleo familiar previo debe ser explícita.');
            }
            $datos['familia'] = $familia;
        }
        $datos['especialidad_id'] = $entrada['especialidad_id'];
        $datos['procedencia_relevamiento'] = $entrada['procedencia_relevamiento'];
        $validados = $this->minimo->validarResultadoProfesional($datos);
        $huella = Fuente::huellaDe(['declaracion' => $fuente->huella, 'datos' => $validados, 'dictamenes' => $dictamenes, 'linaje' => $linaje]);

        return new ResultadoProfesionalRevision($fuente->declaracionId, $fuente->pacienteId, $base?->id,
            $validados, $dictamenes, $linaje, $fuente->huella, $huella);
    }

    private function itemsAdmitidos(Fuente $fuente, string $bloque, array $decision, bool $corregir): array
    {
        $originales = $fuente->bloques[$bloque]['items'];
        if ($corregir) {
            if (array_keys($decision['correccion']) !== ['items']) {
                $this->error('dictamenes.'.$bloque.'.correccion', 'La corrección de una lista debe contener únicamente items.');
            }
            $correccion = Validator::make($decision['correccion'], [
                'items' => ['present', 'array', 'max:30'], 'items.*' => ['array:item_origen_id,tipo_destino,descripcion,procedencia'],
                'items.*.item_origen_id' => ['nullable', 'integer', Rule::in(array_column($originales, 'item_origen_id'))],
                'items.*.tipo_destino' => ['required', Rule::in($bloque === 'antecedentes' ? ['personales', 'familiares'] : [$bloque])],
                'items.*.descripcion' => ['required', 'string', 'max:5000'],
                'items.*.procedencia' => ['required', Rule::in(array_keys(ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS))],
            ])->validate();

            return $correccion['items'];
        }
        $clasificacion = $decision['clasificacion'] ?? [];
        if ($bloque === 'antecedentes' && array_diff(array_column($originales, 'item_origen_id'), array_keys($clasificacion)) !== []) {
            $this->error('dictamenes.antecedentes.clasificacion', 'Clasificá cada antecedente declarado como personal o familiar; no se infiere su tipo.');
        }
        if ($bloque === 'antecedentes' && array_diff(array_keys($clasificacion), array_column($originales, 'item_origen_id')) !== []) {
            $this->error('dictamenes.antecedentes.clasificacion', 'La clasificación referencia un ítem ajeno a la declaración.');
        }

        return array_map(fn ($item) => [...$item, 'tipo_destino' => $bloque === 'antecedentes' ? $clasificacion[$item['item_origen_id']] : $bloque,
            'procedencia' => 'REFERIDO_PACIENTE'], $originales);
    }

    private function datosBase(Fuente $fuente, ?VersionDatosClinicosBase $base): array
    {
        $datos = array_fill_keys(Fuente::CAMPOS, null);
        $datos['bloques'] = array_fill_keys(array_keys(ValidarDatosAperturaHistoriaClinica::TIPOS), ['resultado' => 'NO_RELEVADO', 'items' => []]);
        $datos['familia'] = ['resultado' => 'NO_RELEVADO', 'integrantes' => []];
        if (! $base) {
            return $datos;
        }
        foreach (['items', 'integrantes', 'historiaClinica'] as $relacion) {
            if (! $base->exists || ! $base->relationLoaded($relacion)) {
                throw new LogicException('La versión base debe estar persistida y cargada con sus relaciones.');
            }
        }
        if ((int) $base->historiaClinica->paciente_id !== $fuente->pacienteId || ! $base->historiaClinica->activa) {
            $this->error('version_base', 'La versión base debe pertenecer a la historia activa de este paciente.');
        }
        foreach ($base->items as $item) {
            if (! array_key_exists($item->tipo, ValidarDatosAperturaHistoriaClinica::TIPOS)
                || (int) $item->version_datos_clinicos_base_id !== (int) $base->id) {
                $this->error('version_base', 'La versión contiene ítems incompatibles; no se omiten datos clínicos previos.');
            }
        }
        foreach ($base->integrantes as $integrante) {
            if ((int) $integrante->version_datos_clinicos_base_id !== (int) $base->id) {
                $this->error('version_base', 'El núcleo familiar cargado pertenece a otra versión.');
            }
        }
        foreach (Fuente::CAMPOS as $campo) {
            $datos[$campo] = $base->$campo;
        }
        foreach ($datos['bloques'] as $tipo => &$bloque) {
            $bloque = ['resultado' => $base->relevamientos[$tipo] ?? 'NO_RELEVADO', 'items' => $base->items->where('tipo', $tipo)
                ->map(fn ($item) => $item->only(['descripcion', 'procedencia', 'estado_codificacion']))->values()->all()];
        }
        unset($bloque);
        $datos['familia'] = ['resultado' => $base->resultado_nucleo_familiar, 'motivo' => $base->motivo_nucleo_familiar,
            'integrantes' => $base->integrantes->map(fn ($i) => $i->only(['nombre_apellido', 'vinculo']))->all(), 'incluir_contacto' => false];

        return $datos;
    }

    private function equivalentes(string $campo, mixed $a, mixed $b): bool
    {
        return in_array($campo, ['peso_kg', 'altura_cm'], true) && is_numeric($a) && is_numeric($b)
            ? (string) (float) $a === (string) (float) $b : $a === $b;
    }

    private function error(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
