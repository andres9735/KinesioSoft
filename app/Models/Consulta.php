<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Consulta extends Model
{
    protected $table = 'consultas';
    protected $primaryKey = 'id_consulta';

    // Tipos de consulta
    public const TIPO_INICIAL = 'inicial';

    // Estados (wizard)
    public const ESTADO_BORRADOR   = 'borrador';
    public const ESTADO_FINALIZADA = 'finalizada';

    protected $fillable = [
        'turno_id',
        'paciente_id',
        'kinesiologa_id',
        'fecha',
        'tipo',
        'estado',
        'paso_actual',
        'resumen',
    ];

    protected $casts = [
        'paso_actual' => 'integer',
        'fecha'       => 'date',
    ];

    /** =========================================================
     * Relaciones
     * ========================================================= */

    public function turno()
    {
        return $this->belongsTo(\App\Models\Turno::class, 'turno_id', 'id_turno');
    }

    public function paciente()
    {
        return $this->belongsTo(\App\Models\User::class, 'paciente_id', 'id');
    }

    public function kinesiologa()
    {
        return $this->belongsTo(\App\Models\User::class, 'kinesiologa_id', 'id');
    }

    public function evaluacionFuncional(): HasOne
    {
        return $this->hasOne(\App\Models\EvaluacionFuncional::class, 'id_consulta', 'id_consulta');
    }
}
