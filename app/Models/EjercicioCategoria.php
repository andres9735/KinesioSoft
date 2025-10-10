<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EjercicioCategoria extends Model
{
    protected $table = 'ejercicio_categoria';
    protected $primaryKey = 'id_ejercicio_categoria';
    public $timestamps = true;

    protected $fillable = [
        'id_ejercicio',
        'id_categoria_ejercicio',
    ];
}
