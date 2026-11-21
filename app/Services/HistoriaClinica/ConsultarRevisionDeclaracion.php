<?php

namespace App\Services\HistoriaClinica;

use App\Data\HistoriaClinica\FuenteDeclaracionParaRevision;
use App\Models\DeclaracionSaludPaciente;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class ConsultarRevisionDeclaracion
{
    public const ETIQUETAS = ['antecedentes' => 'Antecedentes declarados sin clasificación', 'cirugias' => 'Cirugías',
        'alergias' => 'Alergias', 'medicacion' => 'Medicación habitual', 'objetivo_salud' => 'Objetivo de salud',
        'peso_kg' => 'Peso (kg)', 'altura_cm' => 'Altura (cm)', 'ocupacion' => 'Ocupación',
        'nivel_actividad_fisica' => 'Actividad física', 'detalle_actividad_fisica' => 'Detalle de actividad física'];

    public function __construct(private readonly ConsultarExpedienteDesdeTurnoService $expedientes) {}

    public function consultar(User $actor, int $turnoId, int $declaracionId): array
    {
        Gate::forUser($actor)->authorize('revisarDeclaracionSalud', [Turno::class, $turnoId]);
        $datos = $this->expedientes->consultar($actor, $turnoId);
        $declaracion = DeclaracionSaludPaciente::query()->useWritePdo()->with('items')
            ->where('paciente_id', $datos['paciente']->id)->findOrFail($declaracionId);
        abort_if($declaracion->estado !== DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION, 409,
            'La declaración ya no está pendiente. Volvé al expediente.');
        abort_if(in_array($datos['recorrido'], ['historia_inactiva', 'declaracion_revisada_sin_historia'], true), 409,
            'La situación del expediente requiere resolución antes de revisar la declaración.');
        $datos['versionBase']?->setRelation('historiaClinica', $datos['historia']);
        $datos['declaracion'] = $declaracion;
        $datos['fuente'] = FuenteDeclaracionParaRevision::desde($declaracion);
        $datos['especialidades'] = $datos['turno']->kinesiologa->especialidades()->where('activo', true)->orderBy('nombre')->get();

        return $datos;
    }
}
