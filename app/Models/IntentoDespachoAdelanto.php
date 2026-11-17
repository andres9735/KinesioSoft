<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class IntentoDespachoAdelanto extends Model
{
    protected $table = 'intentos_despacho_adelanto';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['iniciado_at' => 'immutable_datetime', 'resuelto_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(function (self $intento): void {
            if ($intento->getRawOriginal('resultado') !== 'INCIERTO' || $intento->getRawOriginal('resuelto_at') !== null || $intento->resuelto_at === null
                || array_diff(array_keys($intento->getDirty()), ['resultado', 'resuelto_at']) !== []) {
                throw new LogicException('El intento no admite reasociación ni reescritura de un resultado conocido.');
            }
        });
        self::deleting(fn () => throw new LogicException('El intento no puede eliminarse.'));
    }
}
