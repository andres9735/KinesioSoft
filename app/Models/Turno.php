<?php

namespace App\Models;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Turno extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipo',
        'inicio_at',
        'fin_at',
        'motivo_solicitud',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoTurnoEnum::class,
            'estado' => EstadoTurnoEnum::class,
            'inicio_at' => 'immutable_datetime',
            'fin_at' => 'immutable_datetime',
            'recordatorio_enviado_at' => 'immutable_datetime',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function kinesiologa(): BelongsTo
    {
        return $this->belongsTo(Kinesiologa::class);
    }
}
