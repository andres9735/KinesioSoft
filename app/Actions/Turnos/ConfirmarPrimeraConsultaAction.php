<?php

namespace App\Actions\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\ClaveIdempotenciaIncompatibleException;
use App\Exceptions\Turnos\PrimeraConsultaActivaException;
use App\Exceptions\Turnos\ProfesionalNoElegibleException;
use App\Exceptions\Turnos\SlotNoDisponibleException;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use App\Services\Turnos\DisponibilidadPrimeraConsultaService;
use App\Services\Turnos\ElegibilidadKinesiologaService;
use App\Services\Turnos\ElegibilidadPrimeraConsultaService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ConfirmarPrimeraConsultaAction
{
    public function __construct(
        private readonly AgendaQueryService $agenda,
        private readonly ElegibilidadPrimeraConsultaService $d01,
        private readonly ElegibilidadKinesiologaService $elegibilidad,
        private readonly DisponibilidadPrimeraConsultaService $disponibilidad,
        private readonly RelojAplicacion $reloj,
        private readonly AuditoriaService $auditoria,
    ) {}

    public function ejecutar(
        User $actor,
        string $claveIdempotencia,
        ?int $kinesiologaId = null,
        ?CarbonInterface $inicioAt = null,
        ?string $motivoSolicitud = null,
        array $adjuntos = [],
    ): ResultadoConfirmacionPrimeraConsulta {
        if (! Str::isUuid($claveIdempotencia)) {
            throw new InvalidArgumentException('La clave de intención debe ser una UUID válida.');
        }

        $uuid = strtolower($claveIdempotencia);
        $paciente = $actor->paciente()->first();

        if ($paciente === null) {
            throw new InvalidArgumentException('El actor debe tener un paciente asociado.');
        }

        $existente = $this->porClave($uuid)->first();

        if ($existente !== null) {
            return $this->replay($existente, $paciente);
        }

        if ($adjuntos !== []) {
            throw new InvalidArgumentException('Esta confirmación todavía no admite adjuntos nuevos.');
        }

        if ($kinesiologaId === null || $inicioAt === null) {
            throw new InvalidArgumentException('Una creación nueva requiere profesional e inicio.');
        }

        try {
            return DB::transaction(function () use ($actor, $paciente, $uuid, $kinesiologaId, $inicioAt, $motivoSolicitud): ResultadoConfirmacionPrimeraConsulta {
                // Convenio canónico: kinesióloga -> paciente, incluso cuando no haya turnos previos.
                $kinesiologa = Kinesiologa::query()->whereKey($kinesiologaId)->lockForUpdate()->first();
                $propietario = Paciente::query()->whereKey($paciente->getKey())
                    ->where('user_id', $actor->getKey())->lockForUpdate()->first();

                if ($propietario === null) {
                    throw new InvalidArgumentException('El actor debe tener un paciente asociado.');
                }

                $existente = $this->porClave($uuid)->lockForUpdate()->first();

                if ($existente !== null) {
                    return $this->replay($existente, $propietario);
                }

                if ($this->d01->primerasConsultasFuturasActivasDe($propietario)->lockForUpdate()->get()->isNotEmpty()) {
                    throw new PrimeraConsultaActivaException('El paciente ya tiene una Primera consulta futura activa.');
                }

                if ($kinesiologa === null) {
                    throw new ProfesionalNoElegibleException('La profesional no cumple las condiciones para atender.');
                }

                $this->elegibilidad->prepararEvaluacionBloqueante($kinesiologa);
                $esElegible = $this->elegibilidad->esElegible($kinesiologa);

                if (! $esElegible) {
                    throw new ProfesionalNoElegibleException('La profesional no cumple las condiciones para atender.');
                }

                $inicio = CarbonImmutable::instance($inicioAt)->setTimezone(config('app.timezone'));
                $configuracion = $this->agenda->seleccionarConfiguracionAplicable(
                    $this->agenda->configuracionesAplicablesEnQuery($kinesiologa, $inicio)->lockForUpdate()->get()
                );

                if ($configuracion === null) {
                    throw new SlotNoDisponibleException('El slot seleccionado ya no está disponible.');
                }

                $configuracion->setRelation('franjasHorarias', $this->agenda->franjasDe($configuracion)->lockForUpdate()->get());
                $grilla = $this->disponibilidad->generarGrillaDesdeConfiguracion($configuracion, $inicio);
                $candidato = null;

                foreach ($grilla as $intervalo) {
                    if ($intervalo['inicio_at']->equalTo($inicio)) {
                        $candidato = $intervalo;
                        break;
                    }
                }

                if ($candidato === null) {
                    throw new SlotNoDisponibleException('El slot seleccionado ya no está disponible.');
                }

                $turnos = $this->agenda->turnosAfectadosPorIntervalo($kinesiologa, $candidato['inicio_at'], $candidato['fin_at'])
                    ->lockForUpdate()->get();
                $bloqueos = $this->agenda->bloqueosSuperpuestosCon($kinesiologa, $candidato['inicio_at'], $candidato['fin_at'])
                    ->lockForUpdate()->get();
                $disponibles = $this->disponibilidad->evaluarCandidatosDisponibles(
                    [$candidato], $turnos, $bloqueos, $this->reloj->ahora(), $esElegible
                );

                if ($disponibles === []) {
                    throw new SlotNoDisponibleException('El slot seleccionado ya no está disponible.');
                }

                if ($motivoSolicitud === null || trim($motivoSolicitud) === '' || mb_strlen($motivoSolicitud) > 500) {
                    throw new InvalidArgumentException('El motivo debe ser no vacío y tener como máximo 500 caracteres.');
                }

                $turno = new Turno;
                $turno->paciente()->associate($propietario);
                $turno->kinesiologa()->associate($kinesiologa);
                $turno->tipo = TipoTurnoEnum::PRIMERA_CONSULTA;
                $turno->estado = EstadoTurnoEnum::PENDIENTE;
                $turno->inicio_at = $candidato['inicio_at'];
                $turno->fin_at = $candidato['fin_at'];
                $turno->motivo_solicitud = $motivoSolicitud;
                $turno->clave_idempotencia = $uuid;
                $turno->save();

                $this->auditoria->registrar(
                    evento: 'turno_primera_consulta_creado',
                    modulo: 'Turnos',
                    auditable: $turno,
                    valoresNuevos: [
                        'turno_id' => $turno->getKey(),
                        'paciente_id' => $propietario->getKey(),
                        'kinesiologa_id' => $kinesiologa->getKey(),
                        'tipo' => $turno->tipo->value,
                        'estado' => $turno->estado->value,
                        'inicio_at' => $turno->inicio_at->toDateTimeString(),
                        'fin_at' => $turno->fin_at->toDateTimeString(),
                    ],
                    actor: $actor,
                );

                return new ResultadoConfirmacionPrimeraConsulta($turno, false);
            }, attempts: 1);
        } catch (QueryException $error) {
            if (! $this->esColisionClaveIdempotencia($error)) {
                throw $error;
            }

            // La transacción ya revirtió; no reintentar el callback ni ocultar otras violaciones.
            $existente = $this->porClave($uuid)->first();

            if ($existente === null) {
                throw $error;
            }

            return $this->replay($existente, $paciente);
        }
    }

    private function porClave(string $uuid): Builder
    {
        return Turno::query()->useWritePdo()->where('clave_idempotencia', $uuid);
    }

    private function replay(Turno $turno, Paciente $paciente): ResultadoConfirmacionPrimeraConsulta
    {
        if ((int) $turno->paciente_id !== (int) $paciente->getKey()
            || $turno->getRawOriginal('tipo') !== TipoTurnoEnum::PRIMERA_CONSULTA->value) {
            throw new ClaveIdempotenciaIncompatibleException('La clave de intención no puede utilizarse.');
        }

        return new ResultadoConfirmacionPrimeraConsulta($turno, true);
    }

    private function esColisionClaveIdempotencia(QueryException $error): bool
    {
        $info = $error->errorInfo ?? [];
        $estado = $info[0] ?? null;
        $codigo = (int) ($info[1] ?? 0);
        $detalle = (string) ($info[2] ?? '');

        if ($estado !== '23000') {
            return false;
        }

        return ($codigo === 1062 && preg_match('/\bfor key [\x27\x22\x60](?:turnos\.)?turnos_clave_idempotencia_unique[\x27\x22\x60]/i', $detalle) === 1)
            || (in_array($codigo, [19, 2067], true)
                && preg_match('/\AUNIQUE constraint failed: turnos\.clave_idempotencia\z/i', trim($detalle)) === 1);
    }
}
