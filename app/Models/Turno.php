<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

class Turno extends Model
{
    protected $table = 'turnos';
    protected $primaryKey = 'id_turno';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'profesional_id',
        'paciente_id',
        'id_consultorio', // coincide con la migración
        'fecha',
        'hora_desde',
        'hora_hasta',
        'estado',
        'motivo',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
    ];

    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_CONFIRMADO = 'confirmado';
    public const ESTADO_CANCELADO  = 'cancelado';

    // Relaciones
    public function profesional() { return $this->belongsTo(User::class, 'profesional_id'); }
    public function paciente()    { return $this->belongsTo(User::class, 'paciente_id'); }
    public function consultorio() { return $this->belongsTo(Consultorio::class, 'id_consultorio', 'id_consultorio'); }

    // Scopes útiles
    public function scopeDeProfesional($q, int $profesionalId) { return $q->where('profesional_id', $profesionalId); }
    public function scopeDePaciente($q, int $pacienteId)       { return $q->where('paciente_id', $pacienteId); }
    public function scopeEnFecha($q, string|Carbon $fecha)
    {
        $f = $fecha instanceof Carbon ? $fecha->toDateString() : $fecha;
        return $q->whereDate('fecha', $f);
    }
    public function scopeEntreFechas($q, string|Carbon $desde, string|Carbon $hasta)
    {
        $d = $desde instanceof Carbon ? $desde->toDateString() : $desde;
        $h = $hasta instanceof Carbon ? $hasta->toDateString() : $hasta;
        return $q->whereBetween('fecha', [$d, $h]);
    }
    public function scopeEnRangoHora($q, string $desde, string $hasta)
    {
        return $q->where('hora_desde', '<', $hasta)
                 ->where('hora_hasta', '>', $desde);
    }

    // Helpers
    public function getInicioAttribute(): ?Carbon
    {
        if (!$this->fecha || !$this->hora_desde) return null;
        return Carbon::parse($this->fecha->toDateString().' '.$this->getRawOriginal('hora_desde'));
    }
    public function getFinAttribute(): ?Carbon
    {
        if (!$this->fecha || !$this->hora_hasta) return null;
        return Carbon::parse($this->fecha->toDateString().' '.$this->getRawOriginal('hora_hasta'));
    }
    public function getDuracionMinutosAttribute(): ?int
    {
        $ini = $this->inicio; $fin = $this->fin;
        return ($ini && $fin) ? $ini->diffInMinutes($fin) : null;
    }

    // Normalización de horas: acepta "HH:mm" y guarda "HH:mm:ss"
    protected function horaDesde(): Attribute
    {
        return Attribute::make(
            set: fn($v) => $v ? (strlen($v) === 5 ? $v.':00' : $v) : null
        );
    }
    protected function horaHasta(): Attribute
    {
        return Attribute::make(
            set: fn($v) => $v ? (strlen($v) === 5 ? $v.':00' : $v) : null
        );
    }

    // Reglas sugeridas (para FormRequest/Livewire)
    public static function rules(): array
    {
        return [
            'profesional_id' => ['required', 'exists:users,id'],
            'paciente_id'    => ['required', 'exists:users,id'],
            'id_consultorio' => ['nullable', 'exists:consultorio,id_consultorio'],
            'fecha'          => ['required', 'date'],
            'hora_desde'     => ['required', 'date_format:H:i'],
            'hora_hasta'     => ['required', 'date_format:H:i', 'after:hora_desde'],
            'estado'         => ['required', 'in:pendiente,confirmado,cancelado'],
            'motivo'         => ['nullable', 'string', 'max:255'],
        ];
    }
}
