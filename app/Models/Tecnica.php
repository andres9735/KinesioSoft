<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tecnica extends Model
{
    use SoftDeletes;

    protected $table = 'tecnica';
    protected $primaryKey = 'id_tecnica';

    protected $fillable = [
        'id_tecnica_tipo',
        'codigo',
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tipo()
    {
        return $this->belongsTo(TecnicaTipo::class, 'id_tecnica_tipo', 'id_tecnica_tipo');
    }
}
