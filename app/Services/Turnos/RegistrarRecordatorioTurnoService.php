<?php

namespace App\Services\Turnos;

use App\Enums\EstadoRecordatorioTurnoEnum as Estado;
use App\Enums\EstadoTurnoEnum;
use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\IntentoRecordatorioTurno;
use App\Models\RecordatorioTurno;
use App\Models\SobreEntregaEnlace;
use App\Models\Turno;
use App\Services\AuditoriaService;
use Carbon\CarbonImmutable;
use Closure;
use DomainException;
use Illuminate\Support\Str;

/** Persistencia interna GA-03/04: no envía, encola ni programa reintentos. */
final class RegistrarRecordatorioTurnoService
{
    public function __construct(private TransaccionTurnoService $transacciones, private AuditoriaService $auditoria) {}

    public function registrar(int $turnoId): RecordatorioTurno
    {
        return $this->transacciones->ejecutar($turnoId, function (Turno $turno, CarbonImmutable $ahora): RecordatorioTurno {
            $version = EnlaceAsistenciaTurno::versionActualDe($turno);
            $existente = RecordatorioTurno::query()->where('turno_id', $turno->id)
                ->where('version_programacion', $version)->lockForUpdate()->first();
            if ($existente !== null) {
                return $existente;
            }
            $correo = $turno->paciente->user()->value('email');
            $utilizable = RecordatorioTurno::correoUtilizable($correo);
            $registro = new RecordatorioTurno;
            $registro->forceFill([
                'identidad' => (string) Str::uuid(), 'turno_id' => $turno->id,
                'paciente_id' => $turno->paciente_id, 'kinesiologa_id' => $turno->kinesiologa_id,
                'version_programacion' => $version, 'tipo' => $turno->tipo->value,
                'inicio_at' => $turno->getRawOriginal('inicio_at'), 'fin_at' => $turno->getRawOriginal('fin_at'),
                'zona_horaria' => config('app.timezone'), 'destinatario' => $utilizable ? $correo : null,
                'estado' => $utilizable ? Estado::PENDIENTE : Estado::FALLIDO,
                'incidencia' => $utilizable ? null : 'CORREO_NO_UTILIZABLE',
                'enlace_id' => null, 'sobre_id' => null, 'creado_at' => $ahora, 'actualizado_at' => $ahora,
            ])->saveOrFail();
            $this->auditar($registro, 'recordatorio_registrado');

            return $registro;
        });
    }

    /** Sólo referencias ya emitidas. No genera ni recupera credenciales. */
    public function asociarSobre(int $recordatorioId, int $sobreId): void
    {
        $this->bajoLocks($recordatorioId, function (RecordatorioTurno $registro, Turno $turno, CarbonImmutable $ahora) use ($sobreId): void {
            // Mantener orden de UC–28: revisión -> enlace -> sobre -> recordatorio.
            $referencia = SobreEntregaEnlace::query()->useWritePdo()->findOrFail($sobreId);
            $enlace = EnlaceAsistenciaTurno::query()->where('turno_id', $turno->id)->lockForUpdate()->findOrFail($referencia->enlace_id);
            $sobre = SobreEntregaEnlace::query()->lockForUpdate()->findOrFail($sobreId);
            $registro = RecordatorioTurno::query()->lockForUpdate()->findOrFail($registro->id);
            if (! $registro->correspondeA($turno) || ! $enlace->vigentePara($turno, $ahora)
                || $sobre->material_cifrado === null || $sobre->estado === 'CERRADO'
                || ! $ahora->lt($sobre->vence_at)) {
                throw new DomainException('La referencia de entrega no está vigente.');
            }
            if ($registro->sobre_id !== null) {
                if ((int) $registro->sobre_id !== $sobreId) {
                    throw new DomainException('El recordatorio ya tiene una referencia de entrega.');
                }

                return;
            }
            $registro->forceFill(['enlace_id' => $enlace->id, 'sobre_id' => $sobreId, 'actualizado_at' => $ahora])->saveOrFail();
            $this->auditar($registro, 'recordatorio_sobre_asociado');
        }, bloquearRegistro: false);
    }

