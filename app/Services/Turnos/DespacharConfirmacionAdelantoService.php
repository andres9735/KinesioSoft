<?php

namespace App\Services\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Mail\ConfirmacionAdelantoMail;
use App\Models\DespachoConfirmacionAdelanto;
use App\Models\OfertaAdelanto;
use App\Models\Paciente;
use App\Models\RecordatorioTurno;
use App\Models\SalidaOfertaAdelanto;
use App\Models\Turno;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LogicException;
use SensitiveParameter;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Throwable;

final class DespacharConfirmacionAdelantoService
{
    public function __construct(
        private readonly TransaccionOfertaAdelantoService $transacciones,
        private readonly TransporteCorreoAdelantoService $transporte,
        private readonly RelojAplicacion $reloj,
        private readonly AuditoriaService $auditoria,
    ) {}

    public function despachar(int $id): void
    {
        if (! app()->runningInConsole() || Auth::user() !== null || DB::transactionLevel() !== 0) {
            throw new LogicException('La confirmación requiere un proceso interno posterior al commit.');
        }
        $referencia = DespachoConfirmacionAdelanto::query()->useWritePdo()->findOrFail($id);
        $salida = SalidaOfertaAdelanto::query()->useWritePdo()->findOrFail($referencia->salida_id);
        $correo = $this->transacciones->ejecutar($salida->oferta_id, function (OfertaAdelanto $oferta, Turno $turno, $anterior, $profesional, Paciente $paciente) use ($id): ?ConfirmacionAdelantoMail {
            $registro = DespachoConfirmacionAdelanto::query()->lockForUpdate()->findOrFail($id);
            if ($registro->estado !== 'PENDIENTE') {
                return null;
            }
            try {
                $this->transporte->exigirConfiguracion();
            } catch (LogicException) {
                $this->resolver($registro, 'FALLIDO', 'configuracion_no_admitida');

                return null;
            }
            $datos = $this->datos($registro, $oferta);
            if ($datos === null) {
                $this->resolver($registro, 'FALLIDO', 'material_no_valido');

                return null;
            }
            if (! $this->corresponde($oferta, $turno, $paciente, $datos)) {
                $this->resolver($registro, 'OMITIDO', 'destinatario_o_programacion_modificados');

                return null;
            }
            // Una caída desde esta frontera no habilita un reenvío ciego.
            $registro->estado = 'INCIERTO';
            $registro->reclamado_at = $this->reloj->ahora();
            $registro->saveOrFail();
            $this->auditar($registro, 'confirmacion_adelanto_reclamada', 'inicio');

            return new ConfirmacionAdelantoMail($id, $datos);
        });
        if ($correo === null) {
            return;
        }
        try {
            // Sin locks durante SMTP. El callback vuelve a comprobar antes del transporte.
            $resultado = $this->transporte->enviar($correo, fn (): bool => $this->vigenteParaTransporte($id, $salida->oferta_id));
        } catch (UnexpectedResponseException $error) {
            $resultado = match (true) {
                $error->getCode() >= 400 && $error->getCode() < 500 => Resultado::FALLO_RECUPERABLE,
                $error->getCode() >= 500 && $error->getCode() < 600 => Resultado::FALLO_DEFINITIVO,
                default => Resultado::INCIERTO,
            };
        } catch (Throwable) {
            $resultado = Resultado::INCIERTO;
        }
        $this->transacciones->ejecutar($salida->oferta_id, function () use ($id, $resultado): void {
            $registro = DespachoConfirmacionAdelanto::query()->lockForUpdate()->findOrFail($id);
            if ($registro->estado !== 'INCIERTO' || $registro->resuelto_at !== null) {
                return;
            }
            $estado = match ($resultado) {
                Resultado::ACEPTADO => 'ENVIADO', Resultado::INCIERTO => 'INCIERTO', default => 'FALLIDO',
            };
            $this->resolver($registro, $estado, $resultado->value);
        });
    }

    private function vigenteParaTransporte(int $id, int $ofertaId): bool
    {
        return $this->transacciones->ejecutar($ofertaId, function (OfertaAdelanto $oferta, Turno $turno, $anterior, $profesional, Paciente $paciente) use ($id): bool {
            $registro = DespachoConfirmacionAdelanto::query()->lockForUpdate()->findOrFail($id);
            $datos = $this->datos($registro, $oferta);

            return $registro->estado === 'INCIERTO' && $datos !== null && $this->corresponde($oferta, $turno, $paciente, $datos);
        });
    }

    private function datos(DespachoConfirmacionAdelanto $registro, OfertaAdelanto $oferta): ?array
    {
        try {
            $datos = json_decode(Crypt::decryptString($registro->material_cifrado), true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
        $salida = SalidaOfertaAdelanto::query()->lockForUpdate()->findOrFail($registro->salida_id);
        if ($salida->resultado !== OfertaAdelanto::ACEPTADA || (int) $salida->oferta_id !== (int) $oferta->id
            || ($datos['uso'] ?? null) !== 'confirmacion-adelanto-v1'
            || ($datos['salida_id'] ?? null) !== (int) $salida->id || ($datos['oferta_id'] ?? null) !== (int) $oferta->id
            || ($datos['paciente_id'] ?? null) !== (int) $oferta->paciente_id
            || ($datos['horario'] ?? null) !== $oferta->inicio_at->format('d/m/Y H:i:s').'–'.$oferta->destino_fin_at->format('H:i:s')
            || ($datos['estado'] ?? null) !== ($salida->estado_turno_aceptado === 'PENDIENTE' ? 'Pendiente' : 'Confirmado')
            || ! is_string($datos['profesional'] ?? null) || ! is_string($datos['zona'] ?? null)) {
            return null;
        }

        return $datos;
    }

    private function corresponde(OfertaAdelanto $oferta, Turno $turno, Paciente $paciente, #[SensitiveParameter] array $datos): bool
    {
        $usuario = $paciente->user()->lockForUpdate()->firstOrFail();

        return $oferta->estado === OfertaAdelanto::ACEPTADA
            && (int) $turno->paciente_id === (int) $oferta->paciente_id && (int) $turno->kinesiologa_id === (int) $oferta->kinesiologa_id
            && $turno->inicio_at->equalTo($oferta->inicio_at) && $turno->fin_at->equalTo($oferta->destino_fin_at)
            && in_array($turno->estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)
            && RecordatorioTurno::correoUtilizable($usuario->email) && ($datos['destinatario'] ?? null) === $usuario->email;
    }

    private function resolver(DespachoConfirmacionAdelanto $registro, string $estado, string $resultado): void
    {
        $registro->estado = $estado;
        $registro->resuelto_at = $this->reloj->ahora();
        if ($estado !== 'INCIERTO') {
            $registro->material_cifrado = null;
        }
        $registro->saveOrFail();
        $this->auditar($registro, $estado === 'ENVIADO' ? 'confirmacion_adelanto_enviada' : 'confirmacion_adelanto_fallo_envio', $resultado);
    }

    private function auditar(DespachoConfirmacionAdelanto $registro, string $evento, string $resultado): void
    {
        $this->auditoria->registrar(
            evento: $evento, modulo: 'Turnos', auditable: $registro,
            valoresNuevos: ['salida_id' => $registro->salida_id, 'estado' => $registro->estado, 'resultado' => $resultado],
            url: 'urn:kinesiosoft:confirmacion-adelanto', ipAddress: '', userAgent: 'Proceso interno',
        );
    }
}
