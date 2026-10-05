<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfiguracionAgenda extends Model
{
    use HasFactory;

    protected $table = 'configuraciones_agenda';

    protected $fillable = [
        'vigencia_desde',
        'vigencia_hasta',
        'duracion_turno_minutos',
    ];

    protected function casts(): array
    {
        return [
            'vigencia_desde' => 'immutable_date',
            'vigencia_hasta' => 'immutable_date',
            'duracion_turno_minutos' => 'integer',
            'anulado_at' => 'immutable_datetime',
        ];
    }

    public function kinesiologa(): BelongsTo
    {
        return $this->belongsTo(Kinesiologa::class);
    }

    public function franjasHorarias(): HasMany
    {
        return $this->hasMany(FranjaHorariaAgenda::class);
    }
}
