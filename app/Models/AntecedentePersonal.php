<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AntecedentePersonal extends Model
{
    protected $table = 'antecedente_personal';
    protected $primaryKey = 'antecedente_personal_id';
    public $incrementing = true;
    protected $keyType = 'int';
    protected $touches = ['entradaHc'];

    protected $fillable = [
        'entrada_hc_id',
        'tipo_id',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    public function entradaHc(): BelongsTo
    {
        return $this->belongsTo(EntradaHc::class, 'entrada_hc_id', 'entrada_hc_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(AntecedentePersonalTipo::class, 'tipo_id', 'antecedente_personal_tipo_id');
    }
}
