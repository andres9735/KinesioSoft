<?php

namespace App\Services\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Mail\OfertaAdelantoMail;
use App\Models\DespachoOfertaAdelanto;
use App\Models\IntentoDespachoAdelanto;
use App\Models\OfertaAdelanto;
use App\Models\RecordatorioTurno;
use App\Models\Turno;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Throwable;

final class DespacharOfertaAdelantoService
{
    public function __construct(
        private readonly TransaccionTurnoService $transacciones,
        private readonly PrepararDespachoOfertaAdelantoService $preparador,
        private readonly ValidarDestinoAdelantoService $destino,
        private readonly RelojAplicacion $reloj,
        private readonly AuditoriaService $auditoria,
        private readonly CerrarOfertaAdelantoService $cierres,
        private readonly TransporteCorreoAdelantoService $transporte,
    ) {}

    public function despachar(int $id, int $numero = 1): void
    {
        if (! app()->runningInConsole() || Auth::user() !== null || DB::transactionLevel() !== 0) {
            throw new LogicException('El despacho requiere un proceso interno posterior al commit.');
        }
        $this->transporte->exigirConfiguracion();
        $referencia = DespachoOfertaAdelanto::query()->useWritePdo()->findOrFail($id);
        $ofertaReferencia = OfertaAdelanto::query()->useWritePdo()->findOrFail($referencia->oferta_id);
        $correo = $this->transacciones->ejecutar($ofertaReferencia->turno_id, function (Turno $turno) use ($id, $numero, $ofertaReferencia): ?OfertaAdelantoMail {
            $oferta = OfertaAdelanto::query()->lockForUpdate()->findOrFail($ofertaReferencia->id);
            $despacho = DespachoOfertaAdelanto::query()->lockForUpdate()->findOrFail($id);
            if (! in_array($despacho->estado, ['PENDIENTE', 'REINTENTO'], true)
                || $numero !== $despacho->intentos + 1 || $numero > 4
                || ($despacho->proximo_intento_at !== null && $this->reloj->ahora()->lt($despacho->proximo_intento_at))) {
                return null;
            }
            if ($despacho->primer_fallo_at !== null && $this->reloj->ahora()->gt($despacho->primer_fallo_at->addHour())) {
                $despacho->estado = 'FALLIDO';
                $despacho->resuelto_at = $this->reloj->ahora();
                $despacho->saveOrFail();
                $this->auditar($despacho);

                return null;
            }
            $usuario = $turno->paciente->user()->lockForUpdate()->firstOrFail();
            if (! $oferta->reservaVigenteEn($this->reloj->ahora())
                || ! $oferta->origen_inicio_at->equalTo($turno->inicio_at) || ! $oferta->origen_fin_at->equalTo($turno->fin_at)
                || (int) $oferta->kinesiologa_id !== (int) $turno->kinesiologa_id
                || (int) $oferta->paciente_id !== (int) $turno->paciente_id
                || ! in_array($turno->estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)
                || $this->destino->validar($turno, $turno->kinesiologa, $oferta->inicio_at, $oferta->fin_at, bloquear: true) === null) {
                $this->omitir($despacho);

                return null;
            }
            try {
                $datos = json_decode(Crypt::decryptString($despacho->material_cifrado), true, 16, JSON_THROW_ON_ERROR);
                $url = $datos['url'] ?? null;
                $parametros = [];
                if (is_string($url)) {
                    parse_str((string) parse_url($url, PHP_URL_QUERY), $parametros);
                }
                $token = $parametros['token'] ?? null;
                if (($datos['uso'] ?? null) !== 'oferta-adelanto-v1'
                    || ($datos['oferta_id'] ?? null) !== (int) $oferta->id
                    || ($datos['paciente_id'] ?? null) !== (int) $oferta->paciente_id
                    || (int) $despacho->oferta_id !== (int) $oferta->id || (int) $despacho->paciente_id !== (int) $oferta->paciente_id
                    || ! $despacho->vence_at->equalTo($oferta->vence_at)
                    || array_keys($parametros) !== ['token'] || ! is_string($token) || ! preg_match('/\A[a-f0-9]{64}\z/D', $token)
                    || ! hash_equals($despacho->token_hash, hash('sha256', $token))
                    || $url !== $this->preparador->baseSegura().PrepararDespachoOfertaAdelantoService::RUTA_RESOLUCION.'?token='.$token) {
                    throw new RuntimeException;
                }
            } catch (Throwable) {
                // Nunca encadenar excepciones que puedan contener la credencial o el sobre.
                throw new RuntimeException('El material de entrega de la oferta no es válido.');
            }
            if (! RecordatorioTurno::correoUtilizable($usuario->email) || ($datos['destinatario'] ?? null) !== $usuario->email) {
                $this->omitir($despacho);

                return null;
            }
            $profesional = $turno->kinesiologa->user()->lockForUpdate()->firstOrFail(['name', 'apellido']);
            if (! $oferta->reservaVigenteEn($this->reloj->ahora())) {
                $this->omitir($despacho);

                return null;
            }
            // Frontera de transporte: una caída desde aquí es incierta, nunca reenvío ciego.
            $despacho->estado = 'INCIERTO';
            $despacho->intentos = $numero;
            $despacho->proximo_intento_at = null;
            $despacho->reclamado_at = $this->reloj->ahora();
            $despacho->saveOrFail();
            IntentoDespachoAdelanto::query()->create([
                'despacho_id' => $id, 'numero' => $numero, 'resultado' => Resultado::INCIERTO->value,
                'iniciado_at' => $despacho->reclamado_at,
            ]);
            $this->auditar($despacho);

            return new OfertaAdelantoMail($id, [
                'destinatario' => $usuario->email, 'url' => $url,
                'paciente' => $usuario->name,
                'profesional' => trim($profesional->name.' '.$profesional->apellido),
                'actual' => $oferta->origen_inicio_at->format('d/m/Y H:i:s').'–'.$oferta->origen_fin_at->format('H:i:s'),
                'ofrecido' => $oferta->inicio_at->format('d/m/Y H:i:s').'–'.$oferta->destino_fin_at->format('H:i:s'),
                'vencimiento' => $oferta->vence_at->format('d/m/Y H:i:s.u P'), 'zona' => config('app.timezone'),
            ]);
        });
        if ($correo === null) {
            $this->cerrarSiTerminal($id);

            return;
        }
        try {
            // El Mailable se construye sólo con la oferta confirmada. Ningún SMTP bajo locks.
            $resultado = $this->transporte->enviar($correo, fn (): bool => $this->vigenteParaTransporte($id, $numero));
        } catch (UnexpectedResponseException $error) {
            $resultado = $this->clasificarRespuesta($error);
        } catch (Throwable) {
            $resultado = Resultado::INCIERTO;
        }
        // La ventana de RF12.5 parte del fallo observado, no de una espera posterior por locks.
        $observadoAt = $this->reloj->ahora();
        $this->transacciones->ejecutar($ofertaReferencia->turno_id, function () use ($id, $numero, $resultado, $ofertaReferencia, $observadoAt): void {
            $oferta = OfertaAdelanto::query()->lockForUpdate()->findOrFail($ofertaReferencia->id);
            $despacho = DespachoOfertaAdelanto::query()->lockForUpdate()->findOrFail($id);
            $intento = IntentoDespachoAdelanto::query()->where('despacho_id', $id)->where('numero', $numero)->lockForUpdate()->firstOrFail();
            if ($intento->resuelto_at !== null) {
                return;
            }
            $ahora = $this->reloj->ahora();
            $intento->resultado = $resultado->value;
            $intento->resuelto_at = $ahora;
            $intento->saveOrFail();
            if ($resultado === Resultado::ACEPTADO) {
                $despacho->estado = 'ENVIADO';
                $despacho->material_cifrado = null;
            } elseif ($resultado === Resultado::FALLO_RECUPERABLE && $oferta->reservaVigenteEn($ahora)) {
                $despacho->primer_fallo_at ??= $observadoAt;
                $proximo = $observadoAt->addSeconds(GestionarReintentosRecordatorioTurnoService::ESPERAS_SEGUNDOS[$numero - 1] ?? 3601);
                $puedeReintentar = $numero < 4 && $ahora->lte($despacho->primer_fallo_at->addHour())
                    && $proximo->lte($despacho->primer_fallo_at->addHour()) && $proximo->lt($oferta->vence_at);
                $despacho->estado = $puedeReintentar ? 'REINTENTO' : 'FALLIDO';
                $despacho->proximo_intento_at = $puedeReintentar ? $proximo : null;
            } elseif ($resultado !== Resultado::INCIERTO) {
                $despacho->estado = 'FALLIDO';
            }
            if ($resultado !== Resultado::INCIERTO) {
                $despacho->resuelto_at = $ahora;
                $despacho->saveOrFail();
                $this->auditar($despacho);
            }
        });
        $this->cerrarSiTerminal($id);
        if ($resultado === Resultado::INCIERTO) {
            throw new RuntimeException('El despacho de la oferta quedó con resultado incierto.');
        }
    }

