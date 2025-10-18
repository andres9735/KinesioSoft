<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consultorio extends Model
{
    use SoftDeletes;

    protected $table = 'consultorio';
    protected $primaryKey = 'id_consultorio';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nombre',
        // agrega aquí otros campos si tu tabla los tiene
    ];

    // Relación inversa (opcional, por conveniencia)
    public function equipos()
    {
        return $this->hasMany(EquipoTerapeutico::class, 'id_consultorio', 'id_consultorio');
    }

    // Relación opcional con bloques de disponibilidad (por coherencia)
    public function bloquesDisponibilidad()
    {
        return $this->hasMany(BloqueDisponibilidad::class, 'consultorio_id', 'id_consultorio');
    }
}
