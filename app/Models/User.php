<?php

namespace App\Models;

use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

// 👇 Auditing
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class User extends Authenticatable implements AuditableContract
{
    use HasFactory, Notifiable, HasRoles;
    use Auditable; // 👈 habilita auditoría para User

    /** (opcional) No auditar estos campos en los diffs */
    protected array $auditExclude = ['password', 'remember_token'];

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

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin'    => $this->hasRole('Administrador'),
            'paciente' => $this->hasAnyRole(['Paciente', 'Kinesiologa', 'Administrador']),
            default    => false,
        };
    }
}