    private function omitir(DespachoOfertaAdelanto $despacho): void
    {
        $despacho->estado = 'OMITIDO';
        $despacho->material_cifrado = null;
        $despacho->resuelto_at = $this->reloj->ahora();
        $despacho->saveOrFail();
        $this->auditar($despacho);
    }

    private function vigenteParaTransporte(int $id, int $numero): bool
    {
        $referencia = DespachoOfertaAdelanto::query()->useWritePdo()->findOrFail($id);
        $referenciaOferta = OfertaAdelanto::query()->useWritePdo()->findOrFail($referencia->oferta_id);

        return $this->transacciones->ejecutar($referenciaOferta->turno_id, function (Turno $turno) use ($id, $numero, $referenciaOferta): bool {
            $oferta = OfertaAdelanto::query()->lockForUpdate()->findOrFail($referenciaOferta->id);
            $despacho = DespachoOfertaAdelanto::query()->lockForUpdate()->findOrFail($id);
            if ($despacho->estado !== 'INCIERTO' || $despacho->intentos !== $numero || $despacho->material_cifrado === null) {
                return false;
            }
            try {
                $datos = json_decode(Crypt::decryptString($despacho->material_cifrado), true, 16, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return false;
            }
            $correo = $turno->paciente->user()->lockForUpdate()->value('email');
            if (($datos['destinatario'] ?? null) !== $correo || ! RecordatorioTurno::correoUtilizable($correo)
                || (int) $oferta->paciente_id !== (int) $turno->paciente_id
                || (int) $oferta->kinesiologa_id !== (int) $turno->kinesiologa_id
                || ! $oferta->origen_inicio_at->equalTo($turno->inicio_at) || ! $oferta->origen_fin_at->equalTo($turno->fin_at)
                || $this->destino->validar($turno, $turno->kinesiologa, $oferta->inicio_at, $oferta->fin_at, bloquear: true) === null) {
                return false;
            }
            $final = $this->reloj->ahora();

            return $oferta->reservaVigenteEn($final)
                && ($despacho->primer_fallo_at === null || $final->lte($despacho->primer_fallo_at->addHour()));
        });
    }

    private function clasificarRespuesta(UnexpectedResponseException $error): Resultado
    {
        return match (true) {
            $error->getCode() >= 400 && $error->getCode() < 500 => Resultado::FALLO_RECUPERABLE,
            $error->getCode() >= 500 && $error->getCode() < 600 => Resultado::FALLO_DEFINITIVO,
            default => Resultado::INCIERTO,
        };
    }

    private function cerrarSiTerminal(int $id): void
    {
        $registro = DespachoOfertaAdelanto::query()->useWritePdo()->findOrFail($id);
        if (in_array($registro->estado, ['FALLIDO', 'OMITIDO'], true)) {
            $this->cierres->cerrar($registro->oferta_id, OfertaAdelanto::FALLO_ENVIO);
            // El sondeo recupera la continuación durable aunque el worker caiga aquí.
        }
    }

    private function auditar(DespachoOfertaAdelanto $despacho): void
    {
        $this->auditoria->registrar(
            evento: 'despacho_oferta_adelanto_actualizado', modulo: 'Turnos', auditable: $despacho,
            valoresNuevos: ['oferta_id' => $despacho->oferta_id, 'estado' => $despacho->estado],
            url: 'urn:kinesiosoft:despacho-oferta', ipAddress: '', userAgent: 'Proceso interno',
        );
    }
}
