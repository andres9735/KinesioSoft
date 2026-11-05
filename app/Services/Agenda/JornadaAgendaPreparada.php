<?php

namespace App\Services\Agenda;

use App\Enums\EstadoTurnoEnum;
use Carbon\CarbonImmutable;

/** Resultado administrativo transitorio; no sustituye la programación canónica. */
final readonly class JornadaAgendaPreparada
{
    /** @param list<array<string, mixed>> $turnos */
    public function __construct(
        public int $kinesiologaId,
        public CarbonImmutable $fechaLocal,
        public string $zonaHoraria,
        public array $turnos,
    ) {}

    /** @return array{total: int, operativos: int, en_atencion: int, otros: int} */
    public function cantidades(): array
    {
        $operativos = 0;
        $enAtencion = 0;
        foreach ($this->turnos as $turno) {
            if (in_array($turno['estado'], [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)) {
                $operativos++;
            } elseif ($turno['estado'] === EstadoTurnoEnum::EN_ATENCION) {
                $enAtencion++;
            }
        }

        return [
            'total' => count($this->turnos), 'operativos' => $operativos,
            'en_atencion' => $enAtencion, 'otros' => count($this->turnos) - $operativos - $enAtencion,
        ];
    }
}
