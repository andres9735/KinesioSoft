<?php

namespace App\Models\Concerns;

use LogicException;

trait EsRegistroClinicoInmutable
{
    protected static function bootEsRegistroClinicoInmutable(): void
    {
        static::updating(fn () => throw new LogicException('El registro clínico se conserva mediante versiones.'));
        static::deleting(fn () => throw new LogicException('El registro clínico no se elimina.'));
    }
}
