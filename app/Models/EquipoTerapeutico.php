<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipoTerapeutico extends Model
{
    use SoftDeletes;

    protected $table = 'equipo_terapeutico';
    protected $primaryKey = 'id_equipo_terap';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'codigo',
        'nombre',
        'marca_modelo',
        'descripcion',
        'estado',
        'id_consultorio',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /** Relaciones */
    public function consultorio()
    {
        return $this->belongsTo(Consultorio::class, 'id_consultorio', 'id_consultorio');
    }

    /** Scopes útiles */
    public function scopeActivos($q)
    {
        return $q->where('activo', true);
    }
    public function scopeOperativos($q)
    {
        return $q->where('estado', 'operativo');
    }
}
