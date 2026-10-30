<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class IntencionEvaluacionCupo extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'intenciones_evaluacion_cupo';

    protected $fillable = [
        'auditoria_cancelacion_id', 'turno_id', 'kinesiologa_id',
        'inicio_at', 'fin_at', 'liberado_at',
    ];

    protected function casts(): array
    {
        return [
            'inicio_at' => 'immutable_datetime', 'fin_at' => 'immutable_datetime',
            'liberado_at' => 'immutable_datetime', 'procesado_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $intencion): void {
            if (array_diff(array_keys($intencion->getDirty()), ['procesado_at']) !== []
                || ($intencion->isDirty('procesado_at') && $intencion->getRawOriginal('procesado_at') !== null)) {
                throw new LogicException('La identidad y el contexto de la liberación no pueden reescribirse ni reabrirse.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('La intención durable de evaluación no puede eliminarse.');
        });
    }

    public function cancelacion(): BelongsTo
    {
        return $this->belongsTo(Auditoria::class, 'auditoria_cancelacion_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function kinesiologa(): BelongsTo
    {
        return $this->belongsTo(Kinesiologa::class);
    }
}
