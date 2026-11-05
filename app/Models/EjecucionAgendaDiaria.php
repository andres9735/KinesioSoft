<?php

namespace App\Models;

use App\Enums\IncidenciaGeneracionAgendaEnum;
use App\Enums\ResultadoGeneracionAgendaEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class EjecucionAgendaDiaria extends Model
{
    protected $table = 'ejecuciones_agenda_diaria';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** Persistir DATE civil sin el formato de precisión de los momentos de ejecución. */
    protected function fechaLocal(): Attribute
    {
        return Attribute::make(set: fn ($valor): string => $valor instanceof CarbonInterface
            ? $valor->toDateString() : CarbonImmutable::parse($valor)->toDateString());
    }

    protected function casts(): array
    {
        return [
            'fecha_local' => 'immutable_date', 'iniciado_at' => 'immutable_datetime',
            'finalizado_at' => 'immutable_datetime', 'resultado' => ResultadoGeneracionAgendaEnum::class,
            'codigo_incidencia' => IncidenciaGeneracionAgendaEnum::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $ejecucion): void {
            if ($ejecucion->getRawOriginal('resultado') !== ResultadoGeneracionAgendaEnum::EN_CURSO->value
                || array_diff(array_keys($ejecucion->getDirty()), ['resultado', 'finalizado_at', 'codigo_incidencia']) !== []) {
                throw new LogicException('No se puede reescribir la identidad ni un resultado final de generación.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('La ejecución conserva su trazabilidad.');
        });
    }

    public function preparaciones(): HasMany
    {
        return $this->hasMany(PreparacionAgendaDiaria::class, 'ejecucion_id');
    }
}
