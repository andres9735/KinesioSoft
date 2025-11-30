<?php

namespace App\Jobs;

use App\Mail\TurnoConfirmacionMail;
use App\Models\Turno;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EnviarRecordatorioTurno implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 3;
    public $backoff = [30, 60, 120];

    public function __construct(public int $turnoId, public string $email) {}

    public function handle(): void
    {
        $t = Turno::with(['paciente', 'profesional'])->find($this->turnoId);

        // Si el turno ya no existe o no hay email, marcamos como failed
        if (! $t || empty($this->email)) {
            DB::table('turnos')
                ->where('id_turno', $this->turnoId)
                ->update([
                    'reminder_status' => 'failed',
                    'updated_at'      => now(),
                ]);

            return;
        }

        // 🔸 Pequeña pausa para no saturar Mailtrap en DEV/TEST
        // En producción no queremos dormir el worker.
        if (config('app.env') !== 'production') {
            // 0.5 segundos (podés subirlo a 1 segundo si vuelve a tirar 550)
            usleep(500_000);
        }

        // Envío real del mail
        Mail::to($this->email)->send(new TurnoConfirmacionMail($t));

        // Marcamos como enviado OK
        DB::table('turnos')
            ->where('id_turno', $this->turnoId)
            ->update([
                'reminder_status'  => 'sent',
                'reminder_sent_at' => now(),
                'updated_at'       => now(),
            ]);
    }

    public function failed(\Throwable $e): void
    {
        DB::table('turnos')
            ->where('id_turno', $this->turnoId)
            ->update([
                'reminder_status' => 'failed',
                'updated_at'      => now(),
            ]);
    }
}
