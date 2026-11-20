<?php

namespace App\Policies;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TurnoPolicy
{
    public const PERMISO_CONSULTAR_HISTORIA_CLINICA = 'historias-clinicas.consultar';

    public const PERMISO_ABRIR_HISTORIA_CLINICA = 'historias-clinicas.abrir';

    public function abrirHistoriaClinica(User $user, int $turnoId): bool
    {
        return $this->consultarHistoriaClinica($user, $turnoId)
            && User::query()->useWritePdo()->find($user->getAuthIdentifier())
                ?->checkPermissionTo(self::PERMISO_ABRIR_HISTORIA_CLINICA, 'web') === true;
    }

    public function consultarHistoriaClinica(User $user, int $turnoId): bool
    {
        if (! $user->exists) {
            return false;
        }

        // El guard puede conservar atributos y relaciones anteriores a una revocación.
        $actual = User::query()->useWritePdo()->find($user->getAuthIdentifier());

        return $actual !== null
            && $actual->puedeAtenderComoKinesiologo()
            && $actual->checkPermissionTo(self::PERMISO_CONSULTAR_HISTORIA_CLINICA, 'web')
            && Turno::query()->useWritePdo()->whereKey($turnoId)
                ->whereHas('kinesiologa', fn (Builder $query) => $query->where('user_id', $actual->getKey()))
                ->exists();
    }

    public function view(User $user, Turno $turno): bool
    {
        return $this->esPropietario($user, $turno);
    }

    // Estos contratos autorizan al actor sobre el recurso. Las Actions posteriores
    // deberán revalidar estado, tiempo y transición; la pertenencia no los sustituye.
    public function confirmar(User $user, Turno $turno): bool
    {
        return $this->esPropietario($user, $turno);
    }

    public function cancelar(User $user, Turno $turno): bool
    {
        return $this->esPropietario($user, $turno);
    }

    private function esPropietario(User $user, Turno $turno): bool
    {
        // Las precondiciones de cuenta, verificación y perfil completo siguen en
        // los middleware existentes. Aquí se exige rol y propiedad persistida.
        if (! $user->exists || ! $turno->exists || ! $user->esPaciente()) {
            return false;
        }

        return Turno::query()->whereKey($turno->getKey())
            ->whereHas('paciente', fn (Builder $paciente) => $paciente->where('user_id', $user->getKey()))
            ->exists();
    }
}
