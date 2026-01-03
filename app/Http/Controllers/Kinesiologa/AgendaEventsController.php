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

        $date = $request->query('date', now()->toDateString());

        try {
            $date = Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            $date = now()->toDateString();
        }

        $soloPendientes = $request->boolean('solo_pendientes', false);

        $vista = (string) $request->query('vista', 'programados');
        if (! in_array($vista, ['programados', 'atendidos', 'todos'], true)) {
            $vista = 'programados';
        }

        $query = Turno::query()
            ->deProfesional($user->id)
            ->delDia($date);

        // Vista: programados / atendidos / no_asistio / todos
        if ($vista === 'programados') {
            $query->whereIn('estado', [Turno::ESTADO_PENDIENTE, Turno::ESTADO_CONFIRMADO]);

            if ($soloPendientes) {
                $query->where('estado', Turno::ESTADO_PENDIENTE);
            }
        } elseif ($vista === 'atendidos') {
            $query->where('estado', Turno::ESTADO_ATENDIDO);
            // ignoramos $soloPendientes
        } elseif ($vista === 'no_asistio') {
            $query->where('estado', Turno::ESTADO_NO_ASISTIO);
            // ignoramos $soloPendientes
        } else { // 'todos'
            $query->whereNotIn('estado', [Turno::ESTADO_CANCELADO, Turno::ESTADO_CANCELADO_TARDE]);
        }

        $turnos = $query
            ->with(['paciente:id,name', 'consultorio:id_consultorio,nombre'])
            ->orderBy('hora_desde')
            ->get();

        $events = $turnos->map(function (Turno $t) use ($date) {
            $start = Carbon::parse($date . ' ' . $t->hora_desde)->toIso8601String();
            $end   = Carbon::parse($date . ' ' . $t->hora_hasta)->toIso8601String();

            $paciente = $t->paciente?->name ?? 'Paciente';
            $estado   = $t->estado;

            return [
                'id'    => $t->id_turno,
                'title' => "{$paciente} · {$estado}",
                'start' => $start,
                'end'   => $end,

                // ✅ NUEVO: clases CSS para colorear según estado
                'classNames' => [
                    'kine-event',
                    'estado-' . str_replace('_', '-', $estado), // no_asistio -> estado-no-asistio
                ],

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
