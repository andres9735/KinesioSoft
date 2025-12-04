<?php

namespace App\Models;

use App\Events\TurnoCancelado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Turno extends Model implements AuditableContract
{
    use Auditable;

    /** ---------- Config básica de Eloquent ---------- */
    protected $table = 'turnos';
    protected $primaryKey = 'id_turno';
    public $incrementing = true;
    protected $keyType = 'int';

    public function getRouteKeyName()
    {
        return 'id_turno';
    }

    /** ---------- Asignación masiva ---------- */
    protected $fillable = [
        'profesional_id',
        'paciente_id',          // legacy -> users.id
        'paciente_perfil_id',   // nuevo -> pacientes.paciente_id (transición)
        'id_consultorio',
        'fecha',
        'hora_desde',
        'hora_hasta',
        'estado',
        'motivo',

        // recordatorios
        'reminder_token',
        'reminder_status',
        'reminder_sent_at',

        // adelanto automático
        'es_adelanto_automatico',
    ];

    /** ---------- Casts ---------- */
    protected $casts = [
        'fecha'                 => 'date',
        'reminder_sent_at'      => 'datetime',
        'es_adelanto_automatico' => 'boolean',
    ];

    /** ---------- Campos a auditar ---------- */
    protected $auditInclude = [
        'profesional_id',
        'paciente_id',
        'paciente_perfil_id',
        'id_consultorio',
        'fecha',
        'hora_desde',
        'hora_hasta',
        'estado',
        'motivo',
        'es_adelanto_automatico',
        'reminder_token',
        'reminder_status',
        'reminder_sent_at',
    ];

    /** ---------- Constantes de estado ---------- */
    public const ESTADO_PENDIENTE        = 'pendiente';
    public const ESTADO_CONFIRMADO       = 'confirmado';
    public const ESTADO_CANCELADO        = 'cancelado';
    public const ESTADO_CANCELADO_TARDE  = 'cancelado_tarde';
    public const ESTADO_ATENDIDO         = 'atendido';
    public const ESTADO_NO_ASISTIO       = 'no_asistio';

    /** ---------- Recordatorios ---------- */
    public const REMINDER_PENDING = 'pending';
    public const REMINDER_SENT    = 'sent';
    public const REMINDER_FAILED  = 'failed';

    /** =========================================================
     * Capa de compatibilidad (transición a pacientes)
     * + disparo de eventos de dominio
     * ========================================================= */
    protected static function booted(): void
    {
        // Compatibilidad paciente_id <-> paciente_perfil_id
        static::saving(function (self $turno) {
            // Si se cambia el legacy (users.id) y falta el perfil, lo completamos
            if ($turno->isDirty('paciente_id') && empty($turno->paciente_perfil_id)) {
                $turno->paciente_perfil_id = \App\Models\Paciente::where('user_id', $turno->paciente_id)
                    ->value('paciente_id');
            }

            // Si se cambia el perfil y falta el legacy, lo completamos (solo durante transición)
            if ($turno->isDirty('paciente_perfil_id') && empty($turno->paciente_id)) {
                $turno->paciente_id = \App\Models\Paciente::where('paciente_id', $turno->paciente_perfil_id)
                    ->value('user_id');
            }
        });

        // Disparar evento de dominio cuando el turno pasa a cancelado / cancelado_tarde
        static::updated(function (self $turno) {
            // Solo nos interesa cuando EL ESTADO cambia
            if (! $turno->wasChanged('estado')) {
                return;
            }

            // Solo cuando el nuevo estado es cancelado o cancelado_tarde
            if (! in_array($turno->estado, [self::ESTADO_CANCELADO, self::ESTADO_CANCELADO_TARDE], true)) {
                return;
            }

            // ¿Fue cancelación temprana (>= minutos de configuración)?
            $esTemprano = $turno->esCancelacionTemprana();

            // Disparamos el evento de dominio
            event(new TurnoCancelado($turno, $esTemprano));
        });
    }

    /** ---------- Helpers de presentación (Filament) ---------- */
    public static function estadoColor(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_PENDIENTE        => 'warning',
            self::ESTADO_CONFIRMADO       => 'success',
            self::ESTADO_CANCELADO,
            self::ESTADO_CANCELADO_TARDE  => 'danger',
            self::ESTADO_ATENDIDO         => 'success',
            self::ESTADO_NO_ASISTIO       => 'warning',
            default                       => 'gray',
        };
    }

    public static function estadoIcon(string $estado): ?string
    {
        return match ($estado) {
            self::ESTADO_PENDIENTE        => 'heroicon-o-clock',
            self::ESTADO_CONFIRMADO       => 'heroicon-o-check',
            self::ESTADO_CANCELADO        => 'heroicon-o-x-mark',
            self::ESTADO_CANCELADO_TARDE  => 'heroicon-o-exclamation-triangle',
            self::ESTADO_ATENDIDO         => 'heroicon-o-check-badge',
            self::ESTADO_NO_ASISTIO       => 'heroicon-o-user-x-mark',
            default                       => null,
        };
    }

    /** =========================================================
     * Relaciones
     * ========================================================= */
    public function profesional()
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    // LEGACY: sigue apuntando a users.id para no romper nada existente
    public function paciente()
    {
        return $this->belongsTo(User::class, 'paciente_id');
    }

    // NUEVO: perfil clínico en pacientes
    public function pacientePerfil()
    {
        return $this->belongsTo(\App\Models\Paciente::class, 'paciente_perfil_id', 'paciente_id');
    }

    // Alias explícito del legacy (por si querés ir migrando vistas más claras)
    public function pacienteUser()
    {
        return $this->belongsTo(User::class, 'paciente_id');
    }

    public function consultorio()
    {
        return $this->belongsTo(Consultorio::class, 'id_consultorio', 'id_consultorio');
    }

    /**
     * Ofertas de adelanto que se generaron a partir de este turno cancelado.
     * (Es el turno que quedó libre y se ofrece a otros pacientes).
     */
    public function ofertasAdelantoGeneradas()
    {
        return $this->hasMany(OfertaAdelantoTurno::class, 'turno_ofertado_id', 'id_turno');
    }

    /**
     * Ofertas en las que este turno es el turno original del paciente
     * (el turno que el paciente tenía antes de adelantar).
     */
    public function ofertasAdelantoComoOrigen()
    {
        return $this->hasMany(OfertaAdelantoTurno::class, 'turno_original_paciente_id', 'id_turno');
    }

    /**
     * Oferta de adelanto en la que este turno es el turno resultante
     * (turno nuevo que nació por el adelanto automático).
     */
    public function ofertaAdelantoResultante()
    {
        return $this->hasOne(OfertaAdelantoTurno::class, 'turno_resultante_id', 'id_turno');
    }

    /** =========================================================
     * Scopes útiles
     * ========================================================= */
    public function scopeDeProfesional($q, int $profesionalId)
    {
        return $q->where('profesional_id', $profesionalId);
    }

    // LEGACY: por users.id (se mantiene)
    public function scopeDePaciente($q, int $pacienteUserId)
    {
        return $q->where('paciente_id', $pacienteUserId);
    }

    // NUEVO: por pacientes.paciente_id
    public function scopeDePacientePerfil($q, int $pacientePerfilId)
    {
        return $q->where('paciente_perfil_id', $pacientePerfilId);
    }

    // Útil para “Mis turnos” ya con perfil, sin romper legacy
    public function scopeDelUserAutenticadoViaPerfil($q, int $userId)
    {
        return $q->whereHas('pacientePerfil', fn($qq) => $qq->where('user_id', $userId));
    }

    public function scopeEnFecha($q, string|Carbon $fecha)
    {
        $f = $fecha instanceof Carbon ? $fecha->toDateString() : Carbon::parse($fecha)->toDateString();
        return $q->whereDate('fecha', $f);
    }

    public function scopeEntreFechas($q, string|Carbon $desde, string|Carbon $hasta)
    {
        $d = $desde instanceof Carbon ? $desde->toDateString() : Carbon::parse($desde)->toDateString();
        $h = $hasta instanceof Carbon ? $hasta->toDateString() : Carbon::parse($hasta)->toDateString();
        return $q->whereBetween('fecha', [$d, $h]);
    }

    /** Rango horario abierto: choca si startA < endB && endA > startB */
    public function scopeEnRangoHora($q, string $desde, string $hasta)
    {
        $desde = strlen($desde) === 5 ? $desde . ':00' : $desde;
        $hasta = strlen($hasta) === 5 ? $hasta . ':00' : $hasta;

        return $q->where('hora_desde', '<', $hasta)
            ->where('hora_hasta', '>', $desde);
    }

    /** Futuro = fecha futura o hoy con hora_desde >= ahora */
    public function scopeFuturos($q)
    {
        $today = now()->toDateString();
        $now   = now()->format('H:i:s');

        return $q->where(function ($qq) use ($today, $now) {
            $qq->whereDate('fecha', '>', $today)
                ->orWhere(function ($qqq) use ($today, $now) {
                    $qqq->whereDate('fecha', $today)
                        ->where('hora_desde', '>=', $now);
                });
        });
    }

    /** Pasados = fecha pasada o hoy con hora_hasta < ahora */
    public function scopePasados($q)
    {
        $today = now()->toDateString();
        $now   = now()->format('H:i:s');

        return $q->where(function ($qq) use ($today, $now) {
            $qq->whereDate('fecha', '<', $today)
                ->orWhere(function ($qqq) use ($today, $now) {
                    $qqq->whereDate('fecha', $today)
                        ->where('hora_hasta', '<', $now);
                });
        });
    }

    /** Filtrar por estado puntual (útil en “Mis turnos”) */
    public function scopeEstado($q, string $estado)
    {
        return $q->where('estado', $estado);
    }

    /** --- Scopes para Agenda --- */
    public function scopeActivos($q)
    {
        return $q->whereNotIn('estado', [
            self::ESTADO_CANCELADO,
            self::ESTADO_CANCELADO_TARDE,
            self::ESTADO_ATENDIDO,
            self::ESTADO_NO_ASISTIO,
        ]);
    }

    public function scopeDelDia($q, \Illuminate\Support\Carbon|string $fecha)
    {
        $f = $fecha instanceof \Illuminate\Support\Carbon
            ? $fecha->toDateString()
            : \Illuminate\Support\Carbon::parse($fecha)->toDateString();

        return $q->whereDate('fecha', $f);
    }

    /** =========================================================
     * Accessors / Helpers de tiempo y duración
     * ========================================================= */
    public function getInicioAttribute(): ?Carbon
    {
        if (!$this->fecha || !$this->hora_desde) {
            return null;
        }

        $hora = $this->getRawOriginal('hora_desde') ?? (string) $this->hora_desde;
        return Carbon::parse($this->fecha->toDateString() . ' ' . $hora);
    }

    public function getFinAttribute(): ?Carbon
    {
        if (!$this->fecha || !$this->hora_hasta) {
            return null;
        }

        $hora = $this->getRawOriginal('hora_hasta') ?? (string) $this->hora_hasta;
        return Carbon::parse($this->fecha->toDateString() . ' ' . $hora);
    }

    public function getDuracionMinutosAttribute(): ?int
    {
        $ini = $this->inicio;
        $fin = $this->fin;

        return ($ini && $fin) ? $ini->diffInMinutes($fin) : null;
    }

    public function esFuturo(): bool
    {
        $ini = $this->inicio;
        return $ini ? $ini->greaterThanOrEqualTo(now()) : false;
    }

    /** Helpers de estado */
    public function esPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }
    public function esConfirmado(): bool
    {
        return $this->estado === self::ESTADO_CONFIRMADO;
    }
    public function esCancelado(): bool
    {
        return $this->estado === self::ESTADO_CANCELADO;
    }
    public function esCanceladoTarde(): bool
    {
        return $this->estado === self::ESTADO_CANCELADO_TARDE;
    }
    public function esAtendido(): bool
    {
        return $this->estado === self::ESTADO_ATENDIDO;
    }
    public function esNoAsistio(): bool
    {
        return $this->estado === self::ESTADO_NO_ASISTIO;
    }

    /** =========================================================
     * Acciones de negocio (agenda)
     * ========================================================= */
    public function marcarAtendido(): bool
    {
        if (!$this->fin || now()->lt($this->fin)) {
            return false;
        }
        return $this->update(['estado' => self::ESTADO_ATENDIDO]);
    }

    public function marcarNoAsistio(): bool
    {
        if (!$this->fin || now()->lt($this->fin)) {
            return false;
        }
        return $this->update(['estado' => self::ESTADO_NO_ASISTIO]);
    }

    /** =========================================================
     * Mutators: normalizan horas a "HH:mm:ss" al guardar
     * ========================================================= */
    protected function horaDesde(): Attribute
    {
        return Attribute::make(
            set: fn($v) => $v ? (strlen($v) === 5 ? $v . ':00' : $v) : null
        );
    }

    protected function horaHasta(): Attribute
    {
        return Attribute::make(
            set: fn($v) => $v ? (strlen($v) === 5 ? $v . ':00' : $v) : null
        );
    }

    /** =========================================================
     * Reglas sugeridas (para FormRequest/Livewire)
     * ========================================================= */
    public static function rules(): array
    {
        return [
            'profesional_id'      => ['required', 'exists:users,id'],
            'paciente_id'         => ['required', 'exists:users,id'],                 // legacy
            'paciente_perfil_id'  => ['nullable', 'exists:pacientes,paciente_id'],    // transición
            'id_consultorio'      => ['nullable', 'exists:consultorio,id_consultorio'],
            'fecha'               => ['required', 'date'],
            'hora_desde'          => ['required', 'date_format:H:i'],
            'hora_hasta'          => ['required', 'date_format:H:i', 'after:hora_desde'],
            'estado'              => ['required', 'in:pendiente,confirmado,cancelado,cancelado_tarde,atendido,no_asistio'],
            'motivo'              => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function rulesUnique(): array
    {
        return [
            'slot' => [function ($attr, $val, $fail) {
                if (!is_array($val) || count($val) !== 4) {
                    return;
                }
                [$prof, $fecha, $desde, $hasta] = $val;

                $desde = strlen($desde) === 5 ? $desde . ':00' : $desde;
                $hasta = strlen($hasta) === 5 ? $hasta . ':00' : $hasta;

                $ya = self::where('profesional_id', $prof)
                    ->whereDate('fecha', Carbon::parse($fecha)->toDateString())
                    ->where('hora_desde', $desde)
                    ->where('hora_hasta', $hasta)
                    ->exists();

                if ($ya) {
                    $fail('Ese horario se reservó recién. Elegí otro, por favor.');
                }
            }],
        ];
    }

    /** =========================================================
     * Lead time / Reglas de confirmación & cancelación (config/turnos.php)
     * ========================================================= */
    public static function leadMinutes(string $key, int $default): int
    {
        return (int) config("turnos.$key", $default);
    }

    public function puedeConfirmarAhora(): bool
    {
        if (!$this->esPendiente() || !$this->inicio) {
            return false;
        }
        $mins = now()->diffInMinutes($this->inicio, false);
        return $mins >= self::leadMinutes('confirm_min_minutes', 180);
    }

    public function puedeCancelarAhora(): bool
    {
        if ($this->esCancelado() || !$this->inicio) {
            return false;
        }
        $mins = now()->diffInMinutes($this->inicio, false);
        return $mins >= self::leadMinutes('cancel_min_minutes', 1440);
    }

    public function limiteConfirmacion(): ?Carbon
    {
        return $this->inicio?->copy()->subMinutes(self::leadMinutes('confirm_min_minutes', 180));
    }

    public function limiteCancelacion(): ?Carbon
    {
        return $this->inicio?->copy()->subMinutes(self::leadMinutes('cancel_min_minutes', 1440));
    }

    /**
     * Indica si esta cancelación se considera "temprana"
     * (>= minutos definidos en config/turnos.php para cancelación).
     */
    public function esCancelacionTemprana(): bool
    {
        $limite = $this->limiteCancelacion();

        if (! $limite) {
            return false;
        }

        return now()->lessThanOrEqualTo($limite);
    }
}
