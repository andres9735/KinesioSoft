<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloqueoAgenda extends Model
{
    use HasFactory;

    public const ESTADO_ANULADO = 'ANULADO';

    public const ESTADO_PROXIMO = 'PROXIMO';

    public const ESTADO_EN_CURSO = 'EN_CURSO';

    public const ESTADO_PASADO = 'PASADO';

    protected $table = 'bloqueos_agenda';

    protected $fillable = [
        'inicio_at',
        'fin_at',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'inicio_at' => 'immutable_datetime',
            'fin_at' => 'immutable_datetime',
            'anulado_at' => 'immutable_datetime',
        ];
    }

    public function kinesiologa(): BelongsTo
    {
        return $this->belongsTo(Kinesiologa::class);
    }

    public function scopeNoAnulados(Builder $query): Builder
    {
        return $query->whereNull('anulado_at');
    }

    public function scopeAnulados(Builder $query): Builder
    {
        return $query->whereNotNull('anulado_at');
    }

    public function scopeProximosEn(Builder $query, CarbonInterface $ahora): Builder
    {
        return $query
            ->noAnulados()
            ->where('inicio_at', '>', $ahora);
    }

    public function scopeEnCursoEn(Builder $query, CarbonInterface $ahora): Builder
    {
        return $query
            ->noAnulados()
            ->where('inicio_at', '<=', $ahora)
            ->where('fin_at', '>', $ahora);
    }

    public function scopePasadosEn(Builder $query, CarbonInterface $ahora): Builder
    {
        return $query
            ->noAnulados()
            ->where('fin_at', '<=', $ahora);
    }

    public function scopeSuperpuestosCon(
        Builder $query,
        CarbonInterface $inicio,
        CarbonInterface $fin
    ): Builder {
        return $query
            ->noAnulados()
            ->where('inicio_at', '<', $fin)
            ->where('fin_at', '>', $inicio);
    }

    public function estadoEn(CarbonInterface $ahora): string
    {
        if ($this->anulado_at !== null) {
            return self::ESTADO_ANULADO;
        }

        if ($this->inicio_at->isAfter($ahora)) {
            return self::ESTADO_PROXIMO;
        }

        if ($this->fin_at->lessThanOrEqualTo($ahora)) {
            return self::ESTADO_PASADO;
        }

        return self::ESTADO_EN_CURSO;
    }
}
