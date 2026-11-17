<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EvaluacionAdelanto extends Model
{
    protected $table = 'evaluaciones_adelanto';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['procesada_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('El resultado de la primera evaluación es inmutable.');
        });
        self::deleting(function (): never {
            throw new LogicException('La evaluación durable no puede eliminarse.');
        });
    }
}
