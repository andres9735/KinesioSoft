<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PadecimientoDiagnostico extends Model
{
    protected $table = 'padecimiento_diagnostico';
    protected $primaryKey = 'id_padecimiento_diagnostico';

    protected $fillable = [
        'id_padecimiento',
        'id_diagnostico_funcional',
        'vigente_desde',
        'vigente_hasta',
        'es_principal',
        'notas',
    ];

    protected $casts = [
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'es_principal'  => 'boolean',
    ];

    public function padecimiento()
    {
        return $this->belongsTo(PacientePadecimiento::class, 'id_padecimiento', 'id_padecimiento');
    }

    public function diagnosticoFuncional()
    {
        return $this->belongsTo(DiagnosticoFuncional::class, 'id_diagnostico_funcional', 'id_diagnostico_funcional');
    }
}
