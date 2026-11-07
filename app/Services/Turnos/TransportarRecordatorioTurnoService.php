<?php

namespace App\Services\Turnos;

use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Mail\RecordatorioTurnoMail;
use Closure;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

/** Adaptador SMTP local de 6.34. Sin fallback a log, eventos con contenido ni reintentos. */
class TransportarRecordatorioTurnoService
{
    public function enviar(#[SensitiveParameter] Closure $preparar): Resultado
    {
        if (! app()->runningInConsole() || request()->route() !== null || Auth::check() || DB::transactionLevel() !== 0) {
            return Resultado::FALLO_DEFINITIVO;
        }
        $config = config('mail.mailers.smtp');
        if (! is_array($config) || ($config['transport'] ?? null) !== 'smtp'
            || ! in_array(strtolower((string) ($config['host'] ?? '')), ['localhost', '127.0.0.1', '::1'], true)
            || ! empty($config['url']) || ! in_array($config['scheme'] ?? null, [null, 'smtp'], true)
            || ! empty($config['username']) || ! empty($config['password'])
            || filter_var($config['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
            return Resultado::FALLO_DEFINITIVO;
        }
        $transporte = null;
        try {
            $transporte = $this->crearTransporte($config['host'], (int) $config['port']);
            // Antes de construir el correo: un fallo aquí no puede haber entregado su DATA.
            $transporte->start();
        } catch (Throwable) {
            return Resultado::FALLO_RECUPERABLE;
        }
        try {
            try {
                // Relectura bajo locks después de conectar, inmediatamente antes del envío.
                $correo = $preparar();
                if (! $correo instanceof RecordatorioTurnoMail || DB::transactionLevel() !== 0) {
                    return Resultado::FALLO_DEFINITIVO;
                }
                // Sin dispatcher: ningún listener recibe el cuerpo/URL transitorios.
                // Tampoco heredar alwaysTo, BCC ni fallbacks del mailer general.
                $mailer = new Mailer('recordatorios-smtp-local', app('view'), $transporte);
                $mailer->alwaysFrom(config('mail.from.address'), config('mail.from.name'));
            } catch (Throwable) {
                return Resultado::FALLO_DEFINITIVO;
            }
            try {
                return $mailer->send($correo) !== null ? Resultado::ACEPTADO : Resultado::INCIERTO;
            } catch (UnexpectedResponseException $error) {
                // Respuesta SMTP negativa explícita: el servidor no aceptó el mensaje.
                // Usar sólo el código, nunca texto/debug que pudiera reflejar el contenido.
                $codigo = $error->getCode();

                return $codigo >= 400 && $codigo < 500 ? Resultado::FALLO_RECUPERABLE
                    : ($codigo >= 500 && $codigo < 600 ? Resultado::FALLO_DEFINITIVO : Resultado::INCIERTO);
            } catch (Throwable) {
                // Una excepción de transporte no demuestra que el servidor rechazó DATA.
                // No propagar excepción, debug SMTP, mensaje, destinatario ni credencial.
                return Resultado::INCIERTO;
            }
        } finally {
            try {
                $transporte->stop();
            } catch (Throwable) {
                // Un fallo de QUIT no sustituye el resultado conocido del envío.
            }
        }
    }

    protected function crearTransporte(string $host, int $puerto): SmtpTransport
    {
        $transporte = new EsmtpTransport($host, $puerto, false);
        $transporte->setAutoTls(false);
        $transporte->getStream()->setTimeout(10);

        return $transporte;
    }
}