    /**
     * Registra intención durable ANTES del transporte futuro: una caída es incierta.
     * El consumidor debe revalidar elegibilidad y sobre justo antes del despacho.
     * Una identidad repetida devuelve sólo su registro; wasRecentlyCreated=false
     * no autoriza a volver a transportar el mensaje.
     * No ejecuta transporte ni calcula una agenda de reintentos.
     */
    public function iniciarIntento(int $recordatorioId, string $identidad): IntentoRecordatorioTurno
    {
        if (! Str::isUuid($identidad)) {
            throw new DomainException('La identidad técnica del intento no es válida.');
        }

        return $this->bajoLocks($recordatorioId, function (RecordatorioTurno $registro, Turno $turno, CarbonImmutable $ahora) use ($identidad): IntentoRecordatorioTurno {
            $existente = IntentoRecordatorioTurno::query()->where('identidad', $identidad)->lockForUpdate()->first();
            if ($existente !== null) {
                if ((int) $existente->recordatorio_id !== (int) $registro->id) {
                    throw new DomainException('La identidad del intento pertenece a otro recordatorio.');
                }

                return $existente;
            }
            $previo = $registro->intentos()->orderByDesc('numero')->lockForUpdate()->first();
            $primerFallo = $registro->intentos()->where('resultado', Resultado::FALLO_RECUPERABLE->value)->orderBy('numero')->first();
            if (! $registro->correspondeA($turno) || ! RecordatorioTurno::correoUtilizable($registro->destinatario)
                || ! in_array($turno->estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)
                || ! $ahora->lt(CarbonImmutable::parse($turno->getRawOriginal('inicio_at'), $registro->zona_horaria))
                || $registro->destinatario !== $turno->paciente->user()->value('email')
                || ! in_array($registro->estado, [Estado::PENDIENTE, Estado::FALLIDO], true)
                || ($previo !== null && ($previo->resultado !== Resultado::FALLO_RECUPERABLE || $previo->numero >= 4))
                || ($primerFallo !== null && $ahora->gt($primerFallo->resuelto_at->addHour()))) {
                throw new DomainException('El recordatorio no admite un nuevo intento técnico.');
            }
            $intento = new IntentoRecordatorioTurno;
            $intento->forceFill(['recordatorio_id' => $registro->id, 'identidad' => $identidad,
                'numero' => $previo === null ? 1 : $previo->numero + 1, 'destinatario' => $registro->destinatario,
                'resultado' => Resultado::INCIERTO, 'incidencia' => null, 'evidencia_entrega' => null,
                'iniciado_at' => $ahora, 'resuelto_at' => null])->saveOrFail();
            $registro->forceFill(['estado' => Estado::INCIERTO, 'incidencia' => null, 'actualizado_at' => $ahora])->saveOrFail();
            $this->auditar($registro, 'recordatorio_intento_registrado', $intento);

            return $intento;
        });
    }

    /** Evidencia es una referencia técnica verificable aportada por el adaptador, no su respuesta libre. */
    public function registrarResultado(int $intentoId, Resultado $resultado, ?string $evidencia = null): void
    {
        if (($resultado === Resultado::ENTREGA_VERIFICADA) !== ($evidencia !== null)
            || ($evidencia !== null && ! Str::isUuid($evidencia))) {
            throw new DomainException('El resultado requiere evidencia técnica coherente.');
        }
        $referencia = IntentoRecordatorioTurno::query()->useWritePdo()->findOrFail($intentoId);
        $this->bajoLocks((int) $referencia->recordatorio_id, function (RecordatorioTurno $registro, Turno $turno, CarbonImmutable $ahora) use ($intentoId, $resultado, $evidencia): void {
            $intento = IntentoRecordatorioTurno::query()->lockForUpdate()->findOrFail($intentoId);
            if ($intento->resultado === $resultado && $intento->evidencia_entrega === $evidencia) {
                return;
            }
            if ($intento->resultado !== Resultado::INCIERTO
                && ! ($intento->resultado === Resultado::ACEPTADO && $resultado === Resultado::ENTREGA_VERIFICADA)) {
                throw new DomainException('No se puede sustituir un resultado conocido.');
            }
            $incidencia = match ($resultado) {
                Resultado::FALLO_RECUPERABLE => 'TRANSPORTE_RECUPERABLE',
                Resultado::FALLO_DEFINITIVO => 'TRANSPORTE_DEFINITIVO',
                default => null,
            };
            $intento->forceFill(['resultado' => $resultado, 'incidencia' => $incidencia,
                'evidencia_entrega' => $evidencia, 'resuelto_at' => $ahora])->saveOrFail();
            $estado = match ($resultado) {
                Resultado::ACEPTADO, Resultado::ENTREGA_VERIFICADA => Estado::PROCESADO,
                Resultado::INCIERTO => Estado::INCIERTO,
                default => Estado::FALLIDO,
            };
            $registro->forceFill(['estado' => $estado, 'incidencia' => $incidencia, 'actualizado_at' => $ahora])->saveOrFail();
            $this->auditar($registro, 'recordatorio_resultado_registrado', $intento);
        });
    }

    private function bajoLocks(int $id, Closure $operacion, bool $bloquearRegistro = true): mixed
    {
        $referencia = RecordatorioTurno::query()->useWritePdo()->findOrFail($id, ['id', 'turno_id']);

        return $this->transacciones->ejecutar((int) $referencia->turno_id, function (Turno $turno, CarbonImmutable $ahora) use ($id, $operacion, $bloquearRegistro): mixed {
            EnlaceAsistenciaTurno::versionActualDe($turno);
            $query = RecordatorioTurno::query();
            $registro = ($bloquearRegistro ? $query->lockForUpdate() : $query->useWritePdo())->findOrFail($id);

            return $operacion($registro, $turno, $ahora);
        });
    }

    private function auditar(RecordatorioTurno $registro, string $evento, ?IntentoRecordatorioTurno $intento = null): void
    {
        $auditoria = $this->auditoria->registrar(evento: $evento, modulo: 'Turnos', auditable: $registro,
            valoresNuevos: ['turno_id' => $registro->turno_id, 'version_programacion' => $registro->version_programacion,
                'estado' => $registro->estado->value, 'incidencia' => $registro->incidencia,
                'intento_id' => $intento?->id, 'resultado' => $intento?->resultado->value],
            url: 'urn:kinesiosoft:recordatorios', ipAddress: '', userAgent: 'Proceso interno');
        if (! $auditoria->exists) {
            throw new DomainException('La auditoría del recordatorio no está disponible.');
        }
    }
}
