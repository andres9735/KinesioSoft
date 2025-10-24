<?php

namespace App\Filament\Paciente\Pages;

use App\Models\Turno;
use App\Models\User;
use App\Services\SlotService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

class SolicitarTurno extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-calendar';
    protected static ?string $title           = 'Solicitar turno';
    protected static ?string $navigationGroup = 'Turnos';
    protected static string $view             = 'filament.paciente.pages.solicitar-turno';
    protected static ?int    $navigationSort  = 10;

    /** Select de profesionales: [id => nombre] */
    public array $profesionales = [];

    /** Parámetros de búsqueda */
    public ?int $profesionalId = null;
    public ?string $fecha = null;          // Y-m-d
    public ?int $consultorioId = null;     // opcional

    /** Config de slots (fija para paciente; no se exponen en la UI) */
    protected int $duracionMin = 45;
    protected int $leadTimeMin = 30;
    protected int $bufferMin   = 10;

    /** Resultado de slots disponibles */
    public array $slots = [];

    /** Autenticado (paciente) */
    public int $pacienteId;

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();
        return $u?->hasAnyRole(['Paciente', 'Administrador']) ?? false;
    }

    public function mount(): void
    {
        $user = Filament::auth()->user();
        $this->pacienteId = (int) $user->id;

        $this->profesionales = User::role('Kinesiologa')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $this->profesionalId = array_key_first($this->profesionales) ?: null;
        $this->fecha         = Carbon::today()->toDateString();

        $this->buscarSlots();
    }

    /** Al cambiar profesional/fecha/consultorio, recalcular */
    public function updated($prop): void
    {
        if (in_array($prop, ['profesionalId', 'fecha', 'consultorioId'], true)) {
            $this->buscarSlots();
        }
    }

    public function buscarSlots(): void
    {
        $this->slots = [];

        if (!$this->profesionalId || !$this->fecha) {
            return;
        }

        $svc = app(SlotService::class);

        $this->slots = $svc->slotsDisponibles(
            profesionalId: $this->profesionalId,
            fecha: Carbon::parse($this->fecha),
            consultorioId: $this->consultorioId,
            duracionMin: $this->duracionMin,
            leadTimeMin: $this->leadTimeMin,
            bufferMin: $this->bufferMin,
        );
    }

    /** Reservar un slot (índice dentro de $this->slots) */
    public function reservar(int $index): void
    {
        // 0) Slot válido
        if (!isset($this->slots[$index])) {
            Notification::make()->title('El horario ya no está disponible.')->danger()->send();
            $this->buscarSlots();
            return;
        }
        $slot  = $this->slots[$index];
        $fecha = Carbon::parse($this->fecha)->startOfDay();

        // 1) No permitir fechas pasadas
        if ($fecha->isPast() && ! $fecha->isToday()) {
            Notification::make()->title('No podés reservar en una fecha pasada.')->danger()->send();
            return;
        }

        // 2) Lead time para hoy
        if ($fecha->isToday()) {
            $minInicio = Carbon::now()->addMinutes($this->leadTimeMin)->format('H:i');
            if ($slot['desde'] < $minInicio) {
                Notification::make()->title('Ese horario ya no está disponible por proximidad.')->warning()->send();
                return;
            }
        }

        // 3) Revalidar disponibilidad (por si cambió algo mientras el usuario miraba la lista)
        $svc = app(SlotService::class);
        $aunDisponibles = collect($svc->slotsDisponibles(
            profesionalId: $this->profesionalId,
            fecha: $fecha,
            consultorioId: $this->consultorioId,
            duracionMin: $this->duracionMin,
            leadTimeMin: $this->leadTimeMin,
            bufferMin: $this->bufferMin,
        ));

        $sigueLibre = $aunDisponibles->first(
            fn($s) =>
            $s['desde'] === $slot['desde'] &&
                $s['hasta'] === $slot['hasta'] &&
                (int)($s['consultorio_id'] ?? 0) === (int)($slot['consultorio_id'] ?? 0)
        );

        if (! $sigueLibre) {
            Notification::make()->title('El horario ya no está disponible.')->danger()->send();
            $this->buscarSlots();
            return;
        }

        // 4) Evitar superposición de turnos del mismo paciente ese día
        $overlap = Turno::where('paciente_id', $this->pacienteId)
            ->whereDate('fecha', $fecha->toDateString())
            ->whereIn('estado', [Turno::ESTADO_PENDIENTE, Turno::ESTADO_CONFIRMADO])
            ->where('hora_desde', '<', $slot['hasta'])
            ->where('hora_hasta', '>', $slot['desde'])
            ->exists();

        if ($overlap) {
            Notification::make()->title('Ya tenés un turno que se superpone ese día.')->warning()->send();
            return;
        }

        // 5) Crear turno; si alguien lo tomó justo antes, la UNIQUE dispara excepción
        try {
            Turno::create([
                'profesional_id' => $this->profesionalId,
                'paciente_id'    => $this->pacienteId,
                'id_consultorio' => $slot['consultorio_id'] ?? null,
                'fecha'          => $fecha->toDateString(),
                'hora_desde'     => $slot['desde'],
                'hora_hasta'     => $slot['hasta'],
                'estado'         => Turno::ESTADO_PENDIENTE,
            ]);
        } catch (QueryException $e) {
            Notification::make()
                ->title('Ese horario se reservó recién.')
                ->danger()
                ->body('Elegí otro turno, por favor.')
                ->send();

            $this->buscarSlots();
            return;
        }

        Notification::make()
            ->title('¡Turno reservado!')
            ->success()
            ->body("{$slot['desde']}–{$slot['hasta']} para el {$fecha->toDateString()}.")
            ->send();

        $this->buscarSlots(); // para que desaparezca el slot
    }
}
