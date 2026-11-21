<?php

namespace App\Data\HistoriaClinica;

final readonly class ResultadoProfesionalRevision
{
    /** Resultado propuesto: no firma, confirma, persiste ni cambia estados. */
    public function __construct(
        public int $declaracionId,
        public int $pacienteId,
        public ?int $versionBaseId,
        public array $datosClinicos,
        public array $dictamenes,
        public array $linaje,
        public string $huellaDeclaracion,
        public string $huellaResultado,
    ) {}
}
