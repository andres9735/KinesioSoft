<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class JornadaGeneracionAgenda extends Model
{
    protected $table = 'jornadas_generacion_agenda';

    public $timestamps = false;

    protected $fillable = ['fecha_local', 'zona_horaria'];

    protected static function booted(): void
    {
        static::updating(function (self $jornada): void {
            if ($jornada->isDirty(['fecha_local', 'zona_horaria'])) {
                throw new LogicException('La identidad lógica de jornada no puede cambiar.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('El control durable de jornada no puede eliminarse.');
        });
    }

    public function ejecucionActual(): BelongsTo
    {
        return $this->belongsTo(EjecucionAgendaDiaria::class, 'ejecucion_actual_id');
    }
}
