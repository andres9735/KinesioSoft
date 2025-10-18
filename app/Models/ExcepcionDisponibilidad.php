<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExcepcionDisponibilidad extends Model
{
    use HasFactory;

    protected $table = 'excepciones_disponibilidad';

    protected $fillable = [
        'profesional_id',
        'fecha',
        'bloqueado',
        'hora_desde',
        'hora_hasta',
        'motivo',
    ];

    /** -------- Relaciones -------- */

    public function profesional()
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    /** -------- Accesorios / Helpers opcionales -------- */

    public function getDescripcionAttribute(): string
    {
        if ($this->bloqueado) {
            return "Día bloqueado completo ({$this->motivo})";
        }

        return "No disponible de {$this->hora_desde} a {$this->hora_hasta}" .
            ($this->motivo ? " ({$this->motivo})" : '');
    }
}
