<?php

namespace App\Services\HistoriaClinica;

use App\Models\DeclaracionSaludPaciente;
use App\Models\HistoriaClinica;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

final class ConsultarExpedienteDesdeTurnoService
{
    public function __construct(private readonly ResolverRecorridoHistoriaClinica $recorridos) {}

    /** Lectura de UC-14, sin apertura, incorporación, auditoría ni inicio de atención. */
    public function consultar(User $actor, int $turnoId): array
    {
        Gate::forUser($actor)->authorize('consultarHistoriaClinica', [Turno::class, $turnoId]);

        // Releer el turno registrado y mantener el ámbito aun si cambió tras autorizar.
        // Nunca recibir paciente_id ni kinesiologa_id desde la petición.
        $turno = Turno::query()->useWritePdo()->whereKey($turnoId)
            ->whereHas('kinesiologa', fn (Builder $query) => $query->where('user_id', $actor->getAuthIdentifier()))
            ->firstOrFail();
        $paciente = $turno->paciente()->with('user:id,name,apellido')->firstOrFail();
        $historia = HistoriaClinica::query()->useWritePdo()->where('paciente_id', $paciente->id)->first();
        $estados = DeclaracionSaludPaciente::query()->useWritePdo()
            ->where('paciente_id', $paciente->id)->pluck('estado');
        $declaracionPendiente = $estados->contains(DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION);

        // Una cabecera histórica sigue sin acreditar disponibilidad. La tabla
        // puede no estar desplegada todavía; su ausencia no rompe la consulta.
        $versionBase = $historia && Schema::hasTable('versiones_datos_clinicos_base')
            ? $historia->versionesDatosBase()->useWritePdo()->with('items', 'integrantes')->orderByDesc('numero_version')->first()
            : null;
        $datosBaseDisponibles = $versionBase ? true : null;

        return [
            'turno' => $turno,
            'paciente' => $paciente,
            'historia' => $historia,
            'versionBase' => $versionBase,
            'declaracionPendiente' => $declaracionPendiente,
            'datosBaseDisponibles' => $datosBaseDisponibles,
            'recorrido' => $this->recorridos->resolver(
                $historia?->activa,
                $declaracionPendiente,
                $estados->contains(DeclaracionSaludPaciente::ESTADO_REVISADA),
                $datosBaseDisponibles,
            ),
        ];
    }
}
