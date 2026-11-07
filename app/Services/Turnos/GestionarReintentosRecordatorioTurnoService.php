<?php

namespace App\Services\Turnos;

use App\Enums\EstadoRecordatorioTurnoEnum as Estado;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/** GA-06. El Scheduler recupera referencias; sólo el worker puede reclamar un intento. */
final class GestionarReintentosRecordatorioTurnoService
{
    // Elección técnica progresiva; GA-06 fija tres reintentos y una hora, no estos minutos.
    public const ESPERAS_SEGUNDOS = [300, 600, 1200];

    public function __construct(
        private TransaccionTurnoService $transacciones,
        private RegistrarRecordatorioTurnoService $registros,
        private SeleccionarRecordatoriosTurnoService $elegibilidad,
        private SobreEntregaEnlaceService $sobres,
        private AuditoriaService $auditoria,
    ) {}

    /** Sin reclamos de entrega ni credenciales. Repetir recupera la misma referencia técnica. */
    public function planificar(): array
    {
        $this->autorizarProceso();
        $planes = [];
        foreach (RecordatorioTurno::query()->select('id')->whereIn('estado', [Estado::PENDIENTE, Estado::FALLIDO, Estado::INCIERTO])
            ->lazyById(100) as $referencia) {
            $plan = $this->revisar((int) $referencia->id);
            if ($plan !== null) {
                $planes[] = $plan;
            }
        }

        return $planes;
    }

    public function revisar(int $id): ?array
    {
        return $this->bajoLocks($id, fn (RecordatorioTurno $registro, Turno $turno, CarbonImmutable $ahora,
            ?EnlaceAsistenciaTurno $enlace, ?SobreEntregaEnlace $sobre) => $this->evaluar($registro, $turno, $ahora, $enlace, $sobre));
    }

    /** La evaluación temporal y el registro incierto se consolidan bajo el mismo mutex. */
    public function reclamar(int $id, string $identidad, int $numero): ?IntentoRecordatorioTurno
    {
        if (DB::transactionLevel() !== 0) {
            throw new DomainException('El intento requiere un commit durable antes del despacho.');
        }

        return $this->bajoLocks($id, function (RecordatorioTurno $registro, Turno $turno, CarbonImmutable $ahora,
            ?EnlaceAsistenciaTurno $enlace, ?SobreEntregaEnlace $sobre) use ($identidad, $numero): ?IntentoRecordatorioTurno {
            $existente = IntentoRecordatorioTurno::query()->where('identidad', $identidad)->lockForUpdate()->first();
            if ($existente !== null) {
                if ((int) $existente->recordatorio_id !== (int) $registro->id || $existente->numero !== $numero) {
                    throw new DomainException('La referencia de intento no corresponde a este despacho.');
                }

                return $existente;
            }
            $plan = $this->evaluar($registro, $turno, $ahora, $enlace, $sobre);
            if ($plan === null || $plan['numero'] !== $numero) {
                return null;
            }

            return $this->registros->iniciarIntento((int) $registro->id, $identidad);
        });
    }

