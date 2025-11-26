<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstudioImagen extends Model
{
    protected $table = 'estudio_imagen';
    protected $primaryKey = 'estudio_img_id';

    protected $fillable = [
        'entrada_hc_id',
        'tipo',
        'fecha',
        'archivo_path',
        'archivo_disk',
        'archivo_url',
        'informe',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function entradaHc(): BelongsTo
    {
        return $this->belongsTo(EntradaHc::class, 'entrada_hc_id', 'entrada_hc_id');
    }
}
