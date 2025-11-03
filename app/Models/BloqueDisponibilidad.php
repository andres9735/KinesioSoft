<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;   // 👈 importar
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

class BloqueDisponibilidad extends Model
{
    use HasFactory;
    use SoftDeletes;    // 👈 activar

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

    protected $casts = [
        'activo' => 'bool',
        'deleted_at' => 'datetime',  // 👈 opcional pero prolijo
    ];

    /** Relaciones */
    public function profesional()
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    public function consultorio()
    {
        return $this->belongsTo(Consultorio::class, 'consultorio_id');
    }

    protected static function booted(): void
    {
        $resolveUserId = static fn() =>
        optional(Filament::auth()->user())->id ?? Auth::id();

        static::creating(function (self $model) use ($resolveUserId) {
            if (blank($model->profesional_id)) {
                $model->profesional_id = $resolveUserId();
            }
        });
    }

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
