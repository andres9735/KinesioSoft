<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class DeclaracionSaludPaciente extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public const ESTADO_PENDIENTE_REVISION = 'PENDIENTE_REVISION';

    public const ESTADO_REVISADA = 'REVISADA';

    protected $table = 'declaraciones_salud_paciente';

    protected $fillable = [
        'paciente_id',
        'objetivo_salud',
        'peso_kg',
        'altura_cm',
        'ocupacion',
        'nivel_actividad_fisica',
        'detalle_actividad_fisica',
        'antecedentes_respuesta',
        'cirugias_respuesta',
        'alergias_respuesta',
        'medicacion_respuesta',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'peso_kg' => 'decimal:2',
            'altura_cm' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $declaracion): void {
            if (array_diff(array_keys($declaracion->getDirty()), ['estado']) !== []) {
                throw new LogicException('La declaración original se conserva; las correcciones pertenecen a la revisión profesional.');
            }
            if ($declaracion->isDirty('estado') && ($declaracion->getRawOriginal('estado') !== self::ESTADO_PENDIENTE_REVISION
                || $declaracion->estado !== self::ESTADO_REVISADA || ! $declaracion->incorporacion()->exists())) {
                throw new LogicException('La transición a Revisada requiere una incorporación registrada.');
            }
        });
        static::deleting(fn () => throw new LogicException('La declaración original no se elimina.'));
    }

    public function confirmacion(): HasOne
    {
        return $this->hasOne(ConfirmacionRevisionDeclaracion::class, 'declaracion_salud_id');
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(RevisionDeclaracionSalud::class, 'declaracion_salud_id');
    }

    public function incorporacion(): HasOne
    {
        return $this->hasOne(IncorporacionDeclaracionSalud::class, 'declaracion_salud_id');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            ItemDeclaracionSalud::class,
            'declaracion_salud_id'
        );
    }
}
