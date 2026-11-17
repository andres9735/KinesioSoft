<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OfertaAdelanto extends Model
{
    public const ACTIVA = 'activa';

    public const CERRADA_AGENDA = 'cerrada_agenda';

    public const RECHAZADA = 'rechazada';

    public const VENCIDA = 'vencida';

    public const FALLO_ENVIO = 'fallo_envio';

    public const ORIGINAL_MODIFICADO = 'original_modificado';

    public const ACEPTADA = 'aceptada';

    public const CUPO_NO_DISPONIBLE = 'cupo_no_disponible';

    public const DESTINO_INCOMPATIBLE = 'destino_incompatible';

    protected $table = 'ofertas_adelanto';

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return array_fill_keys([
            'inicio_at', 'fin_at', 'destino_fin_at', 'origen_inicio_at', 'origen_fin_at',
            'activada_at', 'vence_at', 'cerrada_at', 'created_at', 'updated_at',
        ], 'immutable_datetime');
    }

    protected static function booted(): void
    {
        static::updating(function (self $oferta): void {
            if (array_diff(array_keys($oferta->getDirty()), ['estado', 'cerrada_at', 'updated_at']) !== []
                || $oferta->getRawOriginal('estado') !== self::ACTIVA
                || ! in_array($oferta->estado, [self::CERRADA_AGENDA, self::RECHAZADA, self::VENCIDA, self::FALLO_ENVIO, self::ORIGINAL_MODIFICADO, self::ACEPTADA, self::CUPO_NO_DISPONIBLE, self::DESTINO_INCOMPATIBLE], true)
                || $oferta->cerrada_at === null) {
                throw new LogicException('La oferta conserva su identidad, programación y vencimiento; su cierre es irreversible.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('La oferta durable no puede eliminarse.');
        });
    }

    // El estado persistido por sí solo no acredita una reserva: el límite es estricto.
    public function reservaVigenteEn(CarbonInterface $ahora): bool
    {
        return $this->estado === self::ACTIVA && $this->cerrada_at === null
            && $this->activada_at->lte($ahora) && $this->vence_at->gt($ahora);
    }

    public function intencion(): BelongsTo
    {
        return $this->belongsTo(IntencionEvaluacionCupo::class, 'intencion_evaluacion_cupo_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }
}
