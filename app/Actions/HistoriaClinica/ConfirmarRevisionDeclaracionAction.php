<?php

namespace App\Actions\HistoriaClinica;

use App\Data\HistoriaClinica\FuenteDeclaracionParaRevision as Fuente;
use App\Exceptions\HistoriaClinica\ConflictoRevisionDeclaracionException as Conflicto;
use App\Models\ConfirmacionRevisionDeclaracion;
use App\Models\DeclaracionSaludPaciente;
use App\Models\HistoriaClinica;
use App\Models\IncorporacionDeclaracionSalud;
use App\Models\Turno;
use App\Models\User;
use App\Models\VersionDatosClinicosBase;
use App\Policies\TurnoPolicy;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use App\Services\GeneradorNumeroHistoriaClinica;
use App\Services\HistoriaClinica\ComprobanteVistaPreviaRevision;
use App\Services\HistoriaClinica\ContextoRevisionDeclaracion;
use App\Services\HistoriaClinica\PrepararResultadoRevisionDeclaracion;
use App\Services\HistoriaClinica\ResolverIntegrantesRevision;
use App\Services\Turnos\ElegibilidadKinesiologaService;
use App\Services\Turnos\TransaccionTurnoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Permission\Models\Permission;

final class ConfirmarRevisionDeclaracionAction
{
    public function __construct(
        private readonly TransaccionTurnoService $transacciones,
        private readonly ElegibilidadKinesiologaService $elegibilidad,
        private readonly ComprobanteVistaPreviaRevision $comprobantes,
        private readonly ContextoRevisionDeclaracion $contextos,
        private readonly PrepararResultadoRevisionDeclaracion $preparador,
        private readonly ResolverIntegrantesRevision $integrantes,
        private readonly GeneradorNumeroHistoriaClinica $numeros,
        private readonly RelojAplicacion $reloj,
        private readonly AuditoriaService $auditoria,
    ) {}

