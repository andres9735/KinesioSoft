<?php

namespace App\Services\Turnos;

use App\Enums\EstadoRecordatorioTurnoEnum as Estado;
use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\RecordatorioTurno;
use App\Models\SobreEntregaEnlace;
use App\Models\Turno;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Query\Builder;

/** GA-02: selección interna. Sin Scheduler, Jobs, enlaces nuevos ni transporte. */
final class SeleccionarRecordatoriosTurnoService
{
    public function __construct(
        private TransaccionTurnoService $transacciones,
        private RegistrarRecordatorioTurnoService $registros,
        private RelojAplicacion $reloj,
        private AuditoriaService $auditoria,
    ) {}

    /** @return list<int> IDs durables; nunca modelos completos o credenciales para una cola. */
    public function seleccionar(): array
    {
        $zona = (string) config('app.timezone');
        $ahora = $this->reloj->ahora();
        $hasta = $ahora->utc()->addHours(24)->setTimezone($zona);
        $ids = [];
        // No usar updated_at/created_at ni el último ciclo como límite inferior:
        // una ejecución tardía debe recuperar candidatos todavía futuros.
        $candidatos = Turno::query()->select('turnos.id')
            ->whereIn('tipo', array_map(fn ($tipo) => $tipo->value, TipoTurnoEnum::cases()))
            ->whereIn('estado', [EstadoTurnoEnum::PENDIENTE->value, EstadoTurnoEnum::CONFIRMADO->value])
            ->where('inicio_at', '>', $ahora->format('Y-m-d H:i:s.u'))
            ->where('inicio_at', '<=', $hasta->format('Y-m-d H:i:s.u'))
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('recordatorios_turno as r')
                    ->join('revisiones_enlace_asistencia_turno as v', 'v.turno_id', '=', 'r.turno_id')
                    ->whereColumn('r.turno_id', 'turnos.id')->whereColumn('r.version_programacion', 'v.version_programacion')
                    ->where(function (Builder $excluir): void {
                        $excluir->whereIn('r.estado', [Estado::PROCESADO->value, Estado::INCIERTO->value])
                            ->orWhereExists(fn (Builder $intentos) => $intentos->selectRaw('1')
                                ->from('intentos_recordatorio_turno as i')->whereColumn('i.recordatorio_id', 'r.id'));
                    });
            });
        foreach ($candidatos->lazyById(100, 'turnos.id', 'id') as $referencia) {
            $id = $this->transacciones->ejecutar((int) $referencia->id, function (Turno $turno, CarbonImmutable $actual): ?int {
                if (! $this->cumpleCondiciones($turno, $actual)) {
                    return null;
                }
                $version = EnlaceAsistenciaTurno::versionActualDe($turno);
                $existente = RecordatorioTurno::query()->where('turno_id', $turno->id)
                    ->where('version_programacion', $version)->lockForUpdate()->first();
                if ($existente !== null && ($existente->intentos()->exists()
                    || in_array($existente->estado, [Estado::PROCESADO, Estado::INCIERTO], true))) {
                    return null;
                }
                // La transacción exterior retiene K -> P -> T -> revisión.
                $registro = $existente ?? $this->registros->registrar((int) $turno->id);
                if (! $registro->correspondeA($turno)) {
                    throw new DomainException('La programación del recordatorio no coincide con el turno.');
                }
                $correo = $turno->paciente->user()->value('email');
                $utilizable = RecordatorioTurno::correoUtilizable($correo);
                $entregaDisponible = true;
                if ($registro->enlace_id !== null) {
                    $enlace = EnlaceAsistenciaTurno::query()->useWritePdo()->find($registro->enlace_id);
                    $sobre = SobreEntregaEnlace::query()->useWritePdo()->whereNotNull('material_cifrado')
                        ->find($registro->sobre_id, ['id', 'enlace_id', 'estado', 'vence_at']);
                    $entregaDisponible = $enlace !== null && $sobre !== null
                        && $enlace->vigentePara($turno, $actual) && $sobre->estado === 'DISPONIBLE'
                        && (int) $sobre->enlace_id === (int) $enlace->id && $actual->lt($sobre->vence_at);
                }
                $valores = ['destinatario' => $utilizable ? $correo : null,
                    'estado' => $utilizable && $entregaDisponible ? Estado::PENDIENTE : Estado::FALLIDO,
                    'incidencia' => ! $utilizable ? 'CORREO_NO_UTILIZABLE' : ($entregaDisponible ? null : 'ENLACE_NO_DISPONIBLE')];
                $registro->forceFill($valores);
                if ($registro->isDirty()) {
                    $registro->actualizado_at = $actual;
                    $registro->saveOrFail();
                    $audit = $this->auditoria->registrar(evento: 'recordatorio_destinatario_evaluado', modulo: 'Turnos',
                        auditable: $registro, valoresNuevos: ['version_programacion' => $version,
                            'estado' => $registro->estado->value, 'incidencia' => $registro->incidencia],
                        url: 'urn:kinesiosoft:seleccion-recordatorios', ipAddress: '', userAgent: 'Proceso interno');
                    if (! $audit->exists) {
                        throw new DomainException('La auditoría de selección no está disponible.');
                    }
                }

                return $utilizable && $entregaDisponible ? (int) $registro->id : null;
            });
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** Predicado compartido de oportunidad; el consumidor debe releer bajo locks. No autoriza un despacho. */
    public function cumpleCondiciones(Turno $turno, CarbonImmutable $ahora): bool
    {
        $inicio = CarbonImmutable::parse($turno->getRawOriginal('inicio_at'), config('app.timezone'));

        return in_array($turno->tipo, TipoTurnoEnum::cases(), true)
            && in_array($turno->estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)
            && $inicio->gt($ahora) && ! $inicio->utc()->gt($ahora->utc()->addHours(24));
    }
}
