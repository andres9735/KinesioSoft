<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoriaEjercicio extends Model
{
    use SoftDeletes;

    protected $table = 'categoria_ejercicio';
    protected $primaryKey = 'id_categoria_ejercicio';

    protected $fillable = [
        'tipo',
        'codigo',
        'nombre',
        'descripcion',
        'activo',
    ];

    // 🔗 Relación muchos a muchos con Ejercicio
    public function ejercicios()
    {
        return $this->belongsToMany(
            Ejercicio::class,
            'ejercicio_categoria',
            'id_categoria_ejercicio',
            'id_ejercicio'
        );
    }
}
