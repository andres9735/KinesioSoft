<?php

namespace Tests\Support;

use Illuminate\Support\Testing\Fakes\MailFake;
use RuntimeException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

final class SimularEnvioOfertaAdelanto extends MailFake
{
    public array $resultados = [];

    public int $llamadas = 0;

    public function send($view, array $data = [], $callback = null)
    {
        $this->llamadas++;
        match (array_shift($this->resultados) ?? 'aceptado') {
            'temporal' => throw new UnexpectedResponseException('Respuesta sintética negativa', 450),
            'definitivo' => throw new UnexpectedResponseException('Respuesta sintética negativa', 550),
            'incierto' => throw new RuntimeException('Resultado sintético incierto'),
            default => null,
        };
        parent::send($view, $data, $callback);
    }
}
