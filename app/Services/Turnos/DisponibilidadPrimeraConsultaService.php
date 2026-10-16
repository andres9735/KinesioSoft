<?php

namespace App\Services\Turnos;

use App\Enums\DiaSemanaEnum;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\ValidadorMatrizAgenda;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use LogicException;

final class DisponibilidadPrimeraConsultaService
{
    public function __construct(
        private readonly AgendaQueryService $agenda,
        private readonly ValidadorMatrizAgenda $validadorMatriz,
    ) {}

    /**
     * Genera únicamente la grilla base; no evalúa ocupación ni el instante actual.
     * La fecha civil recibida se interpreta en la timezone canónica de la aplicación.
     *
     * @return list<array{inicio_at: CarbonImmutable, fin_at: CarbonImmutable}>
     */
    public function generarGrillaParaFecha(Kinesiologa $kinesiologa, CarbonInterface $fecha): array
    {
        $fechaCivil = CarbonImmutable::createFromFormat('!Y-m-d', $fecha->toDateString(), config('app.timezone'));
        $configuracion = $this->agenda->configuracionAplicableEn($kinesiologa, $fechaCivil);

        if ($configuracion === null) {
            return [];
        }

        $duracion = $configuracion->duracion_turno_minutos;

        if ($duracion < 15 || $duracion > 180 || $duracion % 5 !== 0) {
            throw new LogicException('La duración persistida de la configuración de agenda es inválida.');
        }

        $dia = DiaSemanaEnum::from($fechaCivil->dayOfWeekIso);
        $franjas = $configuracion->franjasHorarias
            ->filter(fn (FranjaHorariaAgenda $franja): bool => $franja->dia_semana === $dia);

        if ($franjas->isEmpty()) {
            return [];
        }

        try {
            $matriz = $this->validadorMatriz->validar($franjas->map(fn (FranjaHorariaAgenda $franja): array => [
                'id' => $franja->getKey(),
                'dia_semana' => $franja->dia_semana->value,
                'hora_inicio' => $franja->hora_inicio,
                'hora_fin' => $franja->hora_fin,
            ]), $duracion);
        } catch (InvalidArgumentException $error) {
            throw new LogicException('La matriz persistida de la configuración de agenda es inválida.', 0, $error);
        }

        $candidatos = [];

        foreach ($matriz as $franja) {
            $inicio = $fechaCivil->setTimeFromTimeString($franja['hora_inicio']);
            $cierre = $fechaCivil->setTimeFromTimeString($franja['hora_fin']);

            while (($fin = $inicio->addMinutes($duracion))->lte($cierre)) {
                $candidatos[] = ['inicio_at' => $inicio, 'fin_at' => $fin];
                $inicio = $fin;
            }
        }

        return $candidatos;
    }
}
