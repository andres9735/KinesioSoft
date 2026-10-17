<?php

namespace App\Services\Turnos\Documentos;

final readonly class ResultadoReconciliacionDocumental
{
    /**
     * @param  array<string, int>  $conteos
     * @param  list<string>  $errores  Códigos técnicos, sin rutas ni datos clínicos.
     */
    public function __construct(
        public bool $dryRun,
        public array $conteos,
        public array $errores,
    ) {}

    public function exitoso(): bool
    {
        return $this->errores === [];
    }
}
