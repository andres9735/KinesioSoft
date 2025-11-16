<?php

namespace App\Mail;

use App\Models\Turno;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
// si lo estabas encolando:
// use Illuminate\Contracts\Queue\ShouldQueue;

class TurnoConfirmacionMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Turno $turno) {}

    public function build()
    {
        $ttl = now()->addHours((int) config('turnos.mail_link_ttl_hours', 36));

        $confirmUrl = URL::temporarySignedRoute(
            'turnos.mail.show',
            $ttl,
            ['turno' => $this->turno->getKey(), 'accion' => 'confirmar']
        );

        $cancelUrl = URL::temporarySignedRoute(
            'turnos.mail.show',
            $ttl,
            ['turno' => $this->turno->getKey(), 'accion' => 'cancelar']
        );

        return $this->subject('Confirmación de turno')
            ->markdown('emails.turnos.confirmacion', [
                'turno'      => $this->turno,
                'confirmUrl' => $confirmUrl,
                'cancelUrl'  => $cancelUrl,
            ]);
    }
}
