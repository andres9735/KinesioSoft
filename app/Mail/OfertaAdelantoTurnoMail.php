<?php

namespace App\Mail;

use App\Models\OfertaAdelantoTurno;
use App\Models\Turno;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OfertaAdelantoTurnoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OfertaAdelantoTurno $oferta,
        public Turno $turnoOfertado,   // el hueco nuevo (turno cancelado)
        public Turno $turnoOriginal,   // el turno original del paciente
    ) {}

    public function build()
    {
        // Usamos el token para construir la URL base
        $baseUrl = url('/oferta-adelanto/' . $this->oferta->oferta_token);

        // Por ahora, dos acciones vía query string
        $aceptarUrl  = $baseUrl . '?accion=aceptar';
        $rechazarUrl = $baseUrl . '?accion=rechazar';

        return $this->subject('Oferta para adelantar tu turno')
            ->markdown('emails.turnos.oferta-adelanto', [
                'oferta'        => $this->oferta,
                'turnoOfertado' => $this->turnoOfertado,
                'turnoOriginal' => $this->turnoOriginal,
                'aceptarUrl'    => $aceptarUrl,
                'rechazarUrl'   => $rechazarUrl,
            ]);
    }
}
