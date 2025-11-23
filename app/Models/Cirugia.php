<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cirugia extends Model
{
    protected $table = 'cirugia';
    protected $primaryKey = 'cirugia_id';
    public $incrementing = true;
    protected $keyType = 'int';

    // Al tocar una cirugía, también "tocar" la entrada para refrescar timelines
    protected $touches = ['entradaHc'];

    protected $fillable = [
        'entrada_hc_id',
        'procedimiento',
        'fecha',
        'lateralidad',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    // Constantes para evitar typos
    public const L_IZQUIERDA  = 'izquierda';
    public const L_DERECHA    = 'derecha';
    public const L_BILATERAL  = 'bilateral';
    public const L_NO_APLICA  = 'no_aplica';
    public const L_DESCONOCIDA = 'desconocida';

    public function entradaHc(): BelongsTo
    {
        return $this->belongsTo(EntradaHc::class, 'entrada_hc_id', 'entrada_hc_id');
    }

    /** Título útil para grillas/listas */
    public function getTituloAttribute(): string
    {
        $lado = $this->lateralidad ? ' · ' . str_replace('_', ' ', $this->lateralidad) : '';
        $f    = $this->fecha ? ' (' . $this->fecha->format('d/m/Y') . ')' : '';
        return trim(($this->procedimiento ?? 'Cirugía') . $lado . $f);
    }
}
