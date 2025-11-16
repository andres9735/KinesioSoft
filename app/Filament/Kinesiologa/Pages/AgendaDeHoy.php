<?php

namespace App\Filament\Kinesiologa\Pages;

use App\Models\Turno;
use Filament\Pages\Page;
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

    /** Mostrar sólo pendientes */
    public bool $soloPendientes = false;

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
    }

    /** Cambios de fecha / filtro */
    public function updatedFecha(): void
    {
        $this->refreshRows();
    }
    public function updatedSoloPendientes(): void
    {
        $this->refreshRows();
    }

    /** Botones rápidos */
    public function setHoy(): void
    {
        $this->fecha = now()->toDateString();
        $this->refreshRows();
    }

    private function refreshRows(): void
    {
        try {
            $f = Carbon::parse($this->fecha)->toDateString();
        } catch (\Throwable $e) {
            $f = now()->toDateString();
        }
        $this->fecha = $f;

        $userId = (int) Auth::id();

        $turnos = Turno::query()
            ->deProfesional($userId)
            ->delDia($this->fecha)
            ->when($this->soloPendientes, fn($q) => $q->estado(Turno::ESTADO_PENDIENTE))
            ->with(['paciente:id,name', 'consultorio:id_consultorio,nombre'])
            ->orderBy('hora_desde')
            ->get();

        $this->rows = $turnos->map(function (Turno $t) {
            return [
                'id'               => $t->id_turno,
                'paciente_id'      => $t->paciente_id,
                'paciente'         => $t->paciente?->name ?? '—',
                'hora'             => substr((string)$t->hora_desde, 0, 5) . '–' . substr((string)$t->hora_hasta, 0, 5),
                'consultorio'      => $t->consultorio?->nombre ?? '—',
                'estado'           => $t->estado,
                'estadoColor'      => Turno::estadoColor($t->estado),
                'reminder_status'  => $t->reminder_status, // para mostrar si confirmó por mail
            ];
        })->values()->all();

        $this->total = count($this->rows);
    }
}
