<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class DespachoConfirmacionAdelanto extends Model
{
    protected $table = 'despachos_confirmacion_adelanto';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['material_cifrado'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return array_fill_keys(['preparado_at', 'reclamado_at', 'resuelto_at'], 'immutable_datetime');
    }

    protected static function booted(): void
    {
        self::creating(function (self $registro): void {
            if ($registro->getConnection()->transactionLevel() < 1 || $registro->estado !== 'PENDIENTE'
                || ! is_string($registro->material_cifrado)
                || SalidaOfertaAdelanto::query()->whereKey($registro->salida_id)->value('resultado') !== OfertaAdelanto::ACEPTADA) {
                throw new LogicException('La confirmación requiere una aceptación registrada y su transacción.');
            }
        });
        self::updating(function (self $registro): void {
            if ($registro->getConnection()->transactionLevel() < 1
                || array_diff(array_keys($registro->getDirty()), ['estado', 'material_cifrado', 'reclamado_at', 'resuelto_at']) !== []
                || ! in_array([$registro->getRawOriginal('estado'), $registro->estado], [
                    ['PENDIENTE', 'INCIERTO'], ['PENDIENTE', 'FALLIDO'], ['PENDIENTE', 'OMITIDO'],
                    ['INCIERTO', 'ENVIADO'], ['INCIERTO', 'FALLIDO'], ['INCIERTO', 'OMITIDO'], ['INCIERTO', 'INCIERTO'],
                ], true)) {
                throw new LogicException('La confirmación no admite reasociación ni reenvío automático.');
            }
        });
        self::deleting(fn () => throw new LogicException('El despacho conserva su trazabilidad.'));
    }

    public function __debugInfo(): array
    {
        return $this->toArray();
    }

    public function __serialize(): array
    {
        throw new LogicException('La cola sólo admite referencias técnicas de confirmación.');
    }
}
