<?php

namespace App\Data\HistoriaClinica;

use App\Models\DeclaracionSaludPaciente;
use App\Models\ItemDeclaracionSalud;
use LogicException;

final readonly class FuenteDeclaracionParaRevision
{
    public const CAMPOS = ['objetivo_salud', 'peso_kg', 'altura_cm', 'ocupacion', 'nivel_actividad_fisica', 'detalle_actividad_fisica'];

    public const LISTAS = ['antecedentes' => ItemDeclaracionSalud::TIPO_ANTECEDENTE, 'cirugias' => ItemDeclaracionSalud::TIPO_CIRUGIA,
        'alergias' => ItemDeclaracionSalud::TIPO_ALERGIA, 'medicacion' => ItemDeclaracionSalud::TIPO_MEDICACION];

    private function __construct(public int $declaracionId, public int $pacienteId, public array $bloques, public string $huella) {}

    /** El llamador aporta la declaración autorizada y sus ítems releídos. No hace SQL. */
    public static function desde(DeclaracionSaludPaciente $declaracion): self
    {
        return self::capturar($declaracion, DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION);
    }

    /** Solo para comprobar el original al recuperar una incorporación ya confirmada. */
    public static function desdeRevisada(DeclaracionSaludPaciente $declaracion): self
    {
        return self::capturar($declaracion, DeclaracionSaludPaciente::ESTADO_REVISADA);
    }

    private static function capturar(DeclaracionSaludPaciente $declaracion, string $estado): self
    {
        if (! $declaracion->exists || ! $declaracion->relationLoaded('items')
            || $declaracion->estado !== $estado) {
            throw new LogicException('Se requiere una declaración persistida en el estado esperado, con sus ítems cargados.');
        }
        $bloques = [];
        foreach (self::LISTAS as $bloque => $tipo) {
            $bloques[$bloque] = ['items' => $declaracion->items->where('tipo', $tipo)->sortBy('id')->map(fn ($item) => [
                'item_origen_id' => (int) $item->id, 'descripcion' => $item->getRawOriginal('descripcion'),
            ])->values()->all()];
            $respuesta = $declaracion->getRawOriginal($bloque.'_respuesta');
            // No agregar una propiedad null: preserva la representación y huella históricas.
            if ($respuesta !== null) {
                $bloques[$bloque]['respuesta'] = $respuesta;
            }
        }
        foreach ($declaracion->items as $item) {
            if (! in_array($item->tipo, self::LISTAS, true) || (int) $item->declaracion_salud_id !== (int) $declaracion->id) {
                throw new LogicException('Los ítems no corresponden al contrato o a la declaración seleccionada.');
            }
        }
        foreach (self::CAMPOS as $campo) {
            $bloques[$campo] = ['valor' => $declaracion->getRawOriginal($campo)];
        }
        $huella = self::huellaDe(['declaracion_id' => $declaracion->id, 'paciente_id' => $declaracion->paciente_id,
            'created_at' => $declaracion->getRawOriginal('created_at'), 'bloques' => $bloques]);

        return new self((int) $declaracion->id, (int) $declaracion->paciente_id, $bloques, $huella);
    }

    public static function huellaDe(array $datos): string
    {
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

        return hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
