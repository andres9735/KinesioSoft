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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Throwable;

class EnviarRecordatorioTurno implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Intentos máximos del job (si no pasás --tries en el worker) */
    public int $tries = 3;

    /** Backoff entre reintentos (en segundos) */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public int $turnoId,
        public string $email,
    ) {}

    public function handle(): void
    {
        $turno = Turno::with(['paciente', 'profesional'])->find($this->turnoId);

        // Si el turno ya no existe o no hay email, marcamos como failed y no reintentamos
        if (! $turno || empty($this->email)) {
            $this->marcarFailed('Turno o email vacío / no encontrado');
            return;
        }

        // 💡 Pequeño throttle para no saturar Mailtrap en DEV/TEST
        if (config('app.env') !== 'production') {
            // 2 segundos entre envío y envío para evitar el 550
            usleep(2_000_000);
        }

        try {
            // Ahora el envío se hace realmente acá (el mailable ya NO es ShouldQueue)
            Mail::to($this->email)->send(new TurnoConfirmacionMail($turno));

            // Marcamos como enviado OK
            DB::table('turnos')
                ->where('id_turno', $this->turnoId)
                ->update([
                    'reminder_status'  => 'sent',
                    'reminder_sent_at' => now(),
                    'updated_at'       => now(),
                ]);
        } catch (UnexpectedResponseException $e) {
            // Caso típico: 550 Too many emails per second (Mailtrap)
            Log::warning('Rate limit al enviar recordatorio de turno D-1', [
                'turno_id' => $this->turnoId,
                'email'    => $this->email,
                'code'     => $e->getCode(),
                'message'  => $e->getMessage(),
            ]);

            // Re-lanzamos para que Laravel maneje reintentos según $tries/$backoff
            throw $e;
        } catch (Throwable $e) {
            Log::error('Error inesperado al enviar recordatorio de turno D-1', [
                'turno_id' => $this->turnoId,
                'email'    => $this->email,
                'code'     => $e->getCode(),
                'message'  => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Marca el turno como failed en BD y loguea el motivo.
     */
    protected function marcarFailed(?string $reason = null): void
    {
        DB::table('turnos')
            ->where('id_turno', $this->turnoId)
            ->update([
                'reminder_status'  => 'failed',
                'reminder_sent_at' => now(),
                'updated_at'       => now(),
            ]);

        if ($reason) {
            Log::error('Error al enviar recordatorio de turno D-1', [
                'turno_id' => $this->turnoId,
                'email'    => $this->email,
                'error'    => $reason,
            ]);
        }
    }

    /**
     * Se ejecuta cuando el job se marca como failed después de agotar reintentos.
     */
    public function failed(Throwable $e): void
    {
        $this->marcarFailed($e->getMessage());
    }
}
