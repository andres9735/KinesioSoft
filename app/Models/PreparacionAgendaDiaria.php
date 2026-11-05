<?php

namespace App\Models;

use App\Enums\IncidenciaGeneracionAgendaEnum;
use App\Enums\ResultadoGeneracionAgendaEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PreparacionAgendaDiaria extends Model
{
    protected $table = 'preparaciones_agenda_diaria';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'iniciado_at' => 'immutable_datetime', 'finalizado_at' => 'immutable_datetime',
            'resultado' => ResultadoGeneracionAgendaEnum::class,
            'codigo_incidencia' => IncidenciaGeneracionAgendaEnum::class,
            'total_turnos' => 'integer', 'turnos_operativos' => 'integer',
            'turnos_en_atencion' => 'integer', 'otros_turnos' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $preparacion): void {
            if ($preparacion->getRawOriginal('resultado') !== ResultadoGeneracionAgendaEnum::EN_CURSO->value
                || array_diff(array_keys($preparacion->getDirty()), [
                    'resultado', 'finalizado_at', 'codigo_incidencia', 'total_turnos',
                    'turnos_operativos', 'turnos_en_atencion', 'otros_turnos',
                ]) !== []) {
                throw new LogicException('No se puede reescribir el ámbito ni un resultado final de preparación.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('La preparación conserva su trazabilidad.');
        });
    }

    public function ejecucion(): BelongsTo
    {
        return $this->belongsTo(EjecucionAgendaDiaria::class, 'ejecucion_id');
    }

    public function kinesiologa(): BelongsTo
    {
        return $this->belongsTo(Kinesiologa::class);
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'preparacion_origen_id');
    }
}
