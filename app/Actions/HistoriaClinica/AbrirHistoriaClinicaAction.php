<?php

namespace App\Actions\HistoriaClinica;

use App\Exceptions\HistoriaClinica\RecorridoAperturaModificadoException;
use App\Models\DeclaracionSaludPaciente;
use App\Models\HistoriaClinica;
use App\Models\Turno;
use App\Models\User;
use App\Models\VersionDatosClinicosBase;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use App\Services\GeneradorNumeroHistoriaClinica;
use App\Services\HistoriaClinica\ContextoAperturaHistoriaClinica;
use App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica;
use App\Services\Turnos\ElegibilidadKinesiologaService;
use App\Services\Turnos\TransaccionTurnoService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AbrirHistoriaClinicaAction
{
    public function __construct(
        private readonly TransaccionTurnoService $transacciones,
        private readonly ElegibilidadKinesiologaService $elegibilidad,
        private readonly ContextoAperturaHistoriaClinica $contextos,
        private readonly ValidarDatosAperturaHistoriaClinica $validador,
        private readonly GeneradorNumeroHistoriaClinica $numeros,
        private readonly AuditoriaService $auditoria,
        private readonly RelojAplicacion $reloj,
    ) {}

    public function ejecutar(User $actor, int $turnoId, string $contexto, array $entrada): HistoriaClinica
    {
        Gate::forUser($actor)->authorize('abrirHistoriaClinica', [Turno::class, $turnoId]);

        return $this->transacciones->ejecutar($turnoId, function (Turno $turno) use ($actor, $contexto, $entrada): HistoriaClinica {
            $this->elegibilidad->prepararEvaluacionBloqueante($turno->kinesiologa);
            Gate::forUser($actor)->authorize('abrirHistoriaClinica', [Turno::class, $turno->id]);
            $datos = $this->validador->validar($entrada);
            $clave = $this->contextos->verificar($contexto, $actor, $turno, (bool) $datos['familia']['incluir_contacto']);
            $huella = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));
            $existente = VersionDatosClinicosBase::query()->where('clave_apertura', $clave)->lockForUpdate()->first();
            if ($existente) {
                if ($existente->huella_solicitud !== $huella || ! $existente->historiaClinica->activa) {
                    throw new RecorridoAperturaModificadoException('La apertura ya se confirmó. Recuperá el expediente antes de continuar.');
                }

                return $existente->historiaClinica;
            }
            if (HistoriaClinica::query()->where('paciente_id', $turno->paciente_id)->lockForUpdate()->first()
                || DeclaracionSaludPaciente::query()->where('paciente_id', $turno->paciente_id)->lockForUpdate()->first()) {
                throw new RecorridoAperturaModificadoException('Cambió la situación del expediente o de la declaración. Consultá el recorrido actualizado.');
            }
            $especialidad = $turno->kinesiologa->especialidades()->where('especialidades_kinesiologia.id', $datos['especialidad_id'])
                ->where('activo', true)->lockForUpdate()->first();
            if (! $especialidad) {
                throw ValidationException::withMessages(['especialidad_id' => 'Seleccioná una especialidad vigente asociada a tu perfil.']);
            }
            $familia = $datos['familia'];
            if ($familia['incluir_contacto'] && ! $turno->paciente->contacto_emergencia_nombre) {
                throw ValidationException::withMessages(['familia.incluir_contacto' => 'El paciente no tiene un contacto de emergencia identificado para confirmar.']);
            }
            $ahora = $this->reloj->ahora();
            $historia = HistoriaClinica::create([
                'paciente_id' => $turno->paciente_id, 'nro_historia_clinica' => $this->numeros->generar($turno->paciente), 'activa' => true,
            ]);
            $historia->forceFill(['created_at' => $ahora, 'updated_at' => $ahora])->save();
            $version = VersionDatosClinicosBase::create([
                'historia_clinica_id' => $historia->id, 'kinesiologa_id' => $turno->kinesiologa_id,
                'autor_user_id' => $actor->id, 'autor_nombre_snapshot' => trim($turno->kinesiologa->user->name.' '.$turno->kinesiologa->user->apellido),
                'especialidad_id' => $especialidad->id, 'especialidad_nombre_snapshot' => $especialidad->nombre,
                'turno_origen_id' => $turno->id, 'numero_version' => 1, 'clave_apertura' => $clave,
                'huella_solicitud' => $huella, 'origen' => 'CARGA_PROFESIONAL_DIRECTA',
                'procedencia_relevamiento' => $datos['procedencia_relevamiento'],
                'relevamientos' => array_map(fn ($b) => $b['resultado'], $datos['bloques']),
                'resultado_nucleo_familiar' => $familia['resultado'], 'motivo_nucleo_familiar' => $familia['motivo'] ?? null,
                ...Arr::only($datos, ['objetivo_salud', 'peso_kg', 'altura_cm', 'ocupacion', 'nivel_actividad_fisica', 'detalle_actividad_fisica']),
                'created_at' => $ahora,
            ]);
            foreach ($datos['bloques'] as $tipo => $bloque) {
                foreach ($bloque['items'] as $item) {
                    $version->items()->create(['tipo' => $tipo, ...$item]);
                }
            }
            foreach ($familia['integrantes'] as $integrante) {
                $version->integrantes()->create([
                    ...$integrante, 'procedencia' => $datos['procedencia_relevamiento'], 'contacto_emergencia_confirmado' => false,
                ]);
            }
            if ($familia['incluir_contacto']) {
                $version->integrantes()->create([
                    'nombre_apellido' => $turno->paciente->contacto_emergencia_nombre, 'vinculo' => $familia['vinculo_contacto'],
                    'procedencia' => 'CONTACTO_EMERGENCIA_CONFIRMADO', 'contacto_emergencia_confirmado' => true,
                ]);
            }
            $this->auditoria->registrar('historia_clinica_abierta', 'Historia clínica', $historia,
                valoresNuevos: ['version_id' => $version->id, 'turno_id' => $turno->id, 'numero_version' => 1], actor: $actor);

            return $historia;
        });
    }
}
