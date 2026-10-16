<?php

namespace App\Models;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

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

    protected static function booted(): void
    {
        static::updating(function (self $turno): void {
            if ($turno->getRawOriginal('tipo') === TipoTurnoEnum::PRIMERA_CONSULTA->value
                && $turno->isDirty(['tipo', 'motivo_solicitud'])) {
                throw new LogicException('El tipo y el motivo original de una Primera consulta no pueden modificarse.');
            }
        });
    }

    public function documentosClinicosOrigenTurno(): HasMany
    {
        return $this->hasMany(DocumentoClinicoOrigenTurno::class);
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
