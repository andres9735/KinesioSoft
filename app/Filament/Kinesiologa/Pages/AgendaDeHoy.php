<?php

namespace App\Filament\Kinesiologa\Pages;

use App\Models\Turno;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use App\Filament\Kinesiologa\Pages\EvaluacionInicial;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AgendaDeHoy extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Turnos y Consultas';
    protected static ?int    $navigationSort  = 12;

    protected static ?string $title           = 'Agenda por día';
    protected static ?string $navigationLabel = 'Agenda por día';

    protected static string $view = 'filament.kinesiologa.pages.agenda-de-hoy';

    /** Fecha seleccionada (Y-m-d) */
    public string $fecha;

    /** Mostrar sólo pendientes (solo aplica en vista "programados") */
    public bool $soloPendientes = false;

    /**
     * Vista:
     * - programados: pendientes + confirmados
     * - atendidos: solo atendidos
     * - no_asistio: solo no asistió
     * - todos: todos los estados del día EXCEPTO cancelados
     */
    public string $vista = 'programados';

    /** Nombre de la profesional (logueada) */
    public string $profesionalNombre = '';

    /** Filas listas para la vista */
    public array $rows = [];

    public int $total = 0;

    public function mount(): void
    {
        $this->fecha             = request()->query('fecha', now()->toDateString());
        $this->profesionalNombre = (string) (Auth::user()->name ?? '—');

        $this->refreshRows();

        $this->dispatch('agenda-updated', fecha: $this->fecha, soloPendientes: $this->soloPendientes, vista: $this->vista);
    }

    public function updatedFecha(): void
    {
        $this->refreshRows();
        $this->dispatch('agenda-updated', fecha: $this->fecha, soloPendientes: $this->soloPendientes, vista: $this->vista);
    }

    public function updatedSoloPendientes(): void
    {
        $this->refreshRows();
        $this->dispatch('agenda-updated', fecha: $this->fecha, soloPendientes: $this->soloPendientes, vista: $this->vista);
    }

    public function updatedVista(): void
    {
        // Si cambia la vista, tiene sentido resetear el checkbox si no estamos en Programados
        if ($this->vista !== 'programados') {
            $this->soloPendientes = false;
        }

        $this->refreshRows();
        $this->dispatch('agenda-updated', fecha: $this->fecha, soloPendientes: $this->soloPendientes, vista: $this->vista);
    }

    public function setHoy(): void
    {
        $this->fecha = now()->toDateString();
        $this->refreshRows();
        $this->dispatch('agenda-updated', fecha: $this->fecha, soloPendientes: $this->soloPendientes, vista: $this->vista);
    }

    public function iniciarConsulta(int $turnoId)
    {
        return redirect(EvaluacionInicial::getUrl(['turno' => $turnoId]));
    }

    /**
     * ✅ Marcar "No asistió"
     * Solo si:
     * - el turno pertenece a la kinesióloga logueada
     * - está pendiente/confirmado
     * - ya pasó hora_hasta (turno finalizado)
     */
    public function marcarNoAsistio(int $turnoId): void
    {
        $userId = (int) Auth::id();

        $turno = Turno::query()
            ->deProfesional($userId)
            ->where('id_turno', $turnoId)
            ->firstOrFail();

        // Solo desde estados activos
        if (! in_array($turno->estado, [Turno::ESTADO_PENDIENTE, Turno::ESTADO_CONFIRMADO], true)) {
            Notification::make()
                ->title('No se puede marcar "No asistió" en este estado')
                ->warning()
                ->send();
            return;
        }

        // Validar que el turno ya terminó
        $fecha = (string) Carbon::parse($turno->fecha)->toDateString();
        $fin   = Carbon::parse($fecha . ' ' . $turno->getRawOriginal('hora_hasta'));

        if (now()->lt($fin)) {
            Notification::make()
                ->title('Todavía no terminó el turno')
                ->body('Solo podés marcar "No asistió" cuando ya pasó la hora de fin.')
                ->warning()
                ->send();
            return;
        }

        $turno->estado = Turno::ESTADO_NO_ASISTIO;
        $turno->save();

        Notification::make()
            ->title('Turno marcado como "No asistió"')
            ->success()
            ->send();

        $this->refreshRows();
        $this->dispatch('agenda-updated', fecha: $this->fecha, soloPendientes: $this->soloPendientes, vista: $this->vista);
    }

    private function refreshRows(): void
    {
        try {
            $this->fecha = Carbon::parse($this->fecha)->toDateString();
        } catch (\Throwable $e) {
            $this->fecha = now()->toDateString();
        }

        $userId = (int) Auth::id();

        $turnos = Turno::query()
            ->deProfesional($userId)
            ->delDia($this->fecha)

            // ✅ Vista: programados / atendidos / no_asistio / todos (excluye cancelados)
            ->when(
                $this->vista === 'programados',
                fn($q) => $q->whereIn('estado', [Turno::ESTADO_PENDIENTE, Turno::ESTADO_CONFIRMADO])
            )
            ->when(
                $this->vista === 'atendidos',
                fn($q) => $q->where('estado', Turno::ESTADO_ATENDIDO)
            )
            ->when(
                $this->vista === 'no_asistio',
                fn($q) => $q->where('estado', Turno::ESTADO_NO_ASISTIO)
            )
            ->when(
                $this->vista === 'todos',
                fn($q) => $q->whereNotIn('estado', [Turno::ESTADO_CANCELADO, Turno::ESTADO_CANCELADO_TARDE])
            )

            // Solo pendientes: SOLO tiene sentido en "programados"
            ->when(
                $this->soloPendientes && $this->vista === 'programados',
                fn($q) => $q->where('estado', Turno::ESTADO_PENDIENTE)
            )

            // 👇 importante: traer consulta para saber si existe (sin query extra)
            ->with([
                'paciente:id,name',
                'consultorio:id_consultorio,nombre',
                'consulta:id_consulta,turno_id',
            ])
            ->orderBy('hora_desde')
            ->get();

        $this->rows = $turnos->map(function (Turno $t) {
            // Para habilitar el botón: solo pendiente/confirmado y ya terminó
            $fecha = (string) Carbon::parse($t->fecha)->toDateString();
            $fin   = Carbon::parse($fecha . ' ' . $t->getRawOriginal('hora_hasta'));

            $puedeNoAsistio = in_array($t->estado, [Turno::ESTADO_PENDIENTE, Turno::ESTADO_CONFIRMADO], true)
                && now()->gte($fin);

            return [
                'id'                      => $t->id_turno,
                'paciente_id'             => $t->paciente_id,
                'paciente'                => $t->paciente?->name ?? '—',
                'hora'                    => substr((string) $t->hora_desde, 0, 5) . '–' . substr((string) $t->hora_hasta, 0, 5),
                'consultorio'             => $t->consultorio?->nombre ?? '—',
                'estado'                  => $t->estado,
                'estadoColor'             => Turno::estadoColor($t->estado),
                'reminder_status'         => $t->reminder_status,
                'es_adelanto_automatico'  => (bool) $t->es_adelanto_automatico,

                'tiene_consulta'          => (bool) $t->consulta,
                'consulta_id'             => $t->consulta?->id_consulta,

                // 👇 NUEVO
                'puede_marcar_no_asistio' => $puedeNoAsistio,
            ];
        })->values()->all();

        $this->total = count($this->rows);
    }
}
