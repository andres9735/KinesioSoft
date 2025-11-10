<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TurnoMailActionController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $turnoId = (int) $request->query('turno');
        $accion  = (string) $request->query('accion'); // confirmar|cancelar

        $turno = Turno::with(['paciente:id,name', 'profesional:id,name'])->find($turnoId);
        if (!$turno || !in_array($accion, ['confirmar', 'cancelar'], true)) {
            return redirect()->away('/')->with('status', 'Enlace inválido.');
        }

        return view('public.turno-mail-action', compact('turno', 'accion'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'turno_id' => ['required', 'integer', 'exists:turnos,id_turno'],
            'accion'   => ['required', 'in:confirmar,cancelar'],
        ]);

        /** @var Turno $turno */
        $turno = Turno::findOrFail($data['turno_id']);

        // si el turno ya empezó o terminó, no permitir
        $fin = $turno->fin;
        if ($fin && now()->gte($fin)) {
            return back()->with('status', 'El turno ya pasó.');
        }

        if ($data['accion'] === 'confirmar') {
            if ($turno->esPendiente()) {
                $turno->update([
                    'estado'          => Turno::ESTADO_CONFIRMADO,
                    'reminder_status' => 'confirmed',
                    'reminder_token'  => null,
                ]);
                return back()->with('status', '¡Turno confirmado!');
            }
            return back()->with('status', 'Este turno no está pendiente.');
        }

        // cancelar (con “tardía” según tus reglas)
        $inicio   = $turno->inicio;
        $minReq   = Turno::leadMinutes('cancel_min_minutes', 1440);
        $minsRest = $inicio ? now()->diffInMinutes($inicio, false) : -1;
        $tarde    = $minsRest < $minReq;

        $turno->update([
            'estado'          => $tarde ? Turno::ESTADO_CANCELADO_TARDE : Turno::ESTADO_CANCELADO,
            'reminder_status' => 'cancelled',
            'reminder_token'  => null,
        ]);

        return back()->with('status', $tarde ? 'Turno cancelado (tardío).' : 'Turno cancelado.');
    }
}
