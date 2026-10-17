<?php

namespace App\Actions\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\AlmacenamientoDocumentalException;
use App\Exceptions\Turnos\PrimeraConsultaActivaException;
use App\Exceptions\Turnos\ProfesionalNoElegibleException;
use App\Exceptions\Turnos\ResultadoConfirmacionInciertoException;
use App\Exceptions\Turnos\SlotNoDisponibleException;
use App\Models\DocumentoClinico;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use App\Services\Turnos\AlmacenamientoDocumentosClinicosService;
use App\Services\Turnos\DisponibilidadPrimeraConsultaService;
use App\Services\Turnos\Documentos\OperacionAlmacenamientoDocumental;
use App\Services\Turnos\ElegibilidadKinesiologaService;
use App\Services\Turnos\ElegibilidadPrimeraConsultaService;
use App\Services\Turnos\GestionDocumentoClinicoService;
use App\Services\Turnos\ReplayPrimeraConsultaService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use PDO;
use RuntimeException;
use Throwable;

final class ConfirmarPrimeraConsultaAction
{
    public function __construct(
        private readonly AgendaQueryService $agenda,
        private readonly ElegibilidadPrimeraConsultaService $d01,
        private readonly ElegibilidadKinesiologaService $elegibilidad,
        private readonly DisponibilidadPrimeraConsultaService $disponibilidad,
        private readonly RelojAplicacion $reloj,
        private readonly AuditoriaService $auditoria,
        private readonly AlmacenamientoDocumentosClinicosService $almacenamiento,
        private readonly GestionDocumentoClinicoService $documentos,
        private readonly ReplayPrimeraConsultaService $replays,
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

        $existente = $this->replays->resolver($paciente, $uuid);

        if ($existente !== null) {
            return new ResultadoConfirmacionPrimeraConsulta($existente, true);
        }

        if ($kinesiologaId === null || $inicioAt === null) {
            throw new InvalidArgumentException('Una creación nueva requiere profesional e inicio.');
        }

        $operacion = null;
        $sesion = null;
        try {
            $resultado = DB::transaction(function () use ($actor, $paciente, $uuid, $kinesiologaId, $inicioAt, $motivoSolicitud, $adjuntos, &$operacion, &$sesion): ResultadoConfirmacionPrimeraConsulta {
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

                if ($adjuntos !== []) {
                    // Un savepoint no confirma el commit: el ciclo documental debe pertenecer a esta Action.
                    if (DB::transactionLevel() !== 1) {
                        throw new LogicException('La confirmación documental requiere una transacción propia, sin transacción exterior.');
                    }
                    $sesion = DB::connection()->getPdo();
                    $operacion = $this->almacenamiento->almacenar($adjuntos);
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

                foreach ($operacion?->documentos() ?? [] as $metadata) {
                    $this->documentos->registrarOrigenTurno($actor, $propietario, $turno, $metadata);
                }

                return new ResultadoConfirmacionPrimeraConsulta($turno, false);
            }, attempts: 1);
        } catch (Throwable $error) {
            if ($operacion !== null) {
                return $this->resolverFalloDocumental($error, $operacion, $sesion, $uuid, $paciente, $actor);
            }
            if (! $error instanceof QueryException || ! $this->esColisionClaveIdempotencia($error)) {
                throw $error;
            }

            // La transacción ya revirtió; no reintentar el callback ni ocultar otras violaciones.
            $existente = $this->porClave($uuid)->first();

            if ($existente === null) {
                throw $error;
            }

            return $this->replay($existente, $paciente);
        }

        // Fuera del catch transaccional: una falla de release nunca debe compensar un commit confirmado.
        $operacion?->conservarYLiberar();

        return $resultado;
    }

    private function resolverFalloDocumental(
        Throwable $original,
        OperacionAlmacenamientoDocumental $operacion,
        PDO $sesion,
        string $uuid,
        Paciente $paciente,
        User $actor,
    ): ResultadoConfirmacionPrimeraConsulta {
        try {
            [$turno, $consolidado, $sinReferencias] = $this->comprobarConsolidacion($sesion, $uuid, $paciente, $actor, $operacion);
        } catch (Throwable $verificacion) {
            $this->preservarResultadoIncierto($operacion, $original, $verificacion);
        }

        if ($consolidado) {
            $this->finalizarConCausa($operacion, false, $original);

            return $this->replay($turno, $paciente);
        }

        $colisionUuid = $original instanceof QueryException && $this->esColisionClaveIdempotencia($original);
        if ($sinReferencias && ($turno === null || $colisionUuid)) {
            $this->finalizarConCausa($operacion, true, $original);
            if ($colisionUuid && $turno !== null) {
                return $this->replay($turno, $paciente);
            }
            throw $original;
        }

        $this->preservarResultadoIncierto($operacion, $original);
    }

    /** @return array{?Turno, bool, bool} */
    private function comprobarConsolidacion(PDO $sesion, string $uuid, Paciente $paciente, User $actor, OperacionAlmacenamientoDocumental $operacion): array
    {
        if (DB::connection()->getRawPdo() !== $sesion || DB::transactionLevel() !== 0 || $sesion->inTransaction()) {
            throw new RuntimeException('La sesión de escritura no permite verificar un resultado consolidado.');
        }

        // Ejecutar SQL construido por Eloquent sobre el PDO exacto: sin réplica, snapshot activo ni reconexión automática.
        $fila = $this->leerSinReconectar($sesion, $this->porClave($uuid))[0] ?? null;
        $turno = $fila === null ? null : (new Turno)->newFromBuilder($fila);
        $metadata = array_values($operacion->documentos());
        $referencias = $this->leerSinReconectar($sesion, DocumentoClinico::query()
            ->whereIn('ruta_storage', array_column($metadata, 'ruta_storage')));
        $consolidado = false;

        if ($turno !== null && (int) $turno->paciente_id === (int) $paciente->getKey()
            && $turno->getRawOriginal('tipo') === TipoTurnoEnum::PRIMERA_CONSULTA->value) {
            $asociados = $this->leerSinReconectar($sesion, DocumentoClinico::query()
                ->join('documentos_clinicos_origen_turno as origen_turno', 'origen_turno.documento_clinico_id', '=', 'documentos_clinicos.id')
                ->where('origen_turno.turno_id', $turno->getKey())->select('documentos_clinicos.*'));
            $porRuta = array_column($asociados, null, 'ruta_storage');
            $consolidado = count($asociados) === count($metadata) && count($referencias) === count($metadata);
            foreach ($metadata as $esperado) {
                $documento = $porRuta[$esperado['ruta_storage']] ?? [];
                $consolidado = $consolidado
                    && (int) ($documento['paciente_id'] ?? 0) === (int) $paciente->getKey()
                    && (int) ($documento['cargado_por_user_id'] ?? 0) === (int) $actor->getKey()
                    && ($documento['origen'] ?? null) === DocumentoClinico::ORIGEN_SOLICITUD_TURNO
                    && ($documento['nombre_original'] ?? null) === $esperado['nombre_original']
                    && ($documento['mime_type'] ?? null) === $esperado['mime_type']
                    && (int) ($documento['tamanio_bytes'] ?? -1) === $esperado['tamanio_bytes'];
            }
        }

        return [$turno, $consolidado, $referencias === []];
    }

    /** @return list<array<string, mixed>> */
    private function leerSinReconectar(PDO $sesion, Builder $query): array
    {
        $consulta = $sesion->prepare($query->toSql());
        if ($consulta === false || ! $consulta->execute($query->getBindings())) {
            throw new RuntimeException('No se pudo verificar la consolidación.');
        }

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    private function finalizarConCausa(OperacionAlmacenamientoDocumental $operacion, bool $compensar, Throwable $original): void
    {
        try {
            $compensar ? $operacion->compensarYLiberar() : $operacion->conservarYLiberar();
        } catch (AlmacenamientoDocumentalException $finalizacion) {
            throw new AlmacenamientoDocumentalException(
                'Falló la finalización documental después de la confirmación.',
                $original,
                erroresEliminacion: $finalizacion->erroresEliminacion,
                erroresLiberacion: $finalizacion->erroresLiberacion,
            );
        }
    }

    private function preservarResultadoIncierto(OperacionAlmacenamientoDocumental $operacion, Throwable $original, ?Throwable $verificacion = null): never
    {
        $finalizacion = null;
        try {
            $operacion->conservarYLiberar();
        } catch (Throwable $error) {
            $finalizacion = $error;
        }
        throw new ResultadoConfirmacionInciertoException($original, $verificacion, $finalizacion);
    }

    private function porClave(string $uuid): Builder
    {
        return $this->replays->porClave($uuid);
    }

    private function replay(Turno $turno, Paciente $paciente): ResultadoConfirmacionPrimeraConsulta
    {
        $this->replays->validarContexto($turno, $paciente);

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
