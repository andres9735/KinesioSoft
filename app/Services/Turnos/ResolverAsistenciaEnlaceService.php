<?php

namespace App\Services\Turnos;

use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarAsistenciaTurnoAction;
use App\Enums\CanalAutogestionTurnoEnum;
use App\Exceptions\Turnos\AccesoEnlaceRechazadoException;
use App\Models\Auditoria;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\Turno;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use LogicException;
use SensitiveParameter;

final class ResolverAsistenciaEnlaceService
{
    public function __construct(
        private TransaccionTurnoService $transacciones,
        private ConfirmarAsistenciaTurnoAction $confirmar,
        private CancelarTurnoAction $cancelar,
        private RelojAplicacion $reloj,
        private AuditoriaService $auditoria,
    ) {}

    /** Los datos proceden exclusivamente de una cookie descifrada y autenticada en servidor. */
    public function ejecutar(#[SensitiveParameter] array $autorizacion, string $accion, ?string $operacion = null): array
    {
        if (! in_array($accion, ['confirmar', 'cancelar'], true)) {
            throw new AccesoEnlaceRechazadoException('decision_invalida');
        }
        if ($operacion !== null && (! Str::isUuid($operacion) || ($autorizacion['operaciones'][$accion] ?? null) !== $operacion)) {
            throw new AccesoEnlaceRechazadoException;
        }
        $referencia = EnlaceAsistenciaTurno::query()->useWritePdo()->find($autorizacion['enlace'] ?? 0, ['id', 'turno_id']);
        if ($referencia === null) {
            throw new AccesoEnlaceRechazadoException;
        }

        return $this->transacciones->ejecutar($referencia->turno_id, function (Turno $turno, CarbonImmutable $ahora) use ($referencia, $autorizacion, $accion, $operacion): array {
            // Orden compartido: K -> P -> T -> revisión -> enlace. Las Actions anidadas
            // sólo reacceden a K/P/T ya retenidos; no liberan los locks exteriores.
            $version = EnlaceAsistenciaTurno::versionActualDe($turno);
            $enlace = EnlaceAsistenciaTurno::query()->lockForUpdate()->find($referencia->id);
            if ($operacion !== null) {
                if (($autorizacion['uso'] ?? null) !== 'uc28-navegador-v1'
                    || ! is_int($autorizacion['exp'] ?? null) || $ahora->getTimestamp() >= $autorizacion['exp']) {
                    throw new AccesoEnlaceRechazadoException('vencido');
                }
                $consolidado = $this->resultadoRegistrado($autorizacion, $accion, $operacion);
                if ($consolidado !== null) {
                    // Sólo informa el hecho histórico correlacionado. No vuelve a invocar
                    // Actions ni rehabilita un enlace cancelado, revocado o reprogramado.
                    return $consolidado;
                }
            }
            $this->comprobar($enlace, $turno, $autorizacion, $ahora, $version);
            $actor = $turno->paciente->user;
            if ($actor === null) {
                throw new AccesoEnlaceRechazadoException;
            }
            $ultimaAuditoria = $operacion === null ? 0 : (int) Auditoria::query()->useWritePdo()->max('id');
            $resultado = $accion === 'confirmar'
                ? $this->confirmar->ejecutar($actor, $turno, CanalAutogestionTurnoEnum::ENLACE_SEGURO)
                : $this->cancelar->ejecutar($actor, $turno, canal: CanalAutogestionTurnoEnum::ENLACE_SEGURO);
            // Defensa también ante vencimiento o cambios durante el callback anidado.
            // Si falla, la transacción exterior revierte estado/auditoría/intención/revisión.
            $final = $enlace->fresh();
            $ahoraFinal = $this->reloj->ahora();
            if ($final === null || $final->revocado_at !== null) {
                throw new AccesoEnlaceRechazadoException('revocado');
            }
            $this->comprobarTiempo($final, $autorizacion, $ahoraFinal);

            if ($operacion !== null) {
                $transicion = null;
                if (! $resultado->esReplay) {
                    $transicion = Auditoria::query()->useWritePdo()->where('id', '>', $ultimaAuditoria)
                        ->where('auditable_type', $turno->getMorphClass())->where('auditable_id', $turno->id)
                        ->where('evento', $accion === 'confirmar' ? 'turno_asistencia_confirmada' : 'turno_cancelado')->sole();
                    if (($transicion->valores_nuevos['canal'] ?? null) !== CanalAutogestionTurnoEnum::ENLACE_SEGURO->value) {
                        throw new LogicException('No se pudo verificar la transición de asistencia.');
                    }
                }
                // Comprobante técnico de esta decisión, no otra transición de UC–06.
                // Distingue expresamente el replay de negocio; repetir esta misma
                // correlación sólo consulta el comprobante y nunca lo duplica.
                $registro = $this->auditoria->registrar(
                    evento: 'enlace_asistencia_resultado', modulo: 'Turnos', auditable: $enlace,
                    valoresNuevos: ['correlacion_hash' => hash('sha256', $operacion), 'accion' => $accion,
                        'revision' => $autorizacion['revision'], 'auditoria_transicion_id' => $transicion?->id,
                        'replay_negocio' => $resultado->esReplay],
                    actor: $actor, url: 'urn:kinesiosoft:resultado-asistencia-enlace',
                );
                if (! $registro->exists) {
                    throw new LogicException('No se pudo registrar el resultado verificable.');
                }
                $this->comprobarTiempo($final, $autorizacion, $this->reloj->ahora());
            }

            return ['accion' => $accion, 'replay' => $resultado->esReplay];
        });
    }

    /** Consulta independiente: no exige un enlace todavía modificatorio ni ejecuta Actions. */
    public function recuperar(#[SensitiveParameter] array $datos): array
    {
        if (($datos['uso'] ?? null) !== 'uc28-recuperacion-v1' || ! is_int($datos['exp'] ?? null)
            || $this->reloj->ahora()->getTimestamp() >= $datos['exp']
            || ! is_int($datos['enlace'] ?? null) || ! is_int($datos['revision'] ?? null)
            || ! is_string($datos['operacion'] ?? null) || ! Str::isUuid($datos['operacion'])
            || ! in_array($datos['accion'] ?? null, ['confirmar', 'cancelar'], true)) {
            throw new AccesoEnlaceRechazadoException;
        }

        return $this->resultadoRegistrado($datos, $datos['accion'], $datos['operacion'])
            ?? ['codigo' => 'sin_resultado', 'accion' => '', 'replay' => false];
    }

    private function resultadoRegistrado(#[SensitiveParameter] array $datos, string $accion, string $operacion): ?array
    {
        $registros = Auditoria::query()->useWritePdo()->where('auditable_type', (new EnlaceAsistenciaTurno)->getMorphClass())
            ->where('auditable_id', $datos['enlace'])->where('evento', 'enlace_asistencia_resultado')->get();
        foreach ($registros as $registro) {
            $valor = $registro->valores_nuevos;
            if (($valor['accion'] ?? null) !== $accion || ($valor['revision'] ?? null) !== $datos['revision']
                || ! is_string($valor['correlacion_hash'] ?? null) || ! hash_equals($valor['correlacion_hash'], hash('sha256', $operacion))) {
                continue;
            }
            $transicion = Auditoria::query()->useWritePdo()->find($valor['auditoria_transicion_id'] ?? 0);
            $enlace = EnlaceAsistenciaTurno::query()->useWritePdo()->find($datos['enlace']);
            if ($enlace === null || $enlace->version_programacion !== $datos['revision']) {
                throw new LogicException('No se pudo verificar el resultado registrado.');
            }
            if (($valor['replay_negocio'] ?? null) === true) {
                if ($accion !== 'confirmar' || $valor['auditoria_transicion_id'] !== null) {
                    throw new LogicException('No se pudo verificar el resultado registrado.');
                }
            } elseif (($valor['replay_negocio'] ?? null) !== false || $transicion === null
                || $transicion->evento !== ($accion === 'confirmar' ? 'turno_asistencia_confirmada' : 'turno_cancelado')
                || $transicion->auditable_type !== (new Turno)->getMorphClass() || (int) $transicion->auditable_id !== (int) $enlace->turno_id
                || (int) ($transicion->valores_nuevos['paciente_id'] ?? 0) !== (int) $enlace->paciente_id
                || ($transicion->valores_nuevos['canal'] ?? null) !== CanalAutogestionTurnoEnum::ENLACE_SEGURO->value) {
                throw new LogicException('No se pudo verificar el resultado registrado.');
            }

            return ['codigo' => 'exito', 'accion' => $accion, 'replay' => true];
        }

        return null;
    }

    private function comprobar(?EnlaceAsistenciaTurno $enlace, Turno $turno, #[SensitiveParameter] array $datos, CarbonImmutable $ahora, int $version): void
    {
        if ($enlace === null || ($datos['uso'] ?? null) !== 'uc28-navegador-v1'
            || ($datos['revision'] ?? null) !== $version || $enlace->version_programacion !== $version) {
            throw new AccesoEnlaceRechazadoException('programacion_modificada');
        }
        if ($enlace->revocado_at !== null) {
            throw new AccesoEnlaceRechazadoException('revocado');
        }
        $this->comprobarTiempo($enlace, $datos, $ahora);
        if (! $enlace->vigentePara($turno, $ahora)) {
            throw new AccesoEnlaceRechazadoException('estado_o_programacion');
        }
    }

    private function comprobarTiempo(EnlaceAsistenciaTurno $enlace, #[SensitiveParameter] array $datos, CarbonImmutable $ahora): void
    {
        if (! is_int($datos['exp'] ?? null) || $ahora->getTimestamp() >= $datos['exp']
            || ! $ahora->lt(CarbonImmutable::parse($enlace->getRawOriginal('vence_at'), $enlace->zona_horaria))) {
            throw new AccesoEnlaceRechazadoException('vencido');
        }
    }
}
