<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

final class SalidaOfertaAdelanto extends Model
{
    protected $table = 'salidas_oferta_adelanto';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['registrada_at' => 'immutable_datetime', 'continuada_at' => 'immutable_datetime'];
    }

    public function confirmacion(): HasOne
    {
        return $this->hasOne(DespachoConfirmacionAdelanto::class, 'salida_id');
    }

    protected static function booted(): void
    {
        self::creating(function (self $salida): void {
            if ($salida->resultado === OfertaAdelanto::ACEPTADA && (
                ! in_array($salida->estado_turno_aceptado, ['PENDIENTE', 'CONFIRMADO'], true)
                || $salida->continuacion !== 'no_corresponde' || $salida->continuada_at === null
                || $salida->oferta_siguiente_id !== null)) {
                throw new LogicException('Una aceptación conserva su estado y no continúa la evaluación.');
            }
        });
        self::updating(function (self $salida): void {
            if ($salida->getRawOriginal('continuacion') !== 'pendiente' || $salida->continuacion === 'pendiente'
                || $salida->continuada_at === null
                || array_diff(array_keys($salida->getDirty()), ['continuacion', 'oferta_siguiente_id', 'continuada_at']) !== []) {
                throw new LogicException('El resultado y su continuación son durables e irreversibles.');
            }
        });
        self::deleting(fn () => throw new LogicException('La salida de la oferta no puede eliminarse.'));
    }
}
