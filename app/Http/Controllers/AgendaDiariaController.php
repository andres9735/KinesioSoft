<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use App\Mail\TurnoConfirmacionMail;

class AgendaDiariaController extends Controller
{
    /** Vista HTML con la previsualización D+1 */
    public function previewHtml(Request $request)
    {
        $hoy = Carbon::today();
        $fechaObjetivo = $hoy->copy()->addDay();

        $turnos = $this->turnosParaNotificar($fechaObjetivo);

        $payload = [
            'hoy'            => $hoy->toDateString(),
            'fecha_objetivo' => $fechaObjetivo->toDateString(),
            'total'          => $turnos->count(),
            'detalle'        => $turnos->map(function (Turno $t) {
                return [
                    'turno_id'       => $t->id_turno,
                    'paciente'       => $t->paciente?->name,
                    'email'          => $t->paciente?->email,
                    'profesional'    => $t->profesional?->name,
                    'fecha'          => optional($t->fecha)->toDateString(),
                    'hora_desde'     => substr((string) $t->hora_desde, 0, 5),
                    'hora_hasta'     => substr((string) $t->hora_hasta, 0, 5),
                    'estado'         => $t->estado,
                    'consultorio_id' => $t->id_consultorio,
                ];
            })->values(),
        ];

        return view('agenda_diaria.preview', $payload);
    }

    /** Previsualización JSON (útil para depurar) */
    public function preview(Request $request)
    {
        $hoy = Carbon::today();
        $fechaObjetivo = $hoy->copy()->addDay();
        $turnos = $this->turnosParaNotificar($fechaObjetivo);

        return response()->json([
            'hoy'            => $hoy->toDateString(),
            'fecha_objetivo' => $fechaObjetivo->toDateString(),
            'total'          => $turnos->count(),
            'detalle'        => $turnos->map(function (Turno $t) {
                return [
                    'turno_id'       => $t->id_turno,
                    'paciente'       => $t->paciente?->name,
                    'email'          => $t->paciente?->email,
                    'profesional'    => $t->profesional?->name,
                    'fecha'          => optional($t->fecha)->toDateString(),
                    'hora_desde'     => substr((string) $t->hora_desde, 0, 5),
                    'hora_hasta'     => substr((string) $t->hora_hasta, 0, 5),
                    'estado'         => $t->estado,
                    'consultorio_id' => $t->id_consultorio,
                ];
            })->values(),
            'mensaje' => 'Se informará que se enviará un correo de confirmación a estos pacientes para sus turnos del día indicado.',
        ]);
    }

    /** Ejecuta el módulo (simulado o real) */
    public function run(Request $request)
    {
        $simulate      = $request->boolean('simulate', true);
        $hoy           = Carbon::today();
        $fechaObjetivo = $hoy->copy()->addDay();

        $turnos = Turno::query()
            ->with(['paciente:id,name,email', 'profesional:id,name'])
            ->whereDate('fecha', $fechaObjetivo->toDateString())
            ->whereIn('estado', [Turno::ESTADO_PENDIENTE, Turno::ESTADO_CONFIRMADO])
            // no reenviamos si ya se marcó 'sent'
            ->where(fn($q) => $q->whereNull('reminder_status')->orWhere('reminder_status', '!=', 'sent'))
            ->orderBy('hora_desde')
            ->get();

        $enviados = 0;
        $errores  = 0;

        foreach ($turnos as $t) {
            $to = $t->paciente?->email;

            if (!$to) {
                $errores++;
                Log::warning('AgendaDiaria: turno sin email de paciente', ['turno' => $t->id_turno]);

                DB::table('turnos')->where('id_turno', $t->id_turno)->update([
                    'reminder_status'  => 'failed',
                    'reminder_sent_at' => now(),
                    'updated_at'       => now(),
                ]);
                continue;
            }

            if ($simulate) {
                // 🧪 SIMULADO: no envía, solo marca y loguea
                Log::info('SIMULAR envío de recordatorio D-1', [
                    'to'    => $to,
                    'turno' => $t->id_turno,
                    'pac'   => $t->paciente?->name,
                    'prof'  => $t->profesional?->name,
                ]);

                DB::table('turnos')->where('id_turno', $t->id_turno)->update([
                    'reminder_status'  => 'pending',
                    'reminder_sent_at' => now(),
                    'updated_at'       => now(),
                ]);

                $enviados++;
                continue;
            }

            // ✉️ REAL: encolamos el mailable (el worker lo envía y no bloquea la request)
            try {
                Mail::to($to)->queue(new TurnoConfirmacionMail($t));

                DB::table('turnos')->where('id_turno', $t->id_turno)->update([
                    'reminder_token'   => null,      // ya no usamos tokens manuales
                    'reminder_status'  => 'sent',    // evita reintentos desde el módulo
                    'reminder_sent_at' => now(),
                    'updated_at'       => now(),
                ]);

                $enviados++;
            } catch (\Throwable $e) {
                $errores++;

                DB::table('turnos')->where('id_turno', $t->id_turno)->update([
                    'reminder_status'  => 'failed',
                    'reminder_sent_at' => now(),
                    'updated_at'       => now(),
                ]);

                Log::error('Fallo envío recordatorio D-1', [
                    'turno_id' => $t->id_turno,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $modo = $simulate ? 'SIMULADO' : 'REAL';
        $msg  = $simulate
            ? 'Modo SIMULADO: no se enviaron correos; se registró en logs lo que se haría.'
            : 'Modo REAL: revisá tu Inbox de Mailtrap y la consola del worker.';

        return redirect()
            ->route('agenda-diaria.index')
            ->with('agenda_result', [
                'fecha_objetivo' => $fechaObjetivo->toDateString(),
                'simulate'       => $simulate,
                'total_turnos'   => $turnos->count(),
                'enviados'       => $enviados,
                'errores'        => $errores,
                'msg'            => $msg,
                'modo'           => $modo,
            ]);
    }

    /** Colección de turnos (D+1) a notificar */
    protected function turnosParaNotificar(Carbon $fechaObjetivo)
    {
        $d = $fechaObjetivo->toDateString();

        return Turno::query()
            ->with(['paciente:id,name,email', 'profesional:id,name'])
            ->whereDate('fecha', $d)
            ->whereIn('estado', [Turno::ESTADO_PENDIENTE, Turno::ESTADO_CONFIRMADO])
            ->where(fn($q) => $q->whereNull('reminder_status')->orWhere('reminder_status', '!=', 'sent'))
            ->orderBy('hora_desde')
            ->get();
    }
}
