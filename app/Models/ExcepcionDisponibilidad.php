<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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

    protected $casts = [
        'fecha' => 'date',
        'bloqueado' => 'boolean',
    ];

    /** ---------- Helper: ¿ya existe full-day (ambas horas NULL) para esa fecha? ---------- */
    public static function yaExisteFullDay(int $profesionalId, Carbon|string $fecha): bool
    {
        $f = $fecha instanceof Carbon ? $fecha->toDateString() : Carbon::parse($fecha)->toDateString();

        return static::query()
            ->where('profesional_id', $profesionalId)
            ->whereDate('fecha', $f)
            ->whereNull('hora_desde')
            ->whereNull('hora_hasta')
            ->exists();
    }

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
