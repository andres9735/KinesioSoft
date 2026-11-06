<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Registro técnico; el cifrado nunca se descifra mediante casts o consultas ordinarias. */
final class SobreEntregaEnlace extends Model
{
    protected $table = 'sobres_entrega_enlace';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $hidden = ['material_cifrado', 'reclamo_hash'];

    protected function casts(): array
    {
        return ['enlace_id' => 'integer', 'preparado_at' => 'immutable_datetime',
            'actualizado_at' => 'immutable_datetime', 'vence_at' => 'immutable_datetime', 'cerrado_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $sobre): void {
            if ($sobre->getConnection()->transactionLevel() < 1
                || ! in_array($sobre->estado, ['DISPONIBLE', 'INCIERTO', 'CERRADO'], true)
                || ($sobre->estado === 'CERRADO' ? ($sobre->material_cifrado !== null || $sobre->cerrado_at === null)
                    : (! is_string($sobre->material_cifrado) || $sobre->material_cifrado === '' || $sobre->cerrado_at !== null))) {
                throw new LogicException('El sobre requiere un ciclo de vida transaccional coherente.');
            }
            if ($sobre->exists && ($sobre->getRawOriginal('estado') === 'CERRADO'
                || array_diff(array_keys($sobre->getDirty()), ['material_cifrado', 'estado', 'resultado', 'reclamo_hash', 'actualizado_at', 'vence_at', 'cerrado_at']) !== []
                || $sobre->vence_at->gt($sobre->getOriginal('vence_at')))) {
                throw new LogicException('El sobre no admite reasociación, reapertura ni extensión de vigencia.');
            }
        });
        self::deleting(function (): never {
            throw new LogicException('Se elimina el material recuperable conservando la identidad cerrada.');
        });
    }

    public function __debugInfo(): array
    {
        return $this->toArray();
    }

    public function __serialize(): array
    {
        throw new LogicException('Los consumidores persistentes deben transportar únicamente identificadores.');
    }
}
