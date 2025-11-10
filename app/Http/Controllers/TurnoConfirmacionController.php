<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TurnoConfirmacionController extends Controller
{
    /**
     * Muestra el formulario público (accesible con el token).
     * GET /r/{token}
     */
    public function show(string $token)
    {
        /** @var Turno|null $turno */
        $turno = Turno::with(['paciente:id,name,email', 'profesional:id,name'])
            ->where('reminder_token', $token)
            ->first();

        if (! $turno) {
            // Mostramos la misma blade con flag de enlace inválido
            return response()->view('recordatorio.form', [
                'invalid'        => true,
                'turno'          => null,
                'puedeConfirmar' => false,
                'puedeCancelar'  => false,
                'limiteConfirm'  => null,
                'limiteCancel'   => null,
            ], 404);
        }

        // Flags de negocio
        $puedeConfirmar = $turno->esPendiente() && $turno->puedeConfirmarAhora();

        // Para cancelar: permitimos mientras sea futuro
        $ahora  = now();
        $inicio = $turno->inicio;
        $puedeCancelar = $inicio && $ahora->lt($inicio);

        return view('recordatorio.form', [
            'invalid'        => false,
            'turno'          => $turno,
            'puedeConfirmar' => $puedeConfirmar,
            'puedeCancelar'  => $puedeCancelar,
            'limiteConfirm'  => $turno->limiteConfirmacion(),
            'limiteCancel'   => $turno->limiteCancelacion(),
        ]);
    }

    /**
     * POST /r/{token}/confirmar
     */
    public function confirmar(Request $request, string $token)
    {
        /** @var Turno|null $turno */
        $turno = Turno::where('reminder_token', $token)->first();

        if (! $turno) {
            return response()->view('recordatorio.form', ['invalid' => true], 404);
        }

        if (! $turno->esPendiente()) {
            // Idempotencia: ya no está pendiente, solo informamos
            return back()->with('info', 'Este turno ya no está pendiente (estado: ' . $turno->estado . ').');
        }

        if (! $turno->puedeConfirmarAhora()) {
            return back()->with('error', 'Ya no es posible confirmar este turno según la política de tiempos.');
        }

        // ✅ Confirmar e invalidar token de recordatorio
        $turno->update([
            'estado'          => Turno::ESTADO_CONFIRMADO,
            'reminder_status' => 'confirmed',
            'reminder_token'  => null,   // invalidamos el enlace
            // 'reminder_sent_at' se conserva como traza del envío
        ]);

        return back()->with('success', '¡Turno confirmado correctamente!');
    }

    /**
     * POST /r/{token}/cancelar
     *
     * Política:
     * - Si el turno ya pasó o está en curso → no se cancela.
     * - Si es futuro:
     *      - fuera del deadline → cancelado
     *      - dentro del deadline → cancelado_tarde
     */
    public function cancelar(Request $request, string $token)
    {
        /** @var Turno|null $turno */
        $turno = Turno::where('reminder_token', $token)->first();

        if (! $turno) {
            return response()->view('recordatorio.form', ['invalid' => true], 404);
        }

        $inicio = $turno->inicio;
        if (! $inicio || now()->gte($inicio)) {
            return back()->with('error', 'No es posible cancelar un turno pasado o que está comenzando.');
        }

        // Ventana mínima para cancelar “en tiempo”
        $mins   = now()->diffInMinutes($inicio, false);
        $minReq = Turno::leadMinutes('cancel_min_minutes', 1440);

        $nuevoEstado = $mins >= $minReq
            ? Turno::ESTADO_CANCELADO
            : Turno::ESTADO_CANCELADO_TARDE;

        // ✅ Cancelar e invalidar token de recordatorio
        $turno->update([
            'estado'          => $nuevoEstado,
            'reminder_status' => 'cancelled',
            'reminder_token'  => null,  // invalidamos el enlace
        ]);

        return back()->with(
            'success',
            $nuevoEstado === Turno::ESTADO_CANCELADO
                ? 'Turno cancelado correctamente.'
                : 'Turno cancelado (dentro de la ventana tardía).'
        );
    }
}
