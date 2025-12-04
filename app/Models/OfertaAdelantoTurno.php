<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class OfertaAdelantoTurno extends Model implements AuditableContract
{
    use Auditable;

    /** ---------- Config básica de Eloquent ---------- */
    protected $table = 'oferta_adelanto_turnos';

    // Definimos explícitamente qué campos se pueden asignar en masa
    protected $fillable = [
        'turno_ofertado_id',
        'turno_original_paciente_id',
        'turno_resultante_id',
        'profesional_id',
        'paciente_id',
        'paciente_perfil_id',
        'estado',
        'orden_cola',
        'oferta_token',
        'oferta_enviada_at',
        'fecha_limite_respuesta',
        'respondida_at',
    ];

    protected $casts = [
        'oferta_enviada_at'      => 'datetime',
        'fecha_limite_respuesta' => 'datetime',
        'respondida_at'          => 'datetime',
    ];

    /** ---------- Campos a auditar ---------- */
    protected $auditInclude = [
        'turno_ofertado_id',
        'turno_original_paciente_id',
        'turno_resultante_id',
        'profesional_id',
        'paciente_id',
        'paciente_perfil_id',
        'estado',
        'orden_cola',
        'oferta_enviada_at',
        'fecha_limite_respuesta',
        'respondida_at',
    ];

    /** ---------- Constantes de estado ---------- */
    public const ESTADO_PENDIENTE         = 'pendiente';
    public const ESTADO_ACEPTADA          = 'aceptada';
    public const ESTADO_RECHAZADA         = 'rechazada';
    public const ESTADO_SIN_RESPUESTA     = 'sin_respuesta';
    public const ESTADO_CANCELADA_SISTEMA = 'cancelada_sistema';
    public const ESTADO_EXPIRADA          = 'expirada';

    /** =========================================================
     * Relaciones
     * ========================================================= */

    /**
     * Turno que quedó libre (cancelado temprano) y se está ofreciendo.
     */
    public function turnoOfertado(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_ofertado_id', 'id_turno');
    }

    /**
     * Turno original del paciente al que se le ofrece adelantar.
     */
    public function turnoOriginalPaciente(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_original_paciente_id', 'id_turno');
    }

    /**
     * Turno resultante (cuando el paciente acepta el adelanto).
     */
    public function turnoResultante(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_resultante_id', 'id_turno');
    }

    /**
     * Profesional dueño de la agenda.
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    /**
     * Paciente (usuario) que recibe la oferta.
     */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paciente_id');
    }

    /**
     * Perfil clínico del paciente (tabla pacientes).
     */
    public function pacientePerfil(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_perfil_id', 'paciente_id');
    }

    /** =========================================================
     * Scopes útiles
     * ========================================================= */

    /**
     * Ofertas en estado pendiente.
     */
    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    /**
     * Ofertas relacionadas a un turno ofertado concreto.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \App\Models\Turno|int  $turno
     */
    public function scopeParaTurnoOfertado($query, Turno|int $turno)
    {
        $turnoId = $turno instanceof Turno ? $turno->id_turno : $turno;

        return $query->where('turno_ofertado_id', $turnoId);
    }
}
