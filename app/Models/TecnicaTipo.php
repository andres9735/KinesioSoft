<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TecnicaTipo extends Model
{
    use SoftDeletes;

    protected $table = 'tecnica_tipo';
    protected $primaryKey = 'id_tecnica_tipo';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tecnicas()
    {
        return $this->hasMany(Tecnica::class, 'id_tecnica_tipo', 'id_tecnica_tipo');
    }
}
