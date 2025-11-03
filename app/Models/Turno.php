<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

class Turno extends Model
{
    /** ---------- Config básica de Eloquent ---------- */
    protected $table = 'turnos';
    protected $primaryKey = 'id_turno';
    public $incrementing = true;
    protected $keyType = 'int';

    /** ---------- Asignación masiva ---------- */
    protected $fillable = [
        'profesional_id',
        'paciente_id',
        'id_consultorio',   // FK a consultorio.id_consultorio (nullable)
        'fecha',            // date
        'hora_desde',       // time "H:i" o "H:i:s"
        'hora_hasta',       // time "H:i" o "H:i:s"
        'estado',           // pendiente|confirmado|cancelado|cancelado_tarde
        'motivo',           // nullable
    ];

    /** ---------- Casts ---------- */
    protected $casts = [
        'fecha' => 'date',
    ];

    /** ---------- Constantes de estado ---------- */
    public const ESTADO_PENDIENTE        = 'pendiente';
    public const ESTADO_CONFIRMADO       = 'confirmado';
    public const ESTADO_CANCELADO        = 'cancelado';
    public const ESTADO_CANCELADO_TARDE  = 'cancelado_tarde';

    /** ---------- Helpers de presentación (Filament) ---------- */
    public static function estadoColor(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_PENDIENTE        => 'warning',
            self::ESTADO_CONFIRMADO       => 'success',
            self::ESTADO_CANCELADO        => 'danger',
            self::ESTADO_CANCELADO_TARDE  => 'danger', // o 'warning' si preferís
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

    public function paciente()
    {
        return $this->belongsTo(User::class, 'paciente_id');
    }

    public function consultorio()
    {
        // PK personalizada en consultorio: id_consultorio
        return $this->belongsTo(Consultorio::class, 'id_consultorio', 'id_consultorio');
    }

    /** =========================================================
     * Scopes útiles
     * ========================================================= */
    public function scopeDeProfesional($q, int $profesionalId)
    {
        return $q->where('profesional_id', $profesionalId);
    }

    public function scopeDePaciente($q, int $pacienteId)
    {
        return $q->where('paciente_id', $pacienteId);
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
            'profesional_id' => ['required', 'exists:users,id'],
            'paciente_id'    => ['required', 'exists:users,id'],
            'id_consultorio' => ['nullable', 'exists:consultorio,id_consultorio'],
            'fecha'          => ['required', 'date'],
            'hora_desde'     => ['required', 'date_format:H:i'],
            'hora_hasta'     => ['required', 'date_format:H:i', 'after:hora_desde'],
            'estado'         => ['required', 'in:pendiente,confirmado,cancelado,cancelado_tarde'],
            'motivo'         => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Chequeo “optimista” adicional al UNIQUE de la BD;
     * útil para mostrar un mensaje claro antes de que dispare la excepción.
     */
    public static function rulesUnique(): array
    {
        return [
            'slot' => [function ($attr, $val, $fail) {
                if (!is_array($val) || count($val) !== 4) {
                    return;
                }
                [$prof, $fecha, $desde, $hasta] = $val;

                // Normalizo horas (HH:mm -> HH:mm:ss)
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

    /** ¿Puedo confirmar ahora (según lead time y estado)? */
    public function puedeConfirmarAhora(): bool
    {
        if (!$this->esPendiente() || ! $this->inicio) {
            return false;
        }
        // diffInMinutes negativo si ya pasó
        $mins = now()->diffInMinutes($this->inicio, false);
        return $mins >= self::leadMinutes('confirm_min_minutes', 180); // default 3h
    }

    /** ¿Puedo cancelar ahora (según lead time y estado)? */
    public function puedeCancelarAhora(): bool
    {
        if ($this->esCancelado() || ! $this->inicio) {
            return false;
        }
        $mins = now()->diffInMinutes($this->inicio, false);
        return $mins >= self::leadMinutes('cancel_min_minutes', 1440); // default 24h
    }

    /** Hora límite “amigable” para confirmar/cancelar (Carbon o null) */
    public function limiteConfirmacion(): ?Carbon
    {
        return $this->inicio?->copy()->subMinutes(self::leadMinutes('confirm_min_minutes', 180));
    }

    public function limiteCancelacion(): ?Carbon
    {
        return $this->inicio?->copy()->subMinutes(self::leadMinutes('cancel_min_minutes', 1440));
    }
}
