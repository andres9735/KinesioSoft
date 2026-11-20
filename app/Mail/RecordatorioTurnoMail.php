<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use JsonSerializable;
use LogicException;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/** Exclusivamente transitorio y sin ShouldQueue. Los Jobs futuros reciben IDs. */
final class RecordatorioTurnoMail extends Mailable implements JsonSerializable
{
    public function __construct(private int $recordatorioId, #[SensitiveParameter] private array $contenido) {}

    public function build(): self
    {
        $horario = PresentacionTurno::horario($this->contenido['fecha'].' '.explode('–', $this->contenido['horario'], 2)[0]);

        return $this->to($this->contenido['destinatario'])->subject('Recordatorio de turno — KinesioSoft')
            ->view('emails.turnos.recordatorio', array_merge($this->contenido, $horario));
    }

    public function render(): string
    {
        try {
            return parent::render();
        } catch (Throwable) {
            throw new RuntimeException('No pudo prepararse la presentación del recordatorio.');
        }
    }

    public function jsonSerialize(): array
    {
        return ['recordatorio_id' => $this->recordatorioId];
    }

    public function __debugInfo(): array
    {
        return $this->jsonSerialize();
    }

    public function __serialize(): array
    {
        throw new LogicException('El correo con material transitorio no puede persistirse ni encolarse.');
    }
}
