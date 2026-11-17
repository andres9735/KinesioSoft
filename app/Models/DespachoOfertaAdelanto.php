<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Identidad única de despacho y material recuperable cifrado; nunca un payload de cola. */
final class DespachoOfertaAdelanto extends Model
{
    protected $table = 'despachos_oferta_adelanto';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'material_cifrado'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return array_fill_keys(['preparado_at', 'vence_at', 'reclamado_at', 'resuelto_at', 'primer_fallo_at', 'proximo_intento_at'], 'immutable_datetime')
            + ['intentos' => 'integer'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $registro): void {
            if ($registro->getConnection()->transactionLevel() < 1) {
                throw new LogicException('El despacho requiere una transacción.');
            }
            $soloDepuracion = $registro->material_cifrado === null && array_keys($registro->getDirty()) === ['material_cifrado'];
            if ($registro->exists && ! $soloDepuracion && (array_diff(array_keys($registro->getDirty()), ['estado', 'material_cifrado', 'reclamado_at', 'resuelto_at', 'intentos', 'primer_fallo_at', 'proximo_intento_at']) !== []
                || ! in_array([$registro->getRawOriginal('estado'), $registro->estado], [
                    ['PENDIENTE', 'INCIERTO'], ['PENDIENTE', 'OMITIDO'], ['INCIERTO', 'ENVIADO'],
                    ['REINTENTO', 'INCIERTO'], ['REINTENTO', 'OMITIDO'], ['REINTENTO', 'FALLIDO'],
                    ['INCIERTO', 'REINTENTO'], ['INCIERTO', 'FALLIDO'], ['INCIERTO', 'OMITIDO'],
                ], true))) {
                throw new LogicException('El despacho no admite reasociación, renovación ni reenvío implícito.');
            }
        });
        self::deleting(function (): never {
            throw new LogicException('El despacho durable no puede eliminarse.');
        });
    }

    public function __debugInfo(): array
    {
        return $this->toArray();
    }

    public function __serialize(): array
    {
        throw new LogicException('La cola sólo admite referencias técnicas del despacho.');
    }
}
