<?php

namespace App\Models;

use App\Enums\ResultadoIntentoRecordatorioEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class IntentoRecordatorioTurno extends Model
{
    protected $table = 'intentos_recordatorio_turno';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['*'];

    protected $hidden = ['destinatario'];

    protected function casts(): array
    {
        return ['numero' => 'integer', 'resultado' => ResultadoIntentoRecordatorioEnum::class,
            'iniciado_at' => 'immutable_datetime', 'resuelto_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $intento): void {
            if ($intento->getConnection()->transactionLevel() < 1
                || array_diff(array_keys($intento->getAttributes()), ['id', 'recordatorio_id', 'identidad', 'numero',
                    'destinatario', 'resultado', 'incidencia', 'evidencia_entrega', 'iniciado_at', 'resuelto_at']) !== []
                || ! Str::isUuid((string) $intento->identidad) || $intento->numero < 1 || $intento->numero > 4
                || ResultadoIntentoRecordatorioEnum::tryFrom((string) ($intento->getAttributes()['resultado'] ?? '')) === null
                || ! RecordatorioTurno::correoUtilizable($intento->destinatario)
                || ($intento->incidencia !== null && ! in_array($intento->incidencia, RecordatorioTurno::INCIDENCIAS, true))
                || ($intento->resultado === ResultadoIntentoRecordatorioEnum::ENTREGA_VERIFICADA
                    && ! Str::isUuid((string) $intento->evidencia_entrega))) {
                throw new LogicException('El intento requiere referencias y resultados técnicos válidos.');
            }
            if ($intento->exists && (array_diff(array_keys($intento->getDirty()),
                ['resultado', 'incidencia', 'evidencia_entrega', 'resuelto_at']) !== []
                || ! in_array($intento->getRawOriginal('resultado'), ['INCIERTO', 'ACEPTADO'], true))) {
                throw new LogicException('El intento conserva su identidad y resultado definitivo.');
            }
        });
        static::deleting(fn () => throw new LogicException('El intento conserva su trazabilidad.'));
    }

    public function __debugInfo(): array
    {
        return $this->attributesToArray();
    }

    public function __serialize(): array
    {
        throw new LogicException('Los trabajos deben transportar sólo identificadores del intento.');
    }
}
