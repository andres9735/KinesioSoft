<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use JsonSerializable;
use LogicException;
use RuntimeException;
use SensitiveParameter;
use Throwable;

final class ConfirmacionAdelantoMail extends Mailable implements JsonSerializable
{
    public function __construct(private int $despachoId, #[SensitiveParameter] private array $contenido) {}

    public function build(): self
    {
        return $this->to($this->contenido['destinatario'])->subject('Cambio de horario confirmado — KinesioSoft')
            ->view('emails.turnos.confirmacion-adelanto', $this->contenido);
    }

    public function render(): string
    {
        try {
            return parent::render();
        } catch (Throwable) {
            throw new RuntimeException('No pudo prepararse la presentación de la confirmación.');
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
        throw new LogicException('El correo transitorio de confirmación no puede encolarse.');
    }
}
