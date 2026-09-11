<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeclaracionSaludPaciente extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public const ESTADO_PENDIENTE_REVISION = 'PENDIENTE_REVISION';

    protected $table = 'declaraciones_salud_paciente';

    protected $fillable = [
        'paciente_id',
        'objetivo_salud',
        'peso_kg',
        'altura_cm',
        'ocupacion',
        'nivel_actividad_fisica',
        'detalle_actividad_fisica',
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
