<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfiguracionAgenda extends Model
{
    use HasFactory;

    public const ESTADO_ANULADA = 'ANULADA';

    public const ESTADO_FUTURA = 'FUTURA';

    public const ESTADO_VIGENTE = 'VIGENTE';

    public const ESTADO_HISTORICA = 'HISTORICA';

    protected $table = 'configuraciones_agenda';

    protected $fillable = [
        'vigencia_desde',
        'vigencia_hasta',
        'duracion_turno_minutos',
    ];

    protected function casts(): array
    {
        return [
            'vigencia_desde' => 'immutable_date',
            'vigencia_hasta' => 'immutable_date',
            'duracion_turno_minutos' => 'integer',
            'anulado_at' => 'immutable_datetime',
        ];
    }

    public function kinesiologa(): BelongsTo
    {
        return $this->belongsTo(Kinesiologa::class);
    }

    public function franjasHorarias(): HasMany
    {
        return $this->hasMany(FranjaHorariaAgenda::class);
    }

    public function scopeNoAnuladas(Builder $query): Builder
    {
        return $query->whereNull('anulado_at');
    }

    public function scopeAnuladas(Builder $query): Builder
    {
        return $query->whereNotNull('anulado_at');
    }

    public function scopeAplicablesEn(Builder $query, CarbonInterface $fecha): Builder
    {
        $fechaCivil = $fecha->toDateString();

        return $query
            ->noAnuladas()
            ->whereDate('vigencia_desde', '<=', $fechaCivil)
            ->where(function (Builder $query) use ($fechaCivil): void {
                $query
                    ->whereNull('vigencia_hasta')
                    ->orWhereDate('vigencia_hasta', '>=', $fechaCivil);
            });
    }

    public function scopeFuturasDesde(Builder $query, CarbonInterface $fecha): Builder
    {
        return $query
            ->noAnuladas()
            ->whereDate('vigencia_desde', '>', $fecha->toDateString());
    }

    public function scopeHistoricasEn(Builder $query, CarbonInterface $fecha): Builder
    {
        return $query
            ->noAnuladas()
            ->whereNotNull('vigencia_hasta')
            ->whereDate('vigencia_hasta', '<', $fecha->toDateString());
    }

    public function scopeSuperpuestasCon(
        Builder $query,
        CarbonInterface $vigenciaDesde,
        ?CarbonInterface $vigenciaHasta
    ): Builder {
        $query
            ->noAnuladas()
            ->where(function (Builder $query) use ($vigenciaDesde): void {
                $query
                    ->whereNull('vigencia_hasta')
                    ->orWhereDate('vigencia_hasta', '>=', $vigenciaDesde->toDateString());
            });

        if ($vigenciaHasta !== null) {
            $query->whereDate('vigencia_desde', '<=', $vigenciaHasta->toDateString());
        }

        return $query;
    }

    public function aplicaEn(CarbonInterface $fecha): bool
    {
        if ($this->anulado_at !== null) {
            return false;
        }

        $fechaCivil = $fecha->toDateString();

        return $this->vigencia_desde->toDateString() <= $fechaCivil
            && ($this->vigencia_hasta === null
                || $this->vigencia_hasta->toDateString() >= $fechaCivil);
    }

    public function estadoEn(CarbonInterface $fecha): string
    {
        if ($this->anulado_at !== null) {
            return self::ESTADO_ANULADA;
        }

        if ($this->vigencia_desde->toDateString() > $fecha->toDateString()) {
            return self::ESTADO_FUTURA;
        }

        if ($this->aplicaEn($fecha)) {
            return self::ESTADO_VIGENTE;
        }

        return self::ESTADO_HISTORICA;
    }

    public function puedeEditarseEn(CarbonInterface $fecha): bool
    {
        return $this->anulado_at === null
            && $this->vigencia_desde->toDateString() > $fecha->toDateString();
    }
}
