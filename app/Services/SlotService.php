<?php

namespace App\Services;

use App\Models\BloqueDisponibilidad;
use App\Models\ExcepcionDisponibilidad;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SlotService
{
    /**
     * Devuelve slots disponibles para un profesional en una fecha dada.
     *
     * @param  int         $profesionalId
     * @param  Carbon      $fecha
     * @param  int|null    $consultorioId
     * @param  int|null    $duracionMin
     * @param  int         $leadTimeMin
     * @param  int         $bufferMin
     * @return array
     */
    public function slotsDisponibles(
        int $profesionalId,
        Carbon $fecha,
        ?int $consultorioId = null,
        ?int $duracionMin = null,
        int $leadTimeMin = 0,
        int $bufferMin = 0,
    ): array {
        // 0️⃣ Día bloqueado completo → no hay slots
        $exDiaCompleto = ExcepcionDisponibilidad::query()
            ->where('profesional_id', $profesionalId)
            ->whereDate('fecha', $fecha->toDateString())
            ->where('bloqueado', true)
            ->exists();

        if ($exDiaCompleto) {
            return [];
        }

        // 1️⃣ Bloques activos del día
        $diaSemanaDb = $this->dayNumberForDb($fecha);
        $bloques = BloqueDisponibilidad::query()
            ->where('profesional_id', $profesionalId)
            ->where('dia_semana', $diaSemanaDb)
            ->where('activo', true)
            ->when($consultorioId, fn($q) => $q->where('consultorio_id', $consultorioId))
            ->orderBy('hora_desde')
            ->get();

        if ($bloques->isEmpty()) {
            return [];
        }

        // 2️⃣ Excepciones parciales (franjas bloqueadas dentro del día)
        $excepcionesParciales = ExcepcionDisponibilidad::query()
            ->where('profesional_id', $profesionalId)
            ->whereDate('fecha', $fecha->toDateString())
            ->where('bloqueado', false)
            ->whereNotNull('hora_desde')
            ->whereNotNull('hora_hasta')
            ->get()
            ->map(fn($e) => [
                'desde' => $this->atDate($fecha, $e->hora_desde),
                'hasta' => $this->atDate($fecha, $e->hora_hasta),
            ]);

        // 3️⃣ Turnos ya tomados (pendientes o confirmados)
        $ocupados = Turno::query()
            ->where('profesional_id', $profesionalId)
            ->whereDate('fecha', $fecha->toDateString())
            ->whereIn('estado', [Turno::ESTADO_PENDIENTE, Turno::ESTADO_CONFIRMADO])
            ->when($consultorioId, fn($q) => $q->where('id_consultorio', $consultorioId))
            ->get(['hora_desde', 'hora_hasta'])
            ->map(function ($t) use ($fecha, $bufferMin) {
                $desde = $this->atDate($fecha, $t->hora_desde)->subMinutes($bufferMin);
                $hasta = $this->atDate($fecha, $t->hora_hasta)->addMinutes($bufferMin);
                return ['desde' => $desde, 'hasta' => $hasta];
            });

        // 4️⃣ Calcular los huecos disponibles dentro de los bloques
        $slots = [];

        foreach ($bloques as $b) {
            $blockStart = $this->atDate($fecha, $b->hora_desde);
            $blockEnd   = $this->atDate($fecha, $b->hora_hasta);
            $duracion   = (int) ($duracionMin ?? ($b->duracion_minutos ?: 45));

            $cursor = $blockStart->copy();

            if ($leadTimeMin > 0 && $fecha->isToday()) {
                $minInicio = Carbon::now()->addMinutes($leadTimeMin);
                if ($minInicio->greaterThan($cursor)) {
                    $cursor = $this->ceilToGrid($minInicio, $duracion);
                }
            }

            while ($cursor->copy()->addMinutes($duracion) <= $blockEnd) {
                $slotStart = $cursor->copy();
                $slotEnd   = $cursor->copy()->addMinutes($duracion);

                if ($this->chocaConAlguno($slotStart, $slotEnd, $excepcionesParciales)) {
                    $cursor->addMinutes($duracion);
                    continue;
                }

                if ($this->chocaConAlguno($slotStart, $slotEnd, $ocupados)) {
                    $cursor->addMinutes($duracion);
                    continue;
                }

                $slots[] = [
                    'desde' => $slotStart->format('H:i'),
                    'hasta' => $slotEnd->format('H:i'),
                    'consultorio_id' => $b->consultorio_id,
                ];

                $cursor->addMinutes($duracion);
            }
        }

        usort($slots, fn($a, $b) => strcmp($a['desde'], $b['desde']));

        return $slots;
    }

    // ================== Helpers ==================

    protected function dayNumberForDb(Carbon $fecha): int
    {
        $iso = (int) $fecha->isoWeekday(); // 1..7
        return $iso === 7 ? 0 : $iso;
    }

    protected function atDate(Carbon $fecha, string $hhmmss): Carbon
    {
        if (strlen($hhmmss) === 5) {
            $hhmmss .= ':00';
        }
        return Carbon::parse($fecha->toDateString() . ' ' . $hhmmss);
    }

    protected function ceilToGrid(Carbon $moment, int $stepMin): Carbon
    {
        $minute = (int) $moment->format('i');
        $mod = $minute % $stepMin;
        if ($mod === 0) return $moment;
        $add = $stepMin - $mod;
        return $moment->copy()->addMinutes($add)->second(0);
    }

    protected function chocaConAlguno(Carbon $aStart, Carbon $aEnd, Collection|array $intervalos): bool
    {
        foreach ($intervalos as $it) {
            if ($this->overlaps($aStart, $aEnd, $it['desde'], $it['hasta'])) {
                return true;
            }
        }
        return false;
    }

    protected function overlaps(Carbon $aStart, Carbon $aEnd, Carbon $bStart, Carbon $bEnd): bool
    {
        return $aStart->lt($bEnd) && $aEnd->gt($bStart);
    }
}
