<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicacionActual extends Model
{
    protected $table = 'medicacion_actual';
    protected $primaryKey = 'medicacion_id';

    protected $fillable = [
        'entrada_hc_id',
        'farmaco',
        'dosis',
        'frecuencia',
        'dosis_cantidad',
        'dosis_unidad',
        'cada_horas',
        'veces_por_dia',
        'frecuencia_unidad',
        'prn',
        'via',
        'fecha_desde',
        'fecha_hasta',
        'observaciones',
    ];

    protected $casts = [
        'prn'           => 'boolean',
        'fecha_desde'   => 'date',
        'fecha_hasta'   => 'date',
        'dosis_cantidad' => 'decimal:2',
        'cada_horas'    => 'integer',
        'veces_por_dia' => 'integer',
    ];

    public function entradaHc(): BelongsTo
    {
        return $this->belongsTo(EntradaHc::class, 'entrada_hc_id', 'entrada_hc_id');
    }

    /** Activa si no tiene fecha_hasta o la fecha_hasta >= hoy */
    public function getActivaAttribute(): bool
    {
        return is_null($this->fecha_hasta) || $this->fecha_hasta->gte(today());
    }

    /** Resumen legible combinando lo estructurado si existe, si no, textos humanos */
    public function getResumenAttribute(): string
    {
        $partes = [];

        $partes[] = $this->farmaco;

        // Dosis
        if ($this->dosis_cantidad && $this->dosis_unidad) {
            $partes[] = "{$this->dosis_cantidad} {$this->dosis_unidad}";
        } elseif ($this->dosis) {
            $partes[] = $this->dosis;
        }

        // Frecuencia
        $freq = null;
        if ($this->cada_horas) {
            $freq = "c/{$this->cada_horas} h";
        } elseif ($this->veces_por_dia) {
            $freq = "{$this->veces_por_dia}×/día";
        } elseif ($this->frecuencia) {
            $freq = $this->frecuencia;
        }
        if ($freq) $partes[] = $freq;

        if ($this->via) {
            $partes[] = "vía {$this->via}";
        }
        if ($this->prn) {
            $partes[] = "(PRN)";
        }

        // Rango de fechas
        $rango = "desde " . $this->fecha_desde?->format('d/m/Y');
        if ($this->fecha_hasta) {
            $rango .= " hasta " . $this->fecha_hasta->format('d/m/Y');
        }
        $partes[] = $rango;

        return implode(', ', array_filter($partes));
    }
}
