<?php

namespace App\Models;

use App\Enums\DiaSemanaEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranjaHorariaAgenda extends Model
{
    use HasFactory;

    protected $table = 'franjas_horarias_agenda';

    protected $fillable = [
        'dia_semana',
        'hora_inicio',
        'hora_fin',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => DiaSemanaEnum::class,
        ];
    }

    public function configuracionAgenda(): BelongsTo
    {
        return $this->belongsTo(ConfiguracionAgenda::class);
    }
}
