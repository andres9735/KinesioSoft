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
     * Página pública firmada: muestra el resumen del turno y el formulario
     * para Confirmar / Cancelar. Usa Route Model Binding: {turno}.
     *
     * Ruta esperada (con middleware 'signed'):
     *   GET  /turnos/mail-action/{turno}   -> name: turnos.mail.show
     */
    public function show(Request $request, Turno $turno): View
    {
        // (Opcional) Preselección de acción desde el mail (?accion=confirmar|cancelar)
        $accion = $request->query('accion');
        if ($accion && ! in_array($accion, ['confirmar', 'cancelar'], true)) {
            $accion = null;
        }

        // Cargar nombres mínimos para la vista
        $turno->loadMissing(['paciente:id,name', 'profesional:id,name']);

        // URL firmada para el POST del formulario (respeta el TTL de config/turnos.php)
        $ttlHours = (int) config('turnos.mail_link_ttl_hours', 36);
        $postUrl  = URL::temporarySignedRoute('turnos.mail.store', now()->addHours($ttlHours), [
            'turno' => $turno->getKey(),
        ]);

        return view('public.turno-mail-action', compact('turno', 'accion', 'postUrl'));
    }

    /**
     * Procesa Confirmar / Cancelar desde la página pública firmada.
     *
     * Ruta esperada (con middleware 'signed'):
     *   POST /turnos/mail-action/{turno}   -> name: turnos.mail.store
     */
    public function store(Request $request, Turno $turno): RedirectResponse
    {
        $data = $request->validate([
            'accion' => ['required', 'in:confirmar,cancelar'],
        ]);

        return DB::transaction(function () use ($turno, $data) {
            // Evita condiciones de carrera (doble clic, reenviar, etc.)
            /** @var Turno $turno */
            $turno = Turno::whereKey($turno->getKey())->lockForUpdate()->firstOrFail();

            // No permitir operar sobre turnos ya finalizados
            if ($turno->fin && now()->gte($turno->fin)) {
                return back()->with('status', 'El turno ya pasó.');
            }

            // Si ya está cancelado, cortamos antes
            if (in_array($turno->estado, [
                Turno::ESTADO_CANCELADO,
                Turno::ESTADO_CANCELADO_TARDE,
            ], true)) {
                return back()->with('status', 'Este turno ya fue cancelado.');
            }

            // ---------- Confirmar ----------
            if ($data['accion'] === 'confirmar') {
                // Respetar ventana de confirmación (UI + regla de negocio)
                if (! $turno->puedeConfirmarAhora()) {
                    return back()->with('status', 'No podés confirmar en este momento.');
                }

                if ($turno->estado === Turno::ESTADO_CONFIRMADO) {
                    return back()->with('status', 'Este turno ya estaba confirmado.');
                }

                // Idempotente: solo si seguía "pendiente"
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

            // ---------- Cancelar (siempre permitido; clasifica "tarde" según ventana) ----------
            $inicio   = $turno->inicio;
            $minReq   = Turno::leadMinutes('cancel_min_minutes', 1440); // p.ej. 24h
            $minsRest = $inicio ? now()->diffInMinutes($inicio, false) : -1; // negativo si ya pasó
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
                $updated ? ($tarde ? 'Turno cancelado (tardío).' : 'Turno cancelado.')
                    : 'Este turno ya fue procesado.'
            );
        });
    }
}
