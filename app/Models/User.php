<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'apellido',
        'email',
        'password',
        'dni',
        'fecha_nacimiento',
        'sexo',
        'telefono',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function paciente()
    {
        return $this->hasOne(Paciente::class);
    }

    public function kinesiologa()
    {
        return $this->hasOne(Kinesiologa::class);
    }

    public function esPaciente(): bool
    {
        return $this->hasRole(RoleEnum::PACIENTE->value);
    }

    public function esKinesiologo(): bool
    {
        return $this->hasRole(RoleEnum::KINESIOLOGO->value);
    }

    public function esAdministrador(): bool
    {
        return $this->hasRole(RoleEnum::ADMINISTRADOR->value);
    }

    public function esSuperadmin(): bool
    {
        return $this->hasRole(RoleEnum::SUPERADMIN->value);
    }

    public function tieneDatosPersonalesCompletos(): bool
    {
        return ! empty($this->dni)
            && ! empty($this->fecha_nacimiento)
            && ! empty($this->sexo)
            && ! empty($this->telefono);
    }

    public function tienePerfilPacienteCompleto(): bool
    {
        if (! $this->tieneDatosPersonalesCompletos()) {
            return false;
        }

        $paciente = $this->paciente;

        if (! $paciente) {
            return false;
        }

        $tieneDatosAdministrativosCompletos =
            $paciente->localidad_id !== null
            && trim((string) $paciente->calle) !== ''
            && trim((string) $paciente->altura_domicilio) !== ''
            && trim((string) $paciente->contacto_emergencia_nombre) !== ''
            && trim((string) $paciente->contacto_emergencia_telefono) !== '';

        if (! $tieneDatosAdministrativosCompletos) {
            return false;
        }

        return $paciente->declaracionesSalud()->exists();
    }

    public function tienePerfilProfesionalKinesiologiaCompleto(): bool
    {
        if (! $this->kinesiologa) {
            return false;
        }

        return ! empty($this->kinesiologa->titulo_profesional_id)
            && $this->kinesiologa->matriculasProfesionales()->where('activa', true)->exists()
            && $this->kinesiologa->especialidades()->exists();
    }

    public function perfilCompleto(): ?bool
    {
        if ($this->esPaciente()) {
            return $this->tienePerfilPacienteCompleto();
        }

        if ($this->esKinesiologo()) {
            return $this->tieneDatosPersonalesCompletos()
                && $this->tienePerfilProfesionalKinesiologiaCompleto();
        }

        return null;
    }

    public function nombreRolPrincipal(): string
    {
        return $this->roles->pluck('name')->implode(', ');
    }
}
