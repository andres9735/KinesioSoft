<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\EvaluacionRom;     
use App\Models\ZonaAnatomica;

class Movimiento extends Model
{
    use HasFactory;

    protected $table = 'movimiento';
    protected $primaryKey = 'id_movimiento';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_zona_anatomica',
        'nombre',
        'slug',
        'codigo',
        'plano',
        'tipo_movimiento',
        'rango_norm_min',
        'rango_norm_max',
        'activo',
    ];

    // Relaciones
    public function zona()
    {
        return $this->belongsTo(ZonaAnatomica::class, 'id_zona_anatomica', 'id_zona_anatomica');
    }

    public function roms()
    {
        return $this->hasMany(EvaluacionRom::class, 'id_movimiento', 'id_movimiento');
    }

    protected $casts = [
        'activo' => 'boolean',
    ];
}
