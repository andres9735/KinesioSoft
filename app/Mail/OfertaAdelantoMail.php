<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use JsonSerializable;
use LogicException;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/** Material transitorio; no se encola ni se serializa. */
final class OfertaAdelantoMail extends Mailable implements JsonSerializable
{
    public function __construct(private int $despachoId, #[SensitiveParameter] private array $contenido) {}

    public function build(): self
    {
        return $this->to($this->contenido['destinatario'])->subject('Oferta de adelanto de turno — KinesioSoft')
            ->view('emails.turnos.oferta-adelanto', $this->contenido);
    }

    public function render(): string
    {
        try {
            return parent::render();
        } catch (Throwable) {
            throw new RuntimeException('No pudo prepararse la presentación de la oferta.');
        }
    }

    public function jsonSerialize(): array
    {
        return ['despacho_id' => $this->despachoId];
    }

    public function __debugInfo(): array
    {
        return $this->jsonSerialize();
    }

    public function __serialize(): array
    {
        throw new LogicException('El correo transitorio de oferta no puede persistirse ni encolarse.');
    }
}
