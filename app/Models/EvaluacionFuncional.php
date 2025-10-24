<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluacionFuncional extends Model
{
    use HasFactory;

    protected $table = 'evaluacion_funcional';
    protected $primaryKey = 'id_eval_func';
    public $incrementing = true;
    protected $keyType = 'int';

    // (opcional) para asignación masiva cuando la uses desde la consulta
    protected $fillable = ['id_consulta', 'fecha', 'eva_dolor', 'resumen_postural', 'texto'];

    /**
     * 1 EvaluacionFuncional -> N EvaluacionRom
     */
    public function roms()
    {
        return $this->hasMany(EvaluacionRom::class, 'id_eval_func', 'id_eval_func');
    }

    /**
     * (opcional) cuando tengas la tabla consulta
     */
    // public function consulta()
    // {
    //     return $this->belongsTo(Consulta::class, 'id_consulta', 'id_consulta');
    // }
}
