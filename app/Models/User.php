<?php

namespace App\Models;

use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;


class User extends Authenticatable implements AuditableContract
{
    use HasFactory, Notifiable, HasRoles;
    use Auditable;

    /**
     * @property int $id
     * @property string $name
     * @property string $email
     *
     * Métodos inyectados por Spatie\Permission\Traits\HasRoles:
     * @method bool hasRole(string|array $roles)
     * @method bool hasAnyRole(string ...$roles)
     */

    /**
     * Spatie Permission guard (por defecto "web").
     * Útil si en el futuro tenés múltiples guards.
     */
    protected string $guard_name = 'web';

    /** No auditar estos campos en los diffs */
    protected array $auditExclude = [
        'password',
        'remember_token',
        'last_login_at',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'dni',
        'address',
        'specialty',
        'is_active',
        'last_login_at',
        'rating_avg',
        'rating_count'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'last_login_at'     => 'datetime',
            'is_active'         => 'boolean',
        ];
    }

    /**
     * Controla a qué paneles puede acceder cada usuario según su rol.
     */
    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin'       => $this->hasRole('Administrador'),
            'paciente'    => $this->hasRole('Paciente'),
            'kinesiologa' => $this->hasAnyRole(['Kinesiologa', 'Administrador']),
            default       => false,
        };
    }

    /** ---------------- Helpers de rol (azúcar sintáctico) ---------------- */
    public function isAdmin(): bool
    {
        return $this->hasRole('Administrador');
    }

    public function isKinesiologa(): bool
    {
        return $this->hasRole('Kinesiologa');
    }

    public function isPaciente(): bool
    {
        return $this->hasRole('Paciente');
    }

    /** ---------------- Scopes útiles ---------------- */
    public function scopeSoloKinesiologas($query)
    {
        return $query->role('Kinesiologa');
    }

    /** ---------------- Relaciones ---------------- */

    // ⏰ Bloques de disponibilidad (horarios semanales)
    public function bloquesDisponibilidad()
    {
        return $this->hasMany(\App\Models\BloqueDisponibilidad::class, 'profesional_id');
    }

    // 🚫 Excepciones (feriados, licencias, etc.)
    public function excepcionesDisponibilidad()
    {
        return $this->hasMany(\App\Models\ExcepcionDisponibilidad::class, 'profesional_id');
    }

    public function especialidades()
    {
        return $this->belongsToMany(\App\Models\Especialidad::class, 'especialidad_user')
            ->withPivot('is_principal')
            ->withTimestamps();
    }

    /** Azúcar: obtener la principal (si existe) */
    public function especialidadPrincipal()
    {
        return $this->belongsToMany(\App\Models\Especialidad::class, 'especialidad_user')
            ->wherePivot('is_principal', true)
            ->limit(1);
    }
}
