<?php

namespace App\Services\Turnos;

use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use Closure;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use LogicException;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

/** Transporte aislado compartido por oferta y confirmación; no persiste ni reintenta. */
final class TransporteCorreoAdelantoService
{
    public function exigirConfiguracion(): void
    {
        if (app()->environment('testing') && config('mail.default') === 'array') {
            return;
        }
        if (config('mail.default') !== 'smtp' || config('mail.mailers.smtp.transport') !== 'smtp'
            || ! empty(config('mail.mailers.smtp.url'))) {
            throw new LogicException('La entrega de ofertas requiere SMTP explícito, sin logs ni fallbacks.');
        }
    }

    /** El llamador ya confirmó su reclamo y no mantiene una transacción abierta. */
    public function enviar(#[SensitiveParameter] Mailable $correo, Closure $vigente): Resultado
    {
        if (app()->environment('testing') && config('mail.default') === 'array') {
            if (! $vigente()) {
                return Resultado::FALLO_DEFINITIVO;
            }
            Mail::send($correo);

            return Resultado::ACEPTADO;
        }
        $transporte = app(MailManager::class)->createSymfonyTransport(config('mail.mailers.smtp'));
        if (! $transporte instanceof SmtpTransport) {
            throw new LogicException('El transporte configurado no admite ofertas.');
        }
        try {
            $transporte->start();
        } catch (UnexpectedResponseException $error) {
            throw $error;
        } catch (Throwable) {
            // Antes de DATA se conoce que el mensaje aún no fue aceptado.
            return Resultado::FALLO_RECUPERABLE;
        }
        if (! $vigente()) {
            return Resultado::FALLO_DEFINITIVO;
        }
        // Sin dispatcher, alwaysTo/BCC globales ni fallback de log. Patrón de UC-27.
        $mailer = new Mailer('adelantos-smtp', app('view'), $transporte);
        $mailer->alwaysFrom(config('mail.from.address'), config('mail.from.name'));
        if ($mailer->send($correo) === null) {
            throw new RuntimeException('El transporte no confirmó la aceptación del mensaje.');
        }

        return Resultado::ACEPTADO;
    }
}
