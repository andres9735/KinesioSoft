<?php

namespace App\Livewire\Turnos;

use App\Models\User;
use App\Models\Turno;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use App\Services\SlotService;
use Carbon\Carbon;
use Livewire\Component;

class CalendarioPaciente extends Component
{
    // Filtros
    public ?int $profesionalId = null;
    public ?int $consultorioId = null;

    // Semana y selección
    public string $fechaBase;       // lunes de la semana (Y-m-d)
    public string $diaSeleccionado; // día elegido (Y-m-d)

    // Datos auxiliares
    public array $diasSemana = [];   // [ ['fecha'=>'2025-10-20','label'=>'Lun 20'], ... ]
    public array $slotsDelDia = [];  // slots del día seleccionado
    public array $resumenPorDia = []; // ['2025-10-20' => 5, '2025-10-21' => 0, ...]

    // Config (no visibles ni editables por el paciente)
    protected int $duracionMin = 45;
    protected int $leadTimeMin = 30;
    protected int $bufferMin   = 10;

    // Ventana máxima de anticipación (días) para permitir reservas
    protected int $maxAnticipacionDias = 30;

    // Listado de profesionales (para selector)
    public array $profesionales = [];

    protected $queryString = [
        'profesionalId' => ['except' => null],
        'consultorioId' => ['except' => null],
        'fechaBase'     => ['except' => null],
    ];

    public function mount(?int $profesionalId = null, ?int $consultorioId = null): void
    {
        $this->profesionales = User::role('Kinesiologa')
            ->orderBy('name')->pluck('name', 'id')->toArray();

        // Si viene por query úsalo, si no tomá el primero disponible
        $primero = empty($this->profesionales) ? null : array_key_first($this->profesionales);
        $this->profesionalId = $profesionalId ?: $primero;

        $this->consultorioId = $consultorioId;

        $hoy   = Carbon::today();
        $lunes = $hoy->copy()->startOfWeek(Carbon::MONDAY);
        $this->fechaBase = $this->fechaBase ?? $lunes->toDateString();

        $this->diaSeleccionado = $hoy->isSameWeek(Carbon::parse($this->fechaBase), Carbon::MONDAY)
            ? $hoy->toDateString()
            : $this->fechaBase;

        $this->recalcularSemana();
        $this->cargarSlotsDelDia();
    }

    public function updatedProfesionalId(): void
    {
        $this->consultorioId = null; // reset consultorio si cambias profesional
        $this->recalcularSemana();
        $this->cargarSlotsDelDia();
    }

    public function updatedConsultorioId(): void
    {
        $this->recalcularSemana();
        $this->cargarSlotsDelDia();
    }

    /** Genera los 7 días de la semana y un resumen de slots disponibles por día */
    public function recalcularSemana(): void
    {
        $this->diasSemana = [];
        $this->resumenPorDia = [];

        if (!$this->profesionalId) {
            return;
        }

        $service = app(SlotService::class);

        $lunes = Carbon::parse($this->fechaBase)->startOfWeek(Carbon::MONDAY);
        for ($i = 0; $i < 7; $i++) {
            $fecha = $lunes->copy()->addDays($i);
            $key = $fecha->toDateString();

            // Label corto tipo "Lun 21"
            $this->diasSemana[] = [
                'fecha' => $key,
                'label' => $fecha->isoFormat('ddd D'),
            ];

            // Fuera de ventana => no mostrar disponibilidad
            if (! $this->fechaPermitida($fecha)) {
                $this->resumenPorDia[$key] = 0;
                continue;
            }

            // Contar slots del día (verde/rojo en la UI)
            $slots = $service->slotsDisponibles(
                profesionalId: $this->profesionalId,
                fecha: $fecha,
                consultorioId: $this->consultorioId,
                duracionMin: $this->duracionMin,
                leadTimeMin: $this->leadTimeMin,
                bufferMin: $this->bufferMin
            );

            $this->resumenPorDia[$key] = count($slots);
        }

        // Si el día seleccionado no cae en esta semana, setear al lunes
        if (!array_key_exists($this->diaSeleccionado, $this->resumenPorDia)) {
            $this->diaSeleccionado = $lunes->toDateString();
        }
    }

    public function cargarSlotsDelDia(): void
    {
        $this->slotsDelDia = [];

        if (!$this->profesionalId || !$this->diaSeleccionado) {
            return;
        }

        $service = app(SlotService::class);
        $fecha = Carbon::parse($this->diaSeleccionado);

        // Si la fecha está fuera de la ventana, no hay slots
        if (! $this->fechaPermitida($fecha)) {
            $this->slotsDelDia = [];
            return;
        }

        $this->slotsDelDia = $service->slotsDisponibles(
            profesionalId: $this->profesionalId,
            fecha: $fecha,
            consultorioId: $this->consultorioId,
            duracionMin: $this->duracionMin,
            leadTimeMin: $this->leadTimeMin,
            bufferMin: $this->bufferMin
        );
    }

