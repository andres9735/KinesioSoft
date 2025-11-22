<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alergia extends Model
{
    protected $table = 'alergia';
    protected $primaryKey = 'alergia_id';
    public $incrementing = true;
    protected $keyType = 'int';

    // Al modificar, “tocar” la entrada para refrescar timelines
    protected $touches = ['entradaHc'];

    protected $fillable = [
        'entrada_hc_id',
        'sustancia',
        'reaccion',
        'gravedad',
        'observaciones',
    ];

    // Constantes para evitar typos
    public const G_LEVE        = 'leve';
    public const G_MODERADA    = 'moderada';
    public const G_SEVERA      = 'severa';
    public const G_ANAFILAXIA  = 'anafilaxia';
    public const G_DESCONOCIDA = 'desconocida';

    public function entradaHc(): BelongsTo
    {
        return $this->belongsTo(EntradaHc::class, 'entrada_hc_id', 'entrada_hc_id');
    }

    /** Título para grillas/listas */
    public function getTituloAttribute(): string
    {
        $g = $this->gravedad ? ' - ' . ucfirst($this->gravedad) : '';
        return trim(($this->sustancia ?? 'Alergia') . $g);
    }
}
