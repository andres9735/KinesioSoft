<?php

namespace App\Services\Turnos;

use App\Enums\EstadoRecordatorioTurnoEnum as Estado;
use App\Mail\RecordatorioTurnoMail;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\RecordatorioTurno;
use App\Models\SobreEntregaEnlace;
use App\Models\Turno;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

final class PrepararContenidoRecordatorioTurnoService
{
    public function __construct(
        private TransaccionTurnoService $transacciones,
        private SeleccionarRecordatoriosTurnoService $elegibilidad,
        private RegistrarRecordatorioTurnoService $registros,
        private EmitirEnlaceAsistenciaTurnoService $emisor,
        private ValidarEnlaceAsistenciaTurnoService $validador,
    ) {}

    /** Preparación durable, sin descifrado ni intento de envío. Sólo devuelve referencias. */
    public function preparar(int $id): array
    {
        $this->autorizarProceso();
        $referencia = RecordatorioTurno::query()->useWritePdo()->findOrFail($id, ['id', 'turno_id']);

        return $this->transacciones->ejecutar((int) $referencia->turno_id, function (Turno $turno, CarbonImmutable $ahora) use ($id): array {
            EnlaceAsistenciaTurno::versionActualDe($turno);
            // El mutex del turno protege la preparación. Bloquear enlace/sobre antes del registro.
            $registro = RecordatorioTurno::query()->useWritePdo()->findOrFail($id);
            $this->comprobar($registro, $turno, $ahora);
            if ($registro->intentos()->exists()) {
                throw new DomainException('La preparación ordinaria no reinicia intentos registrados.');
            }
            $acciones = $this->validador->accionesPara($turno, $ahora, $registro->zona_horaria);
            if ($acciones['confirmar'] || $acciones['cancelar']) {
                $emitido = $this->emisor->emitir((int) $turno->id);
                if ($emitido->sobreId === null) {
                    throw new DomainException('El enlace histórico no dispone de material recuperable.');
                }
                $this->registros->asociarSobre((int) $registro->id, $emitido->sobreId);
                $sobreId = $emitido->sobreId;
            } else {
                $sobreId = null;
            }

            return ['recordatorio_id' => (int) $registro->id, 'sobre_id' => $sobreId];
        });
    }

    /**
     * Sólo dentro del callback autorizado de SobreEntregaEnlaceService cuando se requiere URL.
     * No descifra, reclama, despacha ni registra resultados. El consumidor futuro coordina
     * intento/reclamo y transporte FUERA de toda transacción, revalidando antes del envío.
     */
    public function construir(
        int $id,
        #[SensitiveParameter] ?EnlaceAsistenciaEmitido $material = null,
        #[SensitiveParameter] ?string $reclamo = null,
    ): RecordatorioTurnoMail {
        $this->autorizarProceso();
        if (DB::transactionLevel() !== 0) {
            throw new DomainException('El contenido de entrega se prepara después del commit durable.');
        }
        $referencia = RecordatorioTurno::query()->useWritePdo()->findOrFail($id, ['id', 'turno_id']);

        return $this->transacciones->ejecutar((int) $referencia->turno_id, function (Turno $turno, CarbonImmutable $ahora) use ($id, $material, $reclamo): RecordatorioTurnoMail {
            EnlaceAsistenciaTurno::versionActualDe($turno);
            $registro = RecordatorioTurno::query()->useWritePdo()->findOrFail($id);
            $this->comprobar($registro, $turno, $ahora);
            $acciones = $this->validador->accionesPara($turno, $ahora, $registro->zona_horaria);
            $url = null;
            if ($acciones['confirmar'] || $acciones['cancelar']) {
                $enlace = EnlaceAsistenciaTurno::query()->lockForUpdate()->find($registro->enlace_id);
                $sobre = SobreEntregaEnlace::query()->lockForUpdate()->find($registro->sobre_id);
                $url = $material?->urlParaCorreo();
                $parametros = [];
                if (is_string($url)) {
                    parse_str((string) parse_url($url, PHP_URL_QUERY), $parametros);
                }
                if ($enlace === null || $sobre === null || $material === null || ! is_string($url)
                    || ! is_string($reclamo) || ! Str::isUuid($reclamo)
                    || $sobre->estado !== 'INCIERTO' || ! is_string($sobre->reclamo_hash)
                    || ! hash_equals($sobre->reclamo_hash, hash('sha256', $reclamo))
                    || (int) $material->enlaceId !== (int) $enlace->id || (int) $material->sobreId !== (int) $sobre->id
                    || (int) $sobre->enlace_id !== (int) $enlace->id || ! $ahora->lt($sobre->vence_at)
                    || ! $enlace->vigentePara($turno, $ahora) || array_keys($parametros) !== ['token']
                    || ! is_string($parametros['token']) || ! $enlace->coincideConToken($parametros['token'])
                    || $url !== rtrim(config('app.url'), '/').EmitirEnlaceAsistenciaTurnoService::RUTA_RESOLUCION.'?token='.$parametros['token']) {
                    throw new DomainException('El contenido requiere una referencia de entrega autorizada y vigente.');
                }
            }
            $profesional = $turno->kinesiologa->user()->first(['name', 'apellido']);
            $inicio = CarbonImmutable::parse($turno->getRawOriginal('inicio_at'), $registro->zona_horaria);
            $fin = CarbonImmutable::parse($turno->getRawOriginal('fin_at'), $registro->zona_horaria);

            return new RecordatorioTurnoMail((int) $registro->id, [
                'destinatario' => $registro->destinatario,
                'tipo' => $turno->tipo->value === 'PRIMERA_CONSULTA' ? 'Primera consulta' : 'Seguimiento',
                'fecha' => $inicio->format('d/m/Y'), 'horario' => $inicio->format('H:i').'–'.$fin->format('H:i'),
                'profesional' => trim(($profesional?->name ?? '').' '.($profesional?->apellido ?? '')),
                'estado' => $turno->estado->value === 'PENDIENTE' ? 'Pendiente' : 'Confirmado',
                'confirmar' => $acciones['confirmar'], 'cancelar' => $acciones['cancelar'], 'url' => $url,
            ]);
        });
    }

    private function comprobar(RecordatorioTurno $registro, Turno $turno, CarbonImmutable $ahora): void
    {
        if (! $this->elegibilidad->cumpleCondiciones($turno, $ahora) || ! $registro->correspondeA($turno)
            || $registro->estado === Estado::PROCESADO || ! RecordatorioTurno::correoUtilizable($registro->destinatario)
            || $registro->destinatario !== $turno->paciente->user()->value('email')) {
            throw new DomainException('El recordatorio cambió o dejó de ser elegible.');
        }
    }

    private function autorizarProceso(): void
    {
        if (! app()->runningInConsole() || request()->route() !== null || Auth::check()) {
            throw new DomainException('La preparación del correo requiere un proceso interno autorizado.');
        }
    }
}
