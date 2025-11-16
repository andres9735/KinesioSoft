<?php

namespace App\Filament\Kinesiologa\Pages;

use App\Mail\TurnoConfirmacionMail;
use App\Models\Turno;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RecordatoriosDeManiana extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-bell-alert';
    protected static ?string $navigationGroup = 'Turnos y Consultas';
    protected static ?int    $navigationSort  = 11;

    protected static ?string $title           = 'Recordatorios de mañana';
    protected static ?string $navigationLabel = 'Recordatorios de mañana';

    protected static string $view = 'filament.kinesiologa.pages.recordatorios-de-maniana';

    /** Fecha objetivo (Y-m-d) y modo simulado */
    public string $fecha;
    public bool $simulate = true;

    /** Datos para la tabla */
    public array $rows = [];
    public int   $total = 0;

    public function mount(): void
    {
        // Por defecto, mañana
        $this->fecha = request('fecha', now()->addDay()->toDateString());
        $this->rows  = [];
        $this->total = 0;
    }

    /** --- Helpers internos --- */

    /**
     * Devuelve la query base de turnos a notificar (pendientes del día seleccionado).
     */
    protected function baseQuery()
    {
        $f = Carbon::parse($this->fecha)->toDateString();
        $userId = Auth::id();

        return Turno::query()
            ->deProfesional($userId)
            ->enFecha($f)
            ->where('estado', Turno::ESTADO_PENDIENTE)
            ->with(['paciente:id,name,email', 'profesional:id,name', 'consultorio:id_consultorio,nombre'])
            ->orderBy('hora_desde');
    }

    /**
     * Refresca las filas de la tabla (se usa en preview y post-envío).
     */
    protected function refreshRows(): void
    {
        $turnos = $this->baseQuery()->get();

        $this->rows = $turnos->map(function (Turno $t) {
            return [
                'id'          => $t->id_turno,
                'paciente'    => $t->paciente?->name ?? '—',
                'email'       => $t->paciente?->email ?? '—',
                'profesional' => $t->profesional?->name ?? '—',
                'fecha'       => $t->fecha?->format('Y-m-d'),
                'hora'        => substr((string) $t->hora_desde, 0, 5) . '–' . substr((string) $t->hora_hasta, 0, 5),
                'estado'      => $t->estado,
                'consultorio' => $t->consultorio?->nombre ?? '—',
            ];
        })->values()->all();

        $this->total = count($this->rows);
    }

    /** --- Métodos públicos llamados desde el Blade --- */

    /** Botón: Previsualizar */
    public function preview(): void
    {
        // Normalizo fecha por las dudas
        try {
            $this->fecha = Carbon::parse($this->fecha)->toDateString();
        } catch (\Throwable) {
            $this->fecha = now()->addDay()->toDateString();
        }

        $this->refreshRows();

        Notification::make()
            ->title('Previsualización lista')
            ->body("Encontrados {$this->total} turno(s) para {$this->fecha}.")
            ->success()
            ->send();
    }

    /** Botón: Enviar ahora (real o simulado) */
    public function run(): void
    {
        // Re-calculo sobre la fecha actual
        $turnos = $this->baseQuery()->get();

        $enviados = 0;
        $errores  = 0;

        foreach ($turnos as $t) {
            $to = $t->paciente?->email;
            if (! $to) {
                continue;
            }

            try {
                if ($this->simulate) {
                    // Solo marcamos como "simulated"
                    $t->forceFill([
                        'reminder_status' => 'simulated',
                        'reminder_sent_at' => now(),
                        'reminder_token'  => null,
                    ])->save();
                    $enviados++;
                } else {
                    // Envío real: encolamos el mailable
                    Mail::to($to)->queue(new TurnoConfirmacionMail($t));

                    $t->forceFill([
                        'reminder_status' => 'sent',
                        'reminder_sent_at' => now(),
                        'reminder_token'  => null,
                    ])->save();
                    $enviados++;
                }
            } catch (\Throwable $e) {
                $errores++;
                Log::error('Fallo envío recordatorio', [
                    'turno_id' => $t->id_turno,
                    'error'    => $e->getMessage(),
                ]);
                $t->forceFill(['reminder_status' => 'failed'])->save();
            }
        }

        // Actualizo vista
        $this->refreshRows();

        Notification::make()
            ->title($this->simulate ? 'Simulación completada' : 'Envíos encolados')
            ->body("Procesados: {$enviados} • Errores: {$errores}")
            ->success()
            ->send();
    }
}
