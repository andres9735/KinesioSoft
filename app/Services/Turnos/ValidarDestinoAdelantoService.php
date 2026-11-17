<?php

namespace App\Services\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Kinesiologa;
use App\Models\Turno;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\ValidadorMatrizAgenda;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class ValidarDestinoAdelantoService
{
    public function __construct(
        private readonly AgendaQueryService $agenda,
        private readonly ValidadorMatrizAgenda $matriz,
    ) {}

    /**
     * CA-G06/G07: sólo comprueba el destino, sin autorizar, reservar ni trasladar.
     * El consumidor aporta la programación vigente del candidato. La aceptación
     * deberá releerla y coordinar estas consultas dentro de su transacción.
     * Plazos de activación, selección y reservas pertenecen a sus propios flujos.
     *
     * @return array{inicio_at: CarbonImmutable, fin_at: CarbonImmutable}|null
     */
    public function validar(
        Turno $candidato,
        Kinesiologa $kinesiologa,
        CarbonInterface $inicioCupo,
        CarbonInterface $finCupo,
        bool $bloquear = false,
    ): ?array {
        // El caller transaccional ya posee el mutex de la kinesióloga; al activar
        // también posee Paciente -> Turno. Las lecturas actuales evitan snapshots RR.
        if ($bloquear && DB::transactionLevel() < 1) {
            throw new LogicException('La validación bloqueante requiere una transacción de agenda.');
        }
        // Los atributos sucios y las relaciones precargadas no cambian lo registrado.
        $registrado = $candidato->getRawOriginal();
        if (! $candidato->exists || ! $kinesiologa->exists || $kinesiologa->isDirty('id')
            || (int) ($registrado['id'] ?? 0) <= 0
            || (int) ($registrado['paciente_id'] ?? 0) <= 0
            || (int) ($registrado['kinesiologa_id'] ?? 0) !== (int) $kinesiologa->getRawOriginal('id')
            || ($registrado['tipo'] ?? null) !== TipoTurnoEnum::PRIMERA_CONSULTA->value
            || ! in_array($registrado['estado'] ?? null, [EstadoTurnoEnum::PENDIENTE->value, EstadoTurnoEnum::CONFIRMADO->value], true)
            || ! isset($registrado['inicio_at'], $registrado['fin_at'])) {
            return null;
        }

        $zona = (string) config('app.timezone');
        $inicioOriginal = CarbonImmutable::parse($registrado['inicio_at'], $zona);
        $finOriginal = CarbonImmutable::parse($registrado['fin_at'], $zona);
        $inicio = CarbonImmutable::instance($inicioCupo)->setTimezone($zona);
        $limite = CarbonImmutable::instance($finCupo)->setTimezone($zona);
        if (! $finOriginal->gt($inicioOriginal) || ! $limite->gt($inicio)) {
            return null;
        }

        $fin = $inicio->addMicroseconds($inicioOriginal->diffInMicroseconds($finOriginal));
        if ($fin->gt($limite)) {
            return null;
        }

        $configuracion = $bloquear
            ? $this->agenda->seleccionarConfiguracionAplicable($this->agenda->configuracionesAplicablesEnQuery($kinesiologa, $inicio)->lockForUpdate()->get())
            : $this->agenda->configuracionAplicableEn($kinesiologa, $inicio);
        if ($configuracion === null) {
            return null;
        }
        if ($bloquear) {
            $configuracion->setRelation('franjasHorarias', $this->agenda->franjasDe($configuracion)->lockForUpdate()->get());
        }

        $duracionEstandar = $configuracion->duracion_turno_minutos;
        if ($duracionEstandar < 15 || $duracionEstandar > 180 || $duracionEstandar % 5 !== 0) {
            throw new LogicException('La duración persistida de la configuración de agenda es inválida.');
        }
        $franjas = $configuracion->franjasHorarias
            ->filter(fn ($franja): bool => $franja->dia_semana->value === $inicio->dayOfWeekIso);
        if ($franjas->isEmpty()) {
            return null;
        }
        try {
            $normalizadas = $this->matriz->validar($franjas->map(fn ($franja): array => [
                'id' => $franja->getKey(), 'dia_semana' => $franja->dia_semana->value,
                'hora_inicio' => $franja->hora_inicio, 'hora_fin' => $franja->hora_fin,
            ]), $duracionEstandar);
        } catch (InvalidArgumentException $error) {
            throw new LogicException('La matriz persistida de la configuración de agenda es inválida.', 0, $error);
        }

        $contenido = false;
        foreach ($normalizadas as $franja) {
            $apertura = $inicio->startOfDay()->setTimeFromTimeString($franja['hora_inicio']);
            $cierre = $inicio->startOfDay()->setTimeFromTimeString($franja['hora_fin']);
            if ($inicio->gte($apertura) && $fin->lte($cierre)) {
                $contenido = true;
                break;
            }
        }
        if (! $contenido) {
            return null;
        }
        $cruces = [
            $this->agenda->bloqueosSuperpuestosCon($kinesiologa, $inicio, $fin),
            $this->agenda->turnosAfectadosPorIntervalo($kinesiologa, $inicio, $fin)->whereKeyNot($registrado['id']),
            $this->agenda->turnosDePacienteAfectadosPorIntervalo((int) $registrado['paciente_id'], $inicio, $fin)->whereKeyNot($registrado['id']),
        ];
        foreach ($cruces as $consulta) {
            if ($bloquear ? $consulta->lockForUpdate()->get()->isNotEmpty() : $consulta->exists()) {
                return null;
            }
        }

        return ['inicio_at' => $inicio, 'fin_at' => $fin];
    }
}
