<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\EvaluacionRom;

class MetodoRom extends Model
{
    use HasFactory;

    protected $table = 'metodo_rom';
    protected $primaryKey = 'id_metodo';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nombre',
        'slug',
        'codigo',
        'tipo',
        'precision_decimales',
        'unidad_defecto',
        'activo',
    ];

    public function roms()
    {
        return $this->hasMany(EvaluacionRom::class, 'id_metodo', 'id_metodo');
    }

    protected $casts = [
        'activo' => 'boolean',
    ];
}
