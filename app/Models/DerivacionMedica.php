<?php

namespace App\Models;

use App\Enums\EstadoDerivacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DerivacionMedica extends Model
{
    protected $table = 'derivacion_medica';
    protected $primaryKey = 'id_derivacion';

    protected $fillable = [
        'fecha_emision',
        'fecha_vencimiento',
        'estado',
        'medico_nombre',
        'medico_matricula',
        'medico_especialidad',
        'institucion',
        'diagnostico_texto',
        'indicaciones',
        'sesiones_autorizadas',
        'archivo_url',
        'archivo_path',
        'archivo_disk',
        'entrada_hc_id',
    ];

    protected $casts = [
        'fecha_emision'        => 'date',
        'fecha_vencimiento'    => 'date',
        'sesiones_autorizadas' => 'integer',
        'estado' => EstadoDerivacion::class,
    ];

    public function entradaHc(): BelongsTo
    {
        return $this->belongsTo(EntradaHc::class, 'entrada_hc_id', 'entrada_hc_id');
    }

    // Conveniencia para badges
    public function getColorEstadoAttribute(): string
    {
        return match ($this->estado) {
            'vigente' => 'success',
            'vencida' => 'danger',
            'anulada' => 'warning',
            default   => 'gray',
        };
    }
}
