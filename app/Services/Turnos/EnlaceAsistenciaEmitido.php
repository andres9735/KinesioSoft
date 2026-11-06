<?php

namespace App\Services\Turnos;

use JsonSerializable;
use LogicException;
use SensitiveParameter;

/** La URL secreta sólo se obtiene explícitamente para el correo, después del commit. */
final readonly class EnlaceAsistenciaEmitido implements JsonSerializable
{
    public function __construct(
        public int $enlaceId,
        public bool $nuevaEmision,
        #[SensitiveParameter] private ?string $url,
        public ?int $sobreId = null,
    ) {}

    public function urlParaCorreo(): ?string
    {
        return $this->url;
    }

    public function jsonSerialize(): array
    {
        return ['enlace_id' => $this->enlaceId, 'nueva_emision' => $this->nuevaEmision, 'sobre_id' => $this->sobreId];
    }

    public function __debugInfo(): array
    {
        return $this->jsonSerialize();
    }

    public function __serialize(): array
    {
        throw new LogicException('La credencial transitoria no puede serializarse ni incorporarse a una cola.');
    }
}
