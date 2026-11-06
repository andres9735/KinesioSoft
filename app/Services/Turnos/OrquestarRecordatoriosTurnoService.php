<?php

namespace App\Services\Turnos;

use App\Services\Agenda\RelojAplicacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use LogicException;
use RuntimeException;
use Throwable;

/** Selección durable automática. El transporte en cola pertenece al siguiente bloque. */
final class OrquestarRecordatoriosTurnoService
{
    public const MUTEX = 'uc27:seleccion-recordatorios';

    public function __construct(
        private SeleccionarRecordatoriosTurnoService $selector,
        private RelojAplicacion $reloj,
        private PrepararContenidoRecordatorioTurnoService $contenido,
    ) {}

    /** null: omitida por horario/solapamiento; 0: ejecución normal sin candidatos. */
    public function ejecutar(): ?int
    {
        if (! app()->runningInConsole() || request()->route() !== null || Auth::check()) {
            throw new LogicException('La selección automática requiere un proceso interno.');
        }
        if (! $this->dentroHorario()) {
            return null;
        }
        $lock = Cache::lock(self::MUTEX, 300);
        if (! $lock->get()) {
            return null;
        }
        try {
            $ids = $this->selector->seleccionar();
            foreach ($ids as $id) {
                // Sólo referencias cifradas durables; no renderizar, descifrar ni enviar.
                $this->contenido->preparar($id);
            }

            return count($ids);
        } catch (Throwable) {
            // No transportar la excepción original, SQL, parámetros o credenciales.
            // Sin candidato persistido no fabricar un objetivo de AuditoriaService.
            // Las incidencias de cada recordatorio se conservan en su propio registro.
            Log::error('No pudo completarse la selección automática de recordatorios.');
            throw new RuntimeException('No pudo completarse la selección automática de recordatorios.');
        } finally {
            $lock->release();
        }
    }

    private function dentroHorario(): bool
    {
        $inicio = config('recordatorios.horario_inicio');
        $fin = config('recordatorios.horario_fin');
        if ($inicio === null && $fin === null) {
            return true;
        }
        foreach ([$inicio, $fin] as $hora) {
            if (! is_string($hora) || preg_match('/\\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\\z/', $hora) !== 1) {
                throw new LogicException('La franja operativa requiere dos horas locales HH:mm.');
            }
        }
        $hora = $this->reloj->ahora()->format('H:i');

        return $inicio <= $fin ? $hora >= $inicio && $hora <= $fin : $hora >= $inicio || $hora <= $fin;
    }
}
