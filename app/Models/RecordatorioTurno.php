<?php

namespace App\Models;

use App\Enums\EstadoRecordatorioTurnoEnum;
use App\Enums\TipoTurnoEnum;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class RecordatorioTurno extends Model
{
    protected $table = 'recordatorios_turno';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['*'];

    protected $hidden = ['destinatario'];

    public const INCIDENCIAS = ['CORREO_NO_UTILIZABLE', 'PROGRAMACION_MODIFICADA', 'ENLACE_NO_DISPONIBLE', 'TRANSPORTE_RECUPERABLE', 'TRANSPORTE_DEFINITIVO'];

    protected function casts(): array
    {
        return ['estado' => EstadoRecordatorioTurnoEnum::class, 'tipo' => TipoTurnoEnum::class,
            'version_programacion' => 'integer', 'inicio_at' => 'immutable_datetime', 'fin_at' => 'immutable_datetime',
            'creado_at' => 'immutable_datetime', 'actualizado_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $registro): void {
            $columnas = ['id', 'identidad', 'turno_id', 'paciente_id', 'kinesiologa_id', 'version_programacion',
                'tipo', 'inicio_at', 'fin_at', 'zona_horaria', 'destinatario', 'estado', 'incidencia',
                'enlace_id', 'sobre_id', 'creado_at', 'actualizado_at'];
            if ($registro->getConnection()->transactionLevel() < 1
                || array_diff(array_keys($registro->getAttributes()), $columnas) !== []
                || ! Str::isUuid((string) $registro->identidad) || $registro->version_programacion < 1
                || EstadoRecordatorioTurnoEnum::tryFrom((string) ($registro->getAttributes()['estado'] ?? '')) === null
                || ($registro->incidencia !== null && ! in_array($registro->incidencia, self::INCIDENCIAS, true))
                || ($registro->destinatario !== null && ! self::correoUtilizable($registro->destinatario))
                || (($registro->enlace_id === null) !== ($registro->sobre_id === null))
                || ! CarbonImmutable::parse($registro->getAttributes()['fin_at'], $registro->zona_horaria)
                    ->gt(CarbonImmutable::parse($registro->getAttributes()['inicio_at'], $registro->zona_horaria))) {
                throw new LogicException('El recordatorio requiere contexto administrativo íntegro y una transacción.');
            }
            if ($registro->exists && array_diff(array_keys($registro->getDirty()),
                ['destinatario', 'estado', 'incidencia', 'enlace_id', 'sobre_id', 'actualizado_at']) !== []) {
                throw new LogicException('La identidad y programación del recordatorio son históricas.');
            }
            if ($registro->exists && $registro->getRawOriginal('enlace_id') !== null && $registro->isDirty(['enlace_id', 'sobre_id'])) {
                throw new LogicException('El recordatorio no sustituye su enlace de entrega.');
            }
            if ($registro->enlace_id !== null) {
                $enlace = EnlaceAsistenciaTurno::find($registro->enlace_id);
                $sobre = SobreEntregaEnlace::find($registro->sobre_id);
                if ($enlace === null || $sobre === null || (int) $sobre->enlace_id !== (int) $enlace->id
                    || (int) $enlace->turno_id !== (int) $registro->turno_id
                    || (int) $enlace->version_programacion !== $registro->version_programacion) {
                    throw new LogicException('El sobre debe corresponder al enlace y programación del recordatorio.');
                }
            }
        });
        static::deleting(fn () => throw new LogicException('El recordatorio conserva su trazabilidad.'));
    }

    public static function correoUtilizable(?string $correo): bool
    {
        return $correo !== null && strlen($correo) <= 254 && $correo === trim($correo)
            && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false && ! preg_match('/[\\x00-\\x20]/', $correo);
    }

    public function correspondeA(Turno $turno): bool
    {
        return (int) $this->turno_id === (int) $turno->id
            && (int) $this->paciente_id === (int) $turno->paciente_id
            && (int) $this->kinesiologa_id === (int) $turno->kinesiologa_id
            && $this->tipo === $turno->tipo
            && $this->version_programacion === EnlaceAsistenciaTurno::versionActualDe($turno)
            && CarbonImmutable::parse($this->getRawOriginal('inicio_at'), $this->zona_horaria)
                ->equalTo(CarbonImmutable::parse($turno->getRawOriginal('inicio_at'), $this->zona_horaria))
            && CarbonImmutable::parse($this->getRawOriginal('fin_at'), $this->zona_horaria)
                ->equalTo(CarbonImmutable::parse($turno->getRawOriginal('fin_at'), $this->zona_horaria));
    }

    public function intentos(): HasMany
    {
        return $this->hasMany(IntentoRecordatorioTurno::class, 'recordatorio_id');
    }

    public function __debugInfo(): array
    {
        return $this->attributesToArray();
    }

    public function __serialize(): array
    {
        throw new LogicException('Los trabajos deben transportar sólo identificadores del recordatorio.');
    }
}
