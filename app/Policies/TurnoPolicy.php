<?php

namespace App\Policies;

use App\Models\Turno;
use App\Models\User;

class TurnoPolicy
{
    /**
     * Determine whether the user can view any turnos.
     */
    public function viewAny(User $user): bool
    {
        // Admin y Kinesiologa pueden listar turnos, Paciente también sus propios
        return $user->hasAnyRole(['Administrador', 'Kinesiologa', 'Paciente']);
    }

    /**
     * Determine whether the user can view a specific turno.
     */
    public function view(User $user, Turno $turno): bool
    {
        return $user->hasRole('Administrador')
            || ($user->hasRole('Kinesiologa') && $user->id === $turno->profesional_id)
            || ($user->hasRole('Paciente') && $user->id === $turno->paciente_id);
    }

    /**
     * Determine whether the user can create turnos.
     */
    public function create(User $user): bool
    {
        // Solo Administrador o Paciente (para agendarse)
        return $user->hasAnyRole(['Administrador', 'Paciente']);
    }

    /**
     * Determine whether the user can update a turno.
     */
    public function update(User $user, Turno $turno): bool
    {
        return $user->hasRole('Administrador')
            || ($user->hasRole('Kinesiologa') && $user->id === $turno->profesional_id)
            || ($user->hasRole('Paciente') && $user->id === $turno->paciente_id);
    }

    /**
     * Determine whether the user can delete a turno.
     */
    public function delete(User $user, Turno $turno): bool
    {
        // Admin puede borrar todo, Kinesiologa o Paciente solo los suyos
        return $user->hasRole('Administrador')
            || ($user->hasRole('Kinesiologa') && $user->id === $turno->profesional_id)
            || ($user->hasRole('Paciente') && $user->id === $turno->paciente_id);
    }

    /**
     * Determine whether the user can restore the turno.
     */
    public function restore(User $user, Turno $turno): bool
    {
        return $user->hasRole('Administrador');
    }

    /**
     * Determine whether the user can permanently delete the turno.
     */
    public function forceDelete(User $user, Turno $turno): bool
    {
        return $user->hasRole('Administrador');
    }
}
