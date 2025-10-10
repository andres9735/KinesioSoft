<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ejercicio extends Model
{
    use SoftDeletes;

    protected $table = 'ejercicio';
    protected $primaryKey = 'id_ejercicio';

    protected $fillable = [
        'nombre',
        'descripcion',
        'nivel_dificultad_base',
        'url_recurso',
        'activo',
    ];

    // 🔗 Relación muchos a muchos con Categoría
    public function categorias()
    {
        return $this->belongsToMany(
            CategoriaEjercicio::class,
            'ejercicio_categoria',
            'id_ejercicio',
            'id_categoria_ejercicio'
        );
    }
}
