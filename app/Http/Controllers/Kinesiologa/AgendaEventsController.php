<?php

namespace App\Http\Controllers\Kinesiologa;

use App\Http\Controllers\Controller;
use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AgendaEventsController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if (! $user || ! $user->hasRole('Kinesiologa')) {
            abort(403);
        }

        // Fecha YYYY-mm-dd que viene del Front
        $date = $request->query('date', now()->toDateString());

        try {
            $date = Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            $date = now()->toDateString();
        }

        $soloPendientes = $request->boolean('solo_pendientes', false);

        // Misma lógica que en AgendaDeHoy::refreshRows()
        $query = Turno::query()
            ->deProfesional($user->id)
            ->delDia($date)
            ->whereIn('estado', [Turno::ESTADO_PENDIENTE, Turno::ESTADO_CONFIRMADO]);

        if ($soloPendientes) {
            $query->where('estado', Turno::ESTADO_PENDIENTE);
        }

        $turnos = $query
            ->with(['paciente:id,name', 'consultorio:id_consultorio,nombre'])
            ->orderBy('hora_desde')
            ->get();

        $events = $turnos->map(function (Turno $t) use ($date) {
            // Aseguramos formato ISO completo: 2025-12-09T08:00:00
            $start = Carbon::parse($date . ' ' . $t->hora_desde)->toIso8601String();
            $end   = Carbon::parse($date . ' ' . $t->hora_hasta)->toIso8601String();

            $paciente = $t->paciente?->name ?? 'Paciente';
            $estado   = $t->estado;

            return [
                'id'    => $t->id_turno,
                'title' => "{$paciente} · {$estado}",
                'start' => $start,
                'end'   => $end,

                // Info extra por si luego querés mostrarla en tooltips, etc.
                'extendedProps' => [
                    'paciente'               => $paciente,
                    'estado'                 => $estado,
                    'estadoColor'            => Turno::estadoColor($t->estado),
                    'consultorio'            => $t->consultorio?->nombre ?? null,
                    'es_adelanto_automatico' => (bool) $t->es_adelanto_automatico,
                ],
            ];
        })->values();

        return response()->json($events);
    }
}
