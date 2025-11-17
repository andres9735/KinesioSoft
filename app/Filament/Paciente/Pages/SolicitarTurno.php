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

    /** Listado de profesionales [id => label] */
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
    public array $sugeridos = [];

    /** (Compatibilidad) sugerencia individual */
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

        // >>> Opciones del select con “Nombre (Especialidad)”
        $this->profesionales = User::role('Kinesiologa')
            ->orderBy('name')
            ->get(['id', 'name', 'specialty']) // evita N+1 en el accessor
            ->mapWithKeys(fn($u) => [$u->id => $u->name_with_specialty])
            ->toArray();

        // Defaults
        $this->fecha = Carbon::today()->toDateString();
        $this->slots = [];

        // Precargar sugerencias
        $this->sugerirProximoLista();
    }

    public function updated($prop): void
    {
        if ($prop === 'eligeProfesional' && $this->eligeProfesional === false) {
            $this->profesionalId = null;
        }
        if ($prop === 'eligeFecha' && $this->eligeFecha === false) {
            $this->slots = [];
        }

        if (in_array($prop, ['eligeProfesional', 'profesionalId', 'consultorioId'], true)) {
            $this->slots = [];
            $this->eligeFecha ? $this->buscarSlots() : $this->sugerirProximoLista();
        }

        if (in_array($prop, ['eligeFecha', 'fecha'], true)) {
            $this->slots = [];
            $this->eligeFecha ? $this->buscarSlots() : $this->sugerirProximoLista();
        }
    }

    /* ===================== BÚSQUEDAS ===================== */

    public function buscarSlots(): void
    {
        $this->slots = [];

        if (!$this->eligeFecha || !$this->fecha) return;

        $fechaSel = Carbon::parse($this->fecha);

        if ($fechaSel->isPast() && !$fechaSel->isToday()) {
            Notification::make()->title('No podés reservar en fechas pasadas.')->warning()->send();
            return;
        }

        $svc = new SlotService();

        // CASO A: con profesional elegido
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
                    'profesional'    => $prof?->name_with_specialty ?? 'Profesional',
                    'especialidad'   => $prof?->specialty,
                    'rating_avg'     => $prof?->rating_avg,
                    'rating_count'   => $prof?->rating_count,
                ];
            }, $base);

            return;
        }

        // CASO B: sin profesional elegido
        $profIds = array_keys($this->profesionales);
        if ($profIds === []) {
            $this->slots = [];
            return;
        }

        $profMeta = User::role('Kinesiologa')
            ->select('id', 'name', 'specialty', 'rating_avg', 'rating_count')
            ->whereIn('id', $profIds)
            ->get()
            ->keyBy('id');

        $todos = [];
        foreach ($profIds as $pid) {
            $slots = (new SlotService())->slotsDisponibles(
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
                    'profesional'    => $prof?->name_with_specialty ?? ($this->profesionales[$pid] ?? 'Profesional'),
                    'especialidad'   => $prof?->specialty,
                    'rating_avg'     => $prof?->rating_avg,
                    'rating_count'   => $prof?->rating_count,
                ];
            }
        }

        usort($todos, fn($a, $b) => [$a['desde'], $a['profesional']] <=> [$b['desde'], $b['profesional']]);
        $this->slots = $todos;
    }

    public function consultarAhora(): void
    {
        $this->slots = [];
        $this->sugeridos = [];
        $this->sugerido = null;

        if ($this->eligeFecha) {
            $this->buscarSlots();
            return;
        }
        $this->sugerirProximoLista();
    }

    public function sugerirProximoLista(int $limite = 3): void
    {
        $this->sugeridos = $this->buscarSiguientesDisponibles($limite);
        $this->sugerido  = $this->sugeridos[0] ?? null;
    }

    public function sugerirProximo(): void
    {
        $uno = $this->buscarSiguientesDisponibles(1);
        $this->sugerido = $uno[0] ?? null;
    }

    protected function buscarSiguientesDisponibles(int $limite = 3, int $diasHaciaAdelante = 30): array
    {
        $resultado = [];
        if (empty($this->profesionales)) return $resultado;

        $svc    = new SlotService();
        $desde  = Carbon::today();
        $hasta  = $desde->copy()->addDays($diasHaciaAdelante);

        $profIds = $this->eligeProfesional && $this->profesionalId
            ? [$this->profesionalId]
            : array_keys($this->profesionales);

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
                        'profesional'    => $prof?->name_with_specialty ?? ($this->profesionales[$pid] ?? 'Profesional'),
                        'especialidad'   => $prof?->specialty,
                        'rating_avg'     => $prof?->rating_avg,
                        'rating_count'   => $prof?->rating_count,
                    ];
                    if (count($resultado) >= $limite) return $resultado;
                }
            }
        }

        return $resultado;
    }

    /* ===================== RESERVAS ===================== */

    public function reservar(int $index): void
    {
        if (!$this->eligeFecha || !$this->fecha) return;

        $fechaSel = Carbon::parse($this->fecha);
        if ($fechaSel->isPast() && !$fechaSel->isToday()) {
            Notification::make()->title('No podés reservar en fechas pasadas.')->danger()->send();
            return;
        }

        if (!isset($this->slots[$index])) {
            Notification::make()->title('El horario ya no está disponible.')->danger()->send();
            $this->buscarSlots();
            return;
        }

        $slot   = $this->slots[$index];
        $profId = $slot['profesional_id'] ?? $this->profesionalId;

        if (!$profId) {
            Notification::make()->title('Falta el profesional en el turno seleccionado.')->danger()->send();
            return;
        }

        if ($this->tienePendienteConProfesional($profId)) {
            Notification::make()
                ->title('Un paciente no puede tener más de un turno pendiente con el mismo profesional.')
                ->body('Confirmá, asistí o cancelá el turno pendiente antes de solicitar otro.')
                ->danger()->send();
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
            $msg = $e->getMessage();
            $isDup = $e->getCode() === '23000' || str_contains($msg, '1062');
            $isPendienteUnique = $isDup && (
                str_contains($msg, 'pendiente_guard') ||
                str_contains($msg, 'turnos_paciente_profesional_pendiente_guard_unique')
            );

            if ($isPendienteUnique) {
                Notification::make()
                    ->title('Un paciente no puede tener más de un turno pendiente con el mismo profesional.')
                    ->body('Cancelá o completá el turno pendiente antes de solicitar otro.')
                    ->danger()->send();
            } else {
                Notification::make()
                    ->title('Ese horario se reservó recién.')
                    ->body('Elegí otro turno, por favor.')
                    ->danger()->send();
            }

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

    public function reservarSugerido(int $index): void
    {
        if (!isset($this->sugeridos[$index])) return;

        $s = $this->sugeridos[$index];
        $fecha = Carbon::parse($s['fecha']);

        if ($this->tienePendienteConProfesional((int) $s['profesional_id'])) {
            Notification::make()
                ->title('Un paciente no puede tener más de un turno pendiente con el mismo profesional.')
                ->body('Confirmá, asistí o cancelá el turno pendiente antes de solicitar otro.')
                ->danger()->send();
            return;
        }

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
            $msg = $e->getMessage();
            $isDup = ($e instanceof QueryException) && ($e->getCode() === '23000' || str_contains($msg, '1062'));
            $isPendienteUnique = $isDup && (
                str_contains($msg, 'pendiente_guard') ||
                str_contains($msg, 'turnos_paciente_profesional_pendiente_guard_unique')
            );

            if ($isPendienteUnique) {
                Notification::make()
                    ->title('Un paciente no puede tener más de un turno pendiente con el mismo profesional.')
                    ->body('Cancelá o completá el turno pendiente antes de solicitar otro.')
                    ->danger()->send();
            } else {
                Notification::make()
                    ->title('Ese horario se reservó recién.')
                    ->danger()
                    ->body('Elegí otro turno, por favor.')
                    ->send();
            }

            $this->sugerirProximoLista();
            return;
        }

        Notification::make()
            ->title('¡Turno reservado!')
            ->success()
            ->body("{$s['desde']}–{$s['hasta']} el {$fecha->isoFormat('DD/MM/YYYY')} con {$s['profesional']}.")
            ->send();

        $this->sugerirProximoLista();
    }

    /* ===================== HELPERS ===================== */

    protected function tienePendienteConProfesional(int $profesionalId): bool
    {
        $hoy = now()->toDateString();
        $ahora = now()->format('H:i:s');

        return Turno::query()
            ->where('paciente_id', $this->pacienteId)
            ->where('profesional_id', $profesionalId)
            ->where('estado', Turno::ESTADO_PENDIENTE)
            ->where(function ($q) use ($hoy, $ahora) {
                $q->whereDate('fecha', '>', $hoy)
                    ->orWhere(function ($qq) use ($hoy, $ahora) {
                        $qq->whereDate('fecha', $hoy)
                            ->where('hora_hasta', '>', $ahora);
                    });
            })
            ->exists();
    }
}
