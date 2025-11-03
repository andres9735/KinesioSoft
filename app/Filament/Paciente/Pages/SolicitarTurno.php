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
    protected static string  $view            = 'filament.paciente.pages.solicitar-turno';
    protected static ?int    $navigationSort  = 10;

    /** Listado de profesionales [id => nombre] */
    public array $profesionales = [];

    /** Parámetros */
    public ?int $profesionalId = null;
    public ?string $fecha = null;          // Y-m-d
    public ?int $consultorioId = null;     // opcional

    /** Toggles UX */
    public bool $eligeProfesional = false;
    public bool $eligeFecha       = false;

    /** Config interna de slots */
    protected int $duracionMin = 45;
    protected int $leadTimeMin = 30;
    protected int $bufferMin   = 10;

    /** Resultados */
    public array $slots = [];

    /** Sugerencias (lista) para el flujo “consultar ahora” */
    public array $sugeridos = []; // [['fecha','desde','hasta','consultorio_id','profesional_id','profesional','especialidad','rating_*'], ...]

    /** (Opcional) sugerencia individual – compatibilidad */
    public ?array $sugerido = null;

    /** Paciente autenticado */
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

        // Defaults
        $this->fecha = Carbon::today()->toDateString();
        $this->slots = [];

        // Precargar varias sugerencias (sin exigir que elija nada)
        $this->sugerirProximoLista();
    }

    public function updated($prop): void
    {
        // Si destilda profesional/fecha, limpiamos parámetros y recomputamos
        if ($prop === 'eligeProfesional' && $this->eligeProfesional === false) {
            $this->profesionalId = null;
        }
        if ($prop === 'eligeFecha' && $this->eligeFecha === false) {
            $this->slots = [];
        }

        // Cambios que requieren recalcular
        if (in_array($prop, ['eligeProfesional', 'profesionalId', 'consultorioId'], true)) {
            $this->slots = [];
            $this->eligeFecha ? $this->buscarSlots() : $this->sugerirProximoLista();
        }

        if (in_array($prop, ['eligeFecha', 'fecha'], true)) {
            $this->slots = [];
            $this->eligeFecha ? $this->buscarSlots() : $this->sugerirProximoLista();
        }
    }

    /* ======================================================
     |                    BÚSQUEDAS
     |======================================================*/

    public function buscarSlots(): void
    {
        $this->slots = [];

        if (!$this->eligeFecha || !$this->fecha) {
            return;
        }

        $fechaSel = Carbon::parse($this->fecha);

        // Bloquear días pasados (excepto hoy)
        if ($fechaSel->isPast() && !$fechaSel->isToday()) {
            Notification::make()
                ->title('No podés reservar en fechas pasadas.')
                ->warning()
                ->send();
            return;
        }

        $svc = new SlotService();

        // ===== CASO A: con profesional elegido → mantener UX, pero ENRIQUECIDO =====
        if ($this->profesionalId) {
            $prof = User::select('id', 'name', 'specialty', 'rating_avg', 'rating_count')
                ->find($this->profesionalId);

            $base = $svc->slotsDisponibles(
                profesionalId: $this->profesionalId,
                fecha: $fechaSel,
                consultorioId: $this->consultorioId,
                duracionMin: $this->duracionMin,
                leadTimeMin: $this->leadTimeMin,
                bufferMin: $this->bufferMin,
            );

            $this->slots = array_map(function (array $s) use ($prof) {
                return [
                    'desde'          => $s['desde'],
                    'hasta'          => $s['hasta'],
                    'consultorio_id' => $s['consultorio_id'] ?? null,
                    'profesional_id' => $prof?->id,
                    'profesional'    => $prof?->name ?? 'Profesional',
                    'especialidad'   => $prof?->specialty,
                    'rating_avg'     => $prof?->rating_avg,
                    'rating_count'   => $prof?->rating_count,
                ];
            }, $base);

            return;
        }

        // ===== CASO B: sin profesional elegido → traer TODOS y ENRIQUECER =====
        $profIds = array_keys($this->profesionales);
        if ($profIds === []) {
            $this->slots = [];
            return;
        }

        // Prefetch para evitar N+1
        $profMeta = User::role('Kinesiologa')
            ->select('id', 'name', 'specialty', 'rating_avg', 'rating_count')
            ->whereIn('id', $profIds)
            ->get()
            ->keyBy('id');

        $todos = [];
        foreach ($profIds as $pid) {
            $slots = $svc->slotsDisponibles(
                profesionalId: $pid,
                fecha: $fechaSel,
                consultorioId: $this->consultorioId,
                duracionMin: $this->duracionMin,
                leadTimeMin: $this->leadTimeMin,
                bufferMin: $this->bufferMin,
            );

            $prof = $profMeta->get($pid);
            foreach ($slots as $s) {
                $todos[] = [
                    'desde'          => $s['desde'],
                    'hasta'          => $s['hasta'],
                    'consultorio_id' => $s['consultorio_id'] ?? null,
                    'profesional_id' => $pid,
                    'profesional'    => $prof?->name ?? ($this->profesionales[$pid] ?? 'Profesional'),
                    'especialidad'   => $prof?->specialty,
                    'rating_avg'     => $prof?->rating_avg,
                    'rating_count'   => $prof?->rating_count,
                ];
            }
        }

        // Ordenar por hora y luego por profesional
        usort($todos, fn($a, $b) => [$a['desde'], $a['profesional']] <=> [$b['desde'], $b['profesional']]);

        $this->slots = $todos;
    }

    /**
     * Acción del botón “Consultar ahora”
     * Decide según el estado de los checkboxes qué mostrar.
     */
    public function consultarAhora(): void
    {
        // Limpio resultados
        $this->slots = [];
        $this->sugeridos = [];
        $this->sugerido = null;

        // Si se consulta por día, SIEMPRE llamamos a buscarSlots()
        if ($this->eligeFecha) {
            $this->buscarSlots();
            return;
        }

        // Sin día → sugerencias
        $this->sugerirProximoLista();
    }

    /** Llena $sugeridos con las primeras N sugerencias, enriquecidas */
    public function sugerirProximoLista(int $limite = 3): void
    {
        $this->sugeridos = $this->buscarSiguientesDisponibles($limite);
        $this->sugerido = $this->sugeridos[0] ?? null;
    }

    /** (Compatibilidad) sugerencia individual */
    public function sugerirProximo(): void
    {
        $uno = $this->buscarSiguientesDisponibles(1);
        $this->sugerido = $uno[0] ?? null;
    }

    /** Devuelve primeras N sugerencias entre todos (o el elegido), ENRIQUECIDAS */
    protected function buscarSiguientesDisponibles(int $limite = 3, int $diasHaciaAdelante = 30): array
    {
        $resultado = [];
        if (empty($this->profesionales)) {
            return $resultado;
        }

        $svc    = new SlotService();
        $desde  = Carbon::today();
        $hasta  = $desde->copy()->addDays($diasHaciaAdelante);

        // Si elige profesional => buscamos sólo en ese, si no en todos
        $profIds = $this->eligeProfesional && $this->profesionalId
            ? [$this->profesionalId]
            : array_keys($this->profesionales);

        // Prefetch meta de profesionales para enriquecer sin N+1
        $profMeta = User::select('id', 'name', 'specialty', 'rating_avg', 'rating_count')
            ->whereIn('id', $profIds)
            ->get()
            ->keyBy('id');

        for ($fecha = $desde->copy(); $fecha->lte($hasta); $fecha->addDay()) {
            foreach ($profIds as $pid) {
                $slots = $svc->slotsDisponibles(
                    profesionalId: $pid,
                    fecha: $fecha,
                    consultorioId: $this->consultorioId,
                    duracionMin: $this->duracionMin,
                    leadTimeMin: $this->leadTimeMin,
                    bufferMin: $this->bufferMin,
                );

                $prof = $profMeta->get($pid);
                foreach ($slots as $s) {
                    $resultado[] = [
                        'fecha'          => $fecha->toDateString(),
                        'desde'          => $s['desde'],
                        'hasta'          => $s['hasta'],
                        'consultorio_id' => $s['consultorio_id'] ?? null,
                        'profesional_id' => $pid,
                        'profesional'    => $prof?->name ?? ($this->profesionales[$pid] ?? 'Profesional'),
                        'especialidad'   => $prof?->specialty,
                        'rating_avg'     => $prof?->rating_avg,
                        'rating_count'   => $prof?->rating_count,
                    ];
                    if (count($resultado) >= $limite) {
                        return $resultado;
                    }
                }
            }
        }

        return $resultado;
    }

    /* ======================================================
     |                    RESERVAS
     |======================================================*/

    /** Reservar uno de los slots listados para un día elegido manualmente. */
    public function reservar(int $index): void
    {
        if (!$this->eligeFecha || !$this->fecha) {
            return;
        }

        $fechaSel = Carbon::parse($this->fecha);

        // Seguridad: no permitir fechas pasadas
        if ($fechaSel->isPast() && !$fechaSel->isToday()) {
            Notification::make()
                ->title('No podés reservar en fechas pasadas.')
                ->danger()
                ->send();
            return;
        }

        if (!isset($this->slots[$index])) {
            Notification::make()->title('El horario ya no está disponible.')->danger()->send();
            $this->buscarSlots();
            return;
        }

        $slot   = $this->slots[$index];
        $profId = $slot['profesional_id'] ?? $this->profesionalId; // soporta “todos los profesionales”

        if (!$profId) {
            Notification::make()->title('Falta el profesional en el turno seleccionado.')->danger()->send();
            return;
        }

        try {
            Turno::create([
                'profesional_id' => $profId,
                'paciente_id'    => $this->pacienteId,
                'id_consultorio' => $slot['consultorio_id'] ?? null,
                'fecha'          => $fechaSel->toDateString(),
                'hora_desde'     => $slot['desde'],
                'hora_hasta'     => $slot['hasta'],
                'estado'         => Turno::ESTADO_PENDIENTE,
                'motivo'         => null,
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
            ->body("{$slot['desde']}–{$slot['hasta']} para el {$fechaSel->isoFormat('DD/MM/YYYY')}.")
            ->send();

        $this->buscarSlots();
        $this->sugerirProximoLista();
    }

    /** Reservar cualquiera de los turnos sugeridos (por índice) */
    public function reservarSugerido(int $index): void
    {
        if (!isset($this->sugeridos[$index])) {
            return;
        }

        $s = $this->sugeridos[$index];
        $fecha = Carbon::parse($s['fecha']);

        // Revalidar que sigue disponible
        $svc = new SlotService();
        $still = collect(
            $svc->slotsDisponibles(
                profesionalId: $s['profesional_id'],
                fecha: $fecha,
                consultorioId: $this->consultorioId,
                duracionMin: $this->duracionMin,
                leadTimeMin: $this->leadTimeMin,
                bufferMin: $this->bufferMin,
            )
        )->first(fn($x) => $x['desde'] === $s['desde'] && $x['hasta'] === $s['hasta']);

        if (!$still) {
            Notification::make()->title('El turno sugerido ya no está disponible.')->danger()->send();
            $this->sugerirProximoLista();
            return;
        }

        try {
            Turno::create([
                'profesional_id' => $s['profesional_id'],
                'paciente_id'    => $this->pacienteId,
                'id_consultorio' => $s['consultorio_id'] ?? null,
                'fecha'          => $s['fecha'],
                'hora_desde'     => $s['desde'],
                'hora_hasta'     => $s['hasta'],
                'estado'         => Turno::ESTADO_PENDIENTE,
                'motivo'         => null,
            ]);
        } catch (\Throwable $e) {
            Notification::make()->title('Ese horario se reservó recién.')->danger()->send();
            $this->sugerirProximoLista();
            return;
        }

        Notification::make()
            ->title('¡Turno reservado!')
            ->success()
            ->body("{$s['desde']}–{$s['hasta']} el {$fecha->isoFormat('DD/MM/YYYY')} con {$s['profesional']}.")
            ->send();

        // Refrescar lista para que desaparezca el reservado
        $this->sugerirProximoLista();
    }
}
