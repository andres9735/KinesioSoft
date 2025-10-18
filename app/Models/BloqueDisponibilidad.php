<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BloqueDisponibilidad extends Model
{
    use HasFactory;

    /**
     * @property int $id
     * @property int $profesional_id
     * @property int|null $consultorio_id
     * @property int $dia_semana
     * @property string $hora_desde
     * @property string $hora_hasta
     * @property int $duracion_minutos
     * @property bool $activo
     * @property \App\Models\User $profesional
     * @property \App\Models\Consultorio|null $consultorio
     */


    protected $table = 'bloques_disponibilidad';

    protected $fillable = [
        'profesional_id',
        'consultorio_id',
        'dia_semana',
        'hora_desde',
        'hora_hasta',
        'activo',
        'duracion_minutos',
    ];

    /** Airbag: completar profesional_id si viniera vacío */
    protected static function booted()
    {
        static::creating(function (self $model) {
            if (blank($model->profesional_id)) {
                $model->profesional_id = Filament::auth()->id() ?? auth()->id();
            }
        });

        static::updating(function (self $model) {
            if (blank($model->profesional_id)) {
                $model->profesional_id = Filament::auth()->id() ?? auth()->id();
            }
        });
    }

    /** -------- Relaciones -------- */

    public function profesional()
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    public function consultorio()
    {
        return $this->belongsTo(Consultorio::class, 'consultorio_id');
    }

    /** -------- Accesorios / Helpers opcionales -------- */

    public function getNombreDiaAttribute(): string
    {
        return [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
        ][$this->dia_semana] ?? 'Desconocido';
    }

    public function getRangoHorarioAttribute(): string
    {
        return "{$this->hora_desde} - {$this->hora_hasta}";
    }
}
