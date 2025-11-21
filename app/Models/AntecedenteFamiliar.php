<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AntecedenteFamiliar extends Model
{
    protected $table = 'antecedente_familiar';
    protected $primaryKey = 'antecedente_familiar_id';
    public $incrementing = true;
    protected $keyType = 'int';

    // Al tocar este registro, que se “refresque” la entrada HC (útil para timelines)
    protected $touches = ['entradaHc'];

    protected $fillable = [
        'entrada_hc_id',
        'parentesco',
        'lado_familia',
        'observaciones',
    ];

    // Opcional: constantes para evitar typos en el enum
    public const LADO_MATERNO       = 'materno';
    public const LADO_PATERNO       = 'paterno';
    public const LADO_AMBOS         = 'ambos';
    public const LADO_DESCONOCIDO   = 'desconocido';
    public const LADO_NO_ESPECIFICA = 'no_especifica';

    /**
     * Entrada de HC a la que pertenece este antecedente.
     */
    public function entradaHc(): BelongsTo
    {
        return $this->belongsTo(EntradaHc::class, 'entrada_hc_id', 'entrada_hc_id');
    }

    /**
     * Título amigable para mostrar en tablas/listas.
     * Ej: "Madre (materno)"
     */
    public function getTituloAttribute(): string
    {
        $lado = $this->lado_familia ? ' (' . str_replace('_', ' ', $this->lado_familia) . ')' : '';
        return trim(($this->parentesco ?? 'Familiar') . $lado);
    }
}
