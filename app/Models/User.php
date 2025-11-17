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

    protected string $guard_name = 'web';

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
        'specialty',   // <- campo string con la especialidad
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

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin'       => $this->hasRole('Administrador'),
            'paciente'    => $this->hasRole('Paciente'),
            'kinesiologa' => $this->hasAnyRole(['Kinesiologa', 'Administrador']),
            default       => false,
        };
    }

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

    public function scopeSoloKinesiologas($query)
    {
        return $query->role('Kinesiologa');
    }

    // Relaciones
    public function bloquesDisponibilidad()
    {
        return $this->hasMany(\App\Models\BloqueDisponibilidad::class, 'profesional_id');
    }
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
    public function especialidadPrincipal()
    {
        return $this->belongsToMany(\App\Models\Especialidad::class, 'especialidad_user')
            ->wherePivot('is_principal', true)
            ->limit(1);
    }

    /** ====== ACCESSOR: Nombre + Especialidad ====== */
    public function getNameWithSpecialtyAttribute(): string
    {
        $esp = trim((string) $this->specialty); // hoy tomamos del campo string
        // Si el día de mañana querés priorizar la principal desde la relación:
        // $esp = $this->especialidadPrincipal()->value('nombre') ?? $esp;

        return $esp !== '' ? "{$this->name} ({$esp})" : $this->name;
    }
}
