<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluacionRom extends Model
{
    protected $table = 'evaluacion_rom';
    protected $primaryKey = 'id_eval_rom';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_eval_func',
        'id_movimiento',
        'id_metodo',
        'lado',
        'valor_grados',
        'observaciones',
    ];

    public function movimiento()
    {
        return $this->belongsTo(Movimiento::class, 'id_movimiento', 'id_movimiento');
    }

    public function metodo()
    {
        return $this->belongsTo(MetodoRom::class, 'id_metodo', 'id_metodo');
    }

    public function evaluacionFuncional()
    {
        return $this->belongsTo(EvaluacionFuncional::class, 'id_eval_func', 'id_eval_func');
    }
}

