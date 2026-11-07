<?php

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;

/** Doble de protocolo en memoria; no abre puertos ni escribe archivos. */
final class StreamSmtpRecordatorio extends AbstractStream
{
    public array $escrituras = [];

    public array $respuestas = ["220 SMTP local simulado\r\n", "250 Hello\r\n", "250 NOOP OK\r\n", "250 Sender OK\r\n",
        "250 Recipient OK\r\n", "354 DATA\r\n", "250 Accepted\r\n", "221 Bye\r\n"];

    public bool $fallaInicio = false;

    public function initialize(): void
    {
        if ($this->fallaInicio) {
            throw new RuntimeException('Conexión sintética no disponible.');
        }
    }

    public function write(string $bytes, bool $debug = true): void
    {
        $this->escrituras[] = $bytes;
    }

    public function readLine(): string
    {
        return array_shift($this->respuestas) ?? '';
    }

    public function flush(): void {}

    protected function getReadConnectionDescription(): string
    {
        return 'SMTP simulado en memoria';
    }
}