    public function seleccionarDia(string $fecha): void
    {
        $this->diaSeleccionado = $fecha;
        $this->cargarSlotsDelDia();
    }

    public function semanaAnterior(): void
    {
        $this->fechaBase = Carbon::parse($this->fechaBase)->subWeek()->toDateString();
        $this->recalcularSemana();
        $this->cargarSlotsDelDia();
    }

    public function semanaSiguiente(): void
    {
        $this->fechaBase = Carbon::parse($this->fechaBase)->addWeek()->toDateString();
        $this->recalcularSemana();
        $this->cargarSlotsDelDia();
    }

    public function hoy(): void
    {
        $hoy = Carbon::today();
        $this->fechaBase = $hoy->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->diaSeleccionado = $hoy->toDateString();
        $this->recalcularSemana();
        $this->cargarSlotsDelDia();
    }

    public function reservar($index): void
    {
        // 0) Slot seleccionado válido
        if (! isset($this->slotsDelDia[$index])) {
            return;
        }

        $slot  = $this->slotsDelDia[$index];         // ['desde'=>'HH:mm','hasta'=>'HH:mm','consultorio_id'=>...]
        $fecha = \Carbon\Carbon::parse($this->diaSeleccionado)->startOfDay();
        $hoy   = \Carbon\Carbon::today();

        // 1) Bloqueo duro: no permitir fechas pasadas
        if ($fecha->lt($hoy)) {
            \Filament\Notifications\Notification::make()
                ->title('No se pueden reservar turnos en fechas anteriores.')
                ->danger()
                ->send();
            return;
        }

        // 2) Si es hoy, respetar lead time (ej. 30 min)
        if ($fecha->isToday()) {
            $minInicio = \Carbon\Carbon::now()->addMinutes($this->leadTimeMin)->format('H:i');
            if ($slot['desde'] < $minInicio) {
                \Filament\Notifications\Notification::make()
                    ->title('Ese horario ya no está disponible (fuera de lead time).')
                    ->warning()
                    ->send();
                return;
            }
        }

        // 3) Revalidar disponibilidad ahora mismo (por si cambió entre render y click)
        $service     = app(\App\Services\SlotService::class);
        $disponibles = $service->slotsDisponibles(
            profesionalId: $this->profesionalId,
            fecha: $fecha,
            consultorioId: $this->consultorioId,
            duracionMin: $this->duracionMin,
            leadTimeMin: $this->leadTimeMin,
            bufferMin: $this->bufferMin,
        );

        $sigueDisponible = collect($disponibles)->first(function ($s) use ($slot) {
            return $s['desde'] === $slot['desde']
                && $s['hasta'] === $slot['hasta']
                && (int) $s['consultorio_id'] === (int) $slot['consultorio_id'];
        });

        if (! $sigueDisponible) {
            \Filament\Notifications\Notification::make()
                ->title('El turno elegido ya no está disponible.')
                ->warning()
                ->send();

            // refrescar lista
            $this->cargarSlotsDelDia();
            return;
        }

        // 4) Evitar superposición con turnos del MISMO paciente
        $yaTiene = \App\Models\Turno::query()
            ->where('paciente_id', \Illuminate\Support\Facades\Auth::id())
            ->whereDate('fecha', $fecha->toDateString())
            ->whereIn('estado', ['pendiente', 'confirmado'])
            ->where('hora_desde', '<', $slot['hasta'])
            ->where('hora_hasta', '>', $slot['desde'])
            ->exists();

        if ($yaTiene) {
            \Filament\Notifications\Notification::make()
                ->title('Ya tenés otro turno que se superpone en ese horario.')
                ->warning()
                ->send();
            return;
        }

        // 5) Crear turno
        \App\Models\Turno::create([
            'profesional_id' => $this->profesionalId,
            'paciente_id'    => \Illuminate\Support\Facades\Auth::id(),
            'id_consultorio' => $slot['consultorio_id'],
            'fecha'          => $fecha,
            'hora_desde'     => $slot['desde'], // el modelo normaliza a HH:mm:ss
            'hora_hasta'     => $slot['hasta'],
            'estado'         => 'pendiente',
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Turno reservado correctamente.')
            ->success()
            ->send();

        // 6) Refrescar slots
        $this->cargarSlotsDelDia();
    }

    public function render()
    {
        return view('livewire.turnos.calendario-paciente');
    }

    // ================= Helpers =================

    /** Permite reservar entre HOY y HOY + N días (inclusive). */
    protected function fechaPermitida(Carbon $fecha): bool
    {
        $hoy = Carbon::today();
        $max = $hoy->copy()->addDays($this->maxAnticipacionDias);

        return $fecha->greaterThanOrEqualTo($hoy)
            && $fecha->lessThanOrEqualTo($max);
    }
}
