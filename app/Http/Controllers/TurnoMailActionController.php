<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class TurnoMailActionController extends Controller
{
    /**
     * Página pública firmada: muestra el resumen del turno y el form Confirmar/Cancelar.
     * Usa Route Model Binding: {turno} en la ruta.
     */
    public function show(Request $request, Turno $turno): View
    {
        // Prefijo opcional para preseleccionar la acción desde el mail (?accion=confirmar|cancelar)
        $accion = $request->query('accion');
        if ($accion && !in_array($accion, ['confirmar', 'cancelar'], true)) {
            $accion = null;
        }

        // Cargamos nombres mínimos para la vista
        $turno->loadMissing(['paciente:id,name', 'profesional:id,name']);

        // Firmamos también la acción POST para que el formulario pase el middleware 'signed'
        $ttl     = now()->addHours(config('turnos.mail_link_ttl_hours', 24));
        $postUrl = URL::temporarySignedRoute('turnos.mail.store', $ttl, [
            'turno' => $turno->getKey(),
        ]);

        return view('public.turno-mail-action', compact('turno', 'accion', 'postUrl'));
    }

    /**
     * Procesa Confirmar/Cancelar desde la vista pública firmada.
     * También usa Route Model Binding: {turno} en la ruta.
     */
    public function store(Request $request, Turno $turno): RedirectResponse
    {
        $data = $request->validate([
            'accion' => ['required', 'in:confirmar,cancelar'],
        ]);

        return DB::transaction(function () use ($turno, $data) {
            // 🔒 Evita condiciones de carrera (doble clic, reenviar, etc.)
            $turno = Turno::whereKey($turno->getKey())->lockForUpdate()->firstOrFail();

            // No permitir operar sobre turnos vencidos
            if (optional($turno->fin)->isPast()) {
                return back()->with('status', 'El turno ya pasó.');
            }

            // Si ya está cancelado, cortamos antes
            if (in_array($turno->estado, [
                Turno::ESTADO_CANCELADO,
                Turno::ESTADO_CANCELADO_TARDE,
            ], true)) {
                return back()->with('status', 'Este turno ya fue cancelado.');
            }


            // ⬇️ Endurecer por ventana de confirmación/cancelación
            if ($data['accion'] === 'confirmar' && ! $turno->puedeConfirmarAhora()) {
                return back()->with('status', 'No podés confirmar en este momento.');
            }
            if ($data['accion'] === 'cancelar' && ! $turno->puedeCancelarAhora()) {
                return back()->with('status', 'No podés cancelar en este momento.');
            }
            // ⬆️


            if ($data['accion'] === 'confirmar') {
                if ($turno->estado === Turno::ESTADO_CONFIRMADO) {
                    return back()->with('status', 'Este turno ya estaba confirmado.');
                }

                // Idempotente: solo si sigue pendiente
                $updated = Turno::where('id_turno', $turno->id_turno)
                    ->where('estado', Turno::ESTADO_PENDIENTE)
                    ->update([
                        'estado'          => Turno::ESTADO_CONFIRMADO,
                        'reminder_status' => 'confirmed',
                        'reminder_token'  => null,
                        'updated_at'      => now(),
                    ]);

                return back()->with(
                    'status',
                    $updated ? '¡Turno confirmado!' : 'No se pudo confirmar: el estado cambió en paralelo.'
                );
            }

            // cancelar (permitimos si estaba pendiente o confirmado)
            $inicio   = $turno->inicio;
            $minReq   = Turno::leadMinutes('cancel_min_minutes', 1440);
            $minsRest = $inicio ? now()->diffInMinutes($inicio, false) : -1;
            $tarde    = $minsRest < $minReq;

            $updated = Turno::where('id_turno', $turno->id_turno)
                ->whereIn('estado', [Turno::ESTADO_PENDIENTE, Turno::ESTADO_CONFIRMADO])
                ->update([
                    'estado'          => $tarde ? Turno::ESTADO_CANCELADO_TARDE : Turno::ESTADO_CANCELADO,
                    'reminder_status' => 'cancelled',
                    'reminder_token'  => null,
                    'updated_at'      => now(),
                ]);

            return back()->with(
                'status',
                $updated ? ($tarde ? 'Turno cancelado (tardío).' : 'Turno cancelado.') : 'Este turno ya fue procesado.'
            );
        });
    }
}