    public function ejecutar(User $actor, int $turnoId, int $declaracionId, string $comprobante): array
    {
        Gate::forUser($actor)->authorize('revisarDeclaracionSalud', [Turno::class, $turnoId]);
        $sobre = $this->comprobantes->leer($comprobante, $actor, $turnoId, $declaracionId);

        return $this->transacciones->ejecutar($turnoId, function (Turno $turno) use ($actor, $declaracionId, $sobre): array {
            $this->elegibilidad->prepararEvaluacionBloqueante($turno->kinesiologa);
            if (! $turno->kinesiologa->user) {
                throw new AuthorizationException;
            }
            $this->bloquearPermisos($turno->kinesiologa->user);
            Gate::forUser($actor)->authorize('revisarDeclaracionSalud', [Turno::class, $turno->id]);
            if ((int) $turno->paciente_id !== $sobre['paciente'] || (int) $turno->kinesiologa_id !== $sobre['profesional']) {
                throw new Conflicto;
            }
            // Tras el prefijo K -> P -> T: cabecera, declaraciones por ID, versión e ítems.
            // El mutex del paciente también serializa distintas profesionales y turnos.
            $historia = HistoriaClinica::query()->where('paciente_id', $turno->paciente_id)->lockForUpdate()->first();
            $declaraciones = DeclaracionSaludPaciente::query()->where('paciente_id', $turno->paciente_id)->orderBy('id')->lockForUpdate()->get();
            $declaracion = $declaraciones->firstWhere('id', $declaracionId);
            if (! $declaracion || ($historia && ! $historia->activa)
                || (! $historia && $declaraciones->contains('estado', DeclaracionSaludPaciente::ESTADO_REVISADA))) {
                throw new Conflicto;
            }
            $declaracion->setRelation('items', $declaracion->items()->orderBy('id')->lockForUpdate()->get());
            $base = $historia?->versionesDatosBase()->orderByDesc('numero_version')->lockForUpdate()->first();
            if ($base) {
                $base->setRelation('historiaClinica', $historia);
                $base->setRelation('items', $base->items()->lockForUpdate()->get());
                $base->setRelation('integrantes', $base->integrantes()->lockForUpdate()->get());
            }
            $existente = ConfirmacionRevisionDeclaracion::query()->where('declaracion_salud_id', $declaracionId)->lockForUpdate()->first();
            if ($existente) {
                return $this->recuperar($existente, $declaracion, $historia, $actor, $turno, $sobre);
            }
            if ($declaracion->estado !== DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION) {
                throw new Conflicto('declaracion');
            }
            $especialidades = $turno->kinesiologa->especialidades()->where('activo', true)
                ->orderBy('especialidades_kinesiologia.id')->lockForUpdate()->get();
            $datosContexto = ['turno' => $turno, 'paciente' => $turno->paciente, 'historia' => $historia, 'versionBase' => $base,
                'fuente' => $this->fuente($declaracion), 'especialidades' => $especialidades];
            try {
                $this->contextos->verificar($sobre['contexto'], $actor, $datosContexto);
            } catch (ValidationException) {
                throw new Conflicto('version');
            }
            $resultado = $this->preparador->preparar($datosContexto['fuente'], $sobre['entrada'], $base);
            $especialidad = $especialidades->firstWhere('id', $resultado->datosClinicos['especialidad_id']);
            if (! $especialidad) {
                throw new Conflicto('autor');
            }
            if (! hash_equals($sobre['huella_declaracion'], $resultado->huellaDeclaracion)
                || ! hash_equals($sobre['huella_resultado'], $resultado->huellaResultado)
                || ! hash_equals($sobre['huella_vista'], $this->comprobantes->huellaVista($datosContexto, $resultado))) {
                throw new Conflicto('propuesta');
            }
            if (($resultado->datosClinicos['familia']['incluir_contacto'] ?? false) && ! $turno->paciente->contacto_emergencia_nombre) {
                throw ValidationException::withMessages(['familia' => 'No hay un contacto identificado para incorporar.']);
            }
            $ahora = $this->reloj->ahora();
            $nueva = $historia === null;
            if ($nueva) {
                $historia = HistoriaClinica::create(['paciente_id' => $turno->paciente_id,
                    'nro_historia_clinica' => $this->numeros->generar($turno->paciente), 'activa' => true]);
                $historia->forceFill(['created_at' => $ahora, 'updated_at' => $ahora])->save();
            }
            // Una cabecera preexistente conserva número, fecha y demás atributos.
            $datos = $resultado->datosClinicos;
            $autor = ['kinesiologa_id' => $turno->kinesiologa_id, 'autor_user_id' => $actor->id,
                'especialidad_id' => $especialidad->id, 'autor_nombre_snapshot' => trim($turno->kinesiologa->user->name.' '.$turno->kinesiologa->user->apellido),
                'especialidad_nombre_snapshot' => $especialidad->nombre, 'turno_origen_id' => $turno->id];
            $version = VersionDatosClinicosBase::create([...$autor, 'historia_clinica_id' => $historia->id,
                'numero_version' => ($base?->numero_version ?? 0) + 1, 'clave_apertura' => null,
                'huella_solicitud' => $resultado->huellaResultado, 'origen' => 'REVISION_DECLARACION',
                'procedencia_relevamiento' => $datos['procedencia_relevamiento'], 'relevamientos' => array_map(fn ($b) => $b['resultado'], $datos['bloques']),
                'resultado_nucleo_familiar' => $datos['familia']['resultado'], 'motivo_nucleo_familiar' => $datos['familia']['motivo'] ?? null,
                ...Arr::only($datos, Fuente::CAMPOS), 'anamnesis_fisica' => $base?->anamnesis_fisica,
                'motivo_actualizacion' => 'Revisión de declaración de salud', 'created_at' => $ahora]);
            foreach ($datos['bloques'] as $tipo => $bloque) {
                foreach ($bloque['items'] as $item) {
                    $version->items()->create(['tipo' => $tipo, ...$item]);
                }
            }
            foreach ($this->integrantes->resolver($resultado, $turno->paciente) as $integrante) {
                $version->integrantes()->create($integrante);
            }
            $confirmacion = ConfirmacionRevisionDeclaracion::create([...$autor, 'historia_clinica_id' => $historia->id,
                'paciente_id' => $turno->paciente_id, 'declaracion_salud_id' => $declaracionId, 'version_base_id' => $base?->id,
                'clave_confirmacion' => $sobre['clave'], 'huella_declaracion' => $resultado->huellaDeclaracion,
                'huella_resultado' => $resultado->huellaResultado, 'linaje_resultado' => $resultado->linaje,
                'accion' => ConfirmacionRevisionDeclaracion::ACCION, 'confirmada_at' => $ahora]);
            foreach ($resultado->dictamenes as $dictamen) {
                $confirmacion->dictamenes()->create([...$dictamen, 'declaracion_salud_id' => $declaracionId,
                    'kinesiologa_id' => $turno->kinesiologa_id, 'created_at' => $ahora]);
            }
            IncorporacionDeclaracionSalud::create(['version_datos_clinicos_base_id' => $version->id,
                'declaracion_salud_id' => $declaracionId, 'confirmacion_revision_id' => $confirmacion->id, 'historia_clinica_id' => $historia->id]);
            $declaracion->update(['estado' => DeclaracionSaludPaciente::ESTADO_REVISADA]);
            if ($nueva) {
                $this->auditoria->registrar('historia_clinica_abierta', 'Historia clínica', $historia,
                    valoresNuevos: ['version_id' => $version->id, 'turno_id' => $turno->id, 'numero_version' => 1], actor: $turno->kinesiologa->user);
            }
            $this->auditoria->registrar('declaracion_salud_incorporada', 'Historia clínica', $confirmacion,
                valoresNuevos: ['historia_id' => $historia->id, 'declaracion_id' => $declaracionId, 'version_id' => $version->id,
                    'version_base_id' => $base?->id, 'numero_version' => $version->numero_version, 'turno_id' => $turno->id,
                    'huella_resultado' => $resultado->huellaResultado], actor: $turno->kinesiologa->user);

            return compact('historia', 'version', 'confirmacion') + ['yaProcesada' => false];
        });
    }