    private function evaluar(RecordatorioTurno $registro, Turno $turno, CarbonImmutable $ahora,
        ?EnlaceAsistenciaTurno $enlace, ?SobreEntregaEnlace $sobre): ?array
    {
        if ($registro->estado === Estado::PROCESADO) {
            return null;
        }
        $ultimo = $registro->intentos()->orderByDesc('numero')->lockForUpdate()->first();
        $primero = $registro->intentos()->where('resultado', Resultado::FALLO_RECUPERABLE)->orderBy('numero')->first();
        $correo = $turno->paciente->user()->value('email');
        $incidencia = match (true) {
            ! $registro->correspondeA($turno) => 'PROGRAMACION_MODIFICADA',
            ! $this->elegibilidad->cumpleCondiciones($turno, $ahora) => 'DESPACHO_NO_ELEGIBLE',
            ! RecordatorioTurno::correoUtilizable($correo) => 'CORREO_NO_UTILIZABLE',
            $registro->destinatario !== $correo => 'DESTINATARIO_MODIFICADO',
            $ultimo?->resultado === Resultado::FALLO_DEFINITIVO => 'TRANSPORTE_DEFINITIVO',
            $primero !== null && $ahora->gt($primero->resuelto_at->addHour()) => 'VENTANA_REINTENTOS_AGOTADA',
            $ultimo !== null && $ultimo->resultado === Resultado::FALLO_RECUPERABLE && $ultimo->numero >= 4 => 'REINTENTOS_AGOTADOS',
            $registro->sobre_id !== null && ($enlace === null || $sobre === null
                || ! $enlace->vigentePara($turno, $ahora) || $sobre->estado === 'CERRADO'
                || ! $ahora->lt($sobre->vence_at)) => 'ENLACE_NO_DISPONIBLE',
            $ultimo?->resultado === Resultado::INCIERTO => 'RESULTADO_INCIERTO',
            default => null,
        };
        if ($incidencia !== null) {
            $this->incidir($registro, $ultimo, $incidencia, $ahora);
            if ($sobre !== null && $incidencia !== 'RESULTADO_INCIERTO') {
                $this->sobres->depurar((int) $sobre->id);
                $this->sobres->finalizarNecesidad((int) $sobre->id);
            }

            return null;
        }
        if ($ultimo !== null && $ultimo->resultado !== Resultado::FALLO_RECUPERABLE) {
            return null;
        }
        if ($sobre !== null && $sobre->estado !== 'DISPONIBLE') {
            // No convertir un reclamo de entrega incierto en fallo confirmado ni reabrirlo.
            $this->incidir($registro, $ultimo, 'RESULTADO_INCIERTO', $ahora);

            return null;
        }
        if ($ultimo !== null && ($ultimo->resuelto_at === null
            || $ahora->lt($ultimo->resuelto_at->addSeconds(self::ESPERAS_SEGUNDOS[$ultimo->numero - 1])))) {
            return null;
        }
        $numero = $ultimo === null ? 1 : $ultimo->numero + 1;

        return ['recordatorio_id' => (int) $registro->id, 'numero' => $numero,
            'identidad' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'urn:kinesiosoft:recordatorio:'.$registro->identidad.':intento:'.$numero)->toString()];
    }

    private function incidir(RecordatorioTurno $registro, ?IntentoRecordatorioTurno $ultimo, string $incidencia, CarbonImmutable $ahora): void
    {
        // La necesidad de revisión no transforma un resultado desconocido en un fallo.
        $incierto = $incidencia === 'RESULTADO_INCIERTO' || $ultimo?->resultado === Resultado::INCIERTO || $registro->estado === Estado::INCIERTO;
        $registro->forceFill(['estado' => $incierto ? Estado::INCIERTO : Estado::FALLIDO, 'incidencia' => $incidencia]);
        if (! $registro->isDirty()) {
            return;
        }
        $registro->actualizado_at = $ahora;
        $registro->saveOrFail();
        $audit = $this->auditoria->registrar(evento: 'recordatorio_recuperacion_evaluada', modulo: 'Turnos', auditable: $registro,
            valoresNuevos: ['estado' => $registro->estado->value, 'incidencia' => $incidencia, 'intento_id' => $ultimo?->id],
            url: 'urn:kinesiosoft:recuperacion-recordatorios', ipAddress: '', userAgent: 'Proceso interno');
        if (! $audit->exists) {
            throw new DomainException('La auditoría de recuperación no está disponible.');
        }
    }

    private function bajoLocks(int $id, Closure $operacion): mixed
    {
        $this->autorizarProceso();
        $referencia = RecordatorioTurno::query()->useWritePdo()->findOrFail($id);

        return $this->transacciones->ejecutar((int) $referencia->turno_id, function (Turno $turno, CarbonImmutable $ahora) use ($id, $referencia, $operacion): mixed {
            EnlaceAsistenciaTurno::versionActualDe($turno);
            // Orden compartido: K -> P -> T -> revisión -> enlace -> sobre -> recordatorio -> intento.
            $enlace = $referencia->enlace_id === null ? null : EnlaceAsistenciaTurno::query()->lockForUpdate()->find($referencia->enlace_id);
            $sobre = $referencia->sobre_id === null ? null : SobreEntregaEnlace::query()->lockForUpdate()->find($referencia->sobre_id);
            $registro = RecordatorioTurno::query()->lockForUpdate()->findOrFail($id);
            if ($registro->enlace_id !== $referencia->enlace_id || $registro->sobre_id !== $referencia->sobre_id) {
                throw new DomainException('La referencia de entrega cambió; debe releerse.');
            }

            return $operacion($registro, $turno, $ahora, $enlace, $sobre);
        });
    }

    private function autorizarProceso(): void
    {
        if (! app()->runningInConsole() || request()->route() !== null || Auth::check()) {
            throw new DomainException('La recuperación requiere un proceso interno autorizado.');
        }
    }
}
