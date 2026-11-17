<?php

namespace App\Services\Turnos;

use App\Enums\DiaSemanaEnum;
use App\Exceptions\Turnos\ProfesionalNoElegibleException;
use App\Models\BloqueoAgenda;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\OfertaAdelanto;
use App\Models\Turno;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\RelojAplicacion;
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
        private readonly ElegibilidadKinesiologaService $elegibilidad,
        private readonly RelojAplicacion $reloj,
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

        return $this->generarGrillaDesdeConfiguracion($configuracion, $fechaCivil);
    }

    /**
     * Recibe una configuración ya seleccionada por Agenda y sus franjas ya releídas.
     * No ejecuta consultas; permite reutilizar la grilla con fuentes autoritativas futuras.
     *
     * @return list<array{inicio_at: CarbonImmutable, fin_at: CarbonImmutable}>
     */
    public function generarGrillaDesdeConfiguracion(ConfiguracionAgenda $configuracion, CarbonInterface $fecha): array
    {
        if (! $configuracion->relationLoaded('franjasHorarias')) {
            throw new LogicException('Las franjas deben estar cargadas antes de evaluar la grilla.');
        }

        $fechaCivil = CarbonImmutable::createFromFormat('!Y-m-d', $fecha->toDateString(), config('app.timezone'));
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

    /**
     * Las fechas civiles desde/hasta son inclusivas; el límite acota la consulta, no la anticipación.
     *
     * @return list<array{inicio_at: CarbonImmutable, fin_at: CarbonImmutable}>
     */
    public function consultarDisponibilidad(Kinesiologa $kinesiologa, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $desde = CarbonImmutable::createFromFormat('!Y-m-d', $desde->toDateString(), config('app.timezone'));
        $hasta = CarbonImmutable::createFromFormat('!Y-m-d', $hasta->toDateString(), config('app.timezone'));

        if ($hasta->lt($desde)
            || $desde->diffInDays($hasta) + 1 > config('turnos.disponibilidad.max_dias_por_consulta')) {
            throw new InvalidArgumentException('La ventana de disponibilidad tiene un orden o tamaño inválido.');
        }

        // No reutilizar relaciones posiblemente precargadas antes de un cambio de perfil.
        $actual = $kinesiologa->fresh();
        $esElegible = $actual !== null && $this->elegibilidad->esElegible($actual);
        $this->exigirElegibilidad($esElegible);

        $finVentana = $hasta->addDay();
        $turnos = $this->agenda->turnosAfectadosPorIntervalo($kinesiologa, $desde, $finVentana)->get();
        $bloqueos = $this->agenda->bloqueosSuperpuestosCon($kinesiologa, $desde, $finVentana)->get();
        $reservas = $this->agenda->reservasSuperpuestasCon($kinesiologa, $desde, $finVentana)->get();
        $candidatos = [];

        for ($fecha = $desde; $fecha->lte($hasta); $fecha = $fecha->addDay()) {
            array_push($candidatos, ...$this->generarGrillaParaFecha($kinesiologa, $fecha));
        }

        return $this->evaluarCandidatosDisponibles($candidatos, $turnos, $bloqueos, $this->reloj->ahora(), $esElegible, $reservas);
    }

    /** @return array{inicio_at: CarbonImmutable, fin_at: CarbonImmutable}|null */
    public function revalidarSlot(Kinesiologa $kinesiologa, CarbonInterface $inicioAt): ?array
    {
        $inicio = CarbonImmutable::instance($inicioAt)->setTimezone(config('app.timezone'));

        foreach ($this->consultarDisponibilidad($kinesiologa, $inicio, $inicio) as $candidato) {
            if ($candidato['inicio_at']->equalTo($inicio)) {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * Evaluación sin SQL. El caller aporta grilla canónica, ocupaciones/bloqueos ya
     * seleccionados por Agenda y elegibilidad ya evaluada por su regla canónica.
     * 7.11 podrá aportar esas mismas fuentes obtenidas mediante lecturas bloqueantes.
     *
     * @param  list<array{inicio_at: CarbonImmutable, fin_at: CarbonImmutable}>  $candidatos
     * @param  iterable<Turno>  $turnosOcupantes
     * @param  iterable<BloqueoAgenda>  $bloqueosEfectivos
     * @param  iterable<OfertaAdelanto>  $reservas
     * @return list<array{inicio_at: CarbonImmutable, fin_at: CarbonImmutable}>
     */
    public function evaluarCandidatosDisponibles(
        array $candidatos,
        iterable $turnosOcupantes,
        iterable $bloqueosEfectivos,
        CarbonInterface $ahora,
        bool $profesionalElegible,
        iterable $reservas = [],
    ): array {
        $this->exigirElegibilidad($profesionalElegible);
        $ocupaciones = [...$turnosOcupantes, ...$bloqueosEfectivos];
        foreach ($reservas as $reserva) {
            if ($reserva->reservaVigenteEn($ahora)) {
                $ocupaciones[] = $reserva;
            }
        }
        $disponibles = [];

        foreach ($candidatos as $candidato) {
            if (! $candidato['inicio_at']->gt($ahora)) {
                continue;
            }

            foreach ($ocupaciones as $ocupacion) {
                if ($ocupacion->inicio_at->lt($candidato['fin_at']) && $ocupacion->fin_at->gt($candidato['inicio_at'])) {
                    continue 2;
                }
            }

            $disponibles[] = $candidato;
        }

        return $disponibles;
    }

    private function exigirElegibilidad(bool $esElegible): void
    {
        if (! $esElegible) {
            throw new ProfesionalNoElegibleException('La profesional no cumple las condiciones para atender.');
        }
    }
}