    private function recuperar(ConfirmacionRevisionDeclaracion $confirmacion, DeclaracionSaludPaciente $declaracion,
        ?HistoriaClinica $historia, User $actor, Turno $turno, array $sobre): array
    {
        if ($declaracion->estado !== DeclaracionSaludPaciente::ESTADO_REVISADA
            || $confirmacion->clave_confirmacion !== $sobre['clave'] || (int) $confirmacion->autor_user_id !== (int) $actor->id
            || (int) $confirmacion->turno_origen_id !== (int) $turno->id || (int) $confirmacion->historia_clinica_id !== $historia?->id
            || ! hash_equals($confirmacion->huella_resultado, $sobre['huella_resultado'])
            || ! hash_equals($confirmacion->huella_declaracion, $this->fuente($declaracion)->huella)) {
            throw new Conflicto('declaracion');
        }
        $incorporacion = $confirmacion->incorporacion()->lockForUpdate()->first();
        $version = $incorporacion?->version()->lockForUpdate()->first();
        if (! $version || (int) $version->historia_clinica_id !== (int) $historia->id
            || ! hash_equals($version->huella_solicitud, $confirmacion->huella_resultado)) {
            throw new Conflicto;
        }

        return compact('historia', 'version', 'confirmacion') + ['yaProcesada' => true];
    }

    private function fuente(DeclaracionSaludPaciente $declaracion): Fuente
    {
        try {
            return $declaracion->estado === DeclaracionSaludPaciente::ESTADO_REVISADA
                ? Fuente::desdeRevisada($declaracion) : Fuente::desde($declaracion);
        } catch (LogicException) {
            throw new Conflicto('declaracion');
        }
    }

    private function bloquearPermisos(User $actor): void
    {
        // Releer las concesiones Spatie bajo lock, aun con cachés de permisos previas.
        $directos = $actor->permissions()->newPivotQuery()->orderBy('permission_id')->lockForUpdate()->get()->pluck('permission_id');
        $ids = $directos;
        foreach ($actor->roles->sortBy('id') as $rol) {
            $ids = $ids->merge($rol->permissions()->newPivotQuery()->orderBy('permission_id')->lockForUpdate()->get()->pluck('permission_id'));
        }
        $nombres = Permission::query()->whereIn('id', $ids->unique())->orderBy('id')->lockForUpdate()->get()
            ->where('guard_name', 'web')->pluck('name');
        if (! $nombres->contains(TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA) || ! $nombres->contains(TurnoPolicy::PERMISO_REVISAR_DECLARACION)) {
            throw new AuthorizationException;
        }
    }
}
