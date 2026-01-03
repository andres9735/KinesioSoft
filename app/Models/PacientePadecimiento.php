<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PacientePadecimiento extends Model
{
    use SoftDeletes;

    protected $table = 'paciente_padecimiento';
    protected $primaryKey = 'id_padecimiento';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'paciente_id',
        'id_consulta',
        'id_padecimiento_tipo',
        'id_zona_anatomica',
        'nombre',
        'fecha_inicio',
        'lateralidad',
        'severidad',
        'estado',
        'prioridad',
        'origen',
        'notas',
        'detalle_zona',
        'fecha_cierre',
        'motivo_cierre',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_cierre' => 'datetime',
    ];

    public function diagnosticos()
    {
        return $this->hasMany(PadecimientoDiagnostico::class, 'id_padecimiento', 'id_padecimiento');
    }

    // (Opcional) relaciones útiles si ya tenés estos modelos
    public function paciente()
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'paciente_id');
    }

    public function consulta()
    {
        return $this->belongsTo(Consulta::class, 'id_consulta', 'id_consulta');
    }

    public function tipoPadecimiento()
    {
        return $this->belongsTo(PadecimientoTipo::class, 'id_padecimiento_tipo', 'id_padecimiento_tipo');
    }

    public function zonaAnatomica()
    {
        return $this->belongsTo(ZonaAnatomica::class, 'id_zona_anatomica', 'id_zona_anatomica');
    }
}
