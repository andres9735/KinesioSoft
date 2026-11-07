<?php

namespace App\Services\Turnos;

use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Models\IntentoRecordatorioTurno;
use App\Models\RecordatorioTurno;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/** Despacho idempotente de una posición de intento autorizada por el estado durable. */
final class DespacharRecordatorioTurnoService
{
    public function __construct(
        private PrepararContenidoRecordatorioTurnoService $contenido,
        private RegistrarRecordatorioTurnoService $registros,
        private SobreEntregaEnlaceService $sobres,
        private TransportarRecordatorioTurnoService $transporte,
        private TransaccionTurnoService $transacciones,
        private GestionarReintentosRecordatorioTurnoService $reintentos,
    ) {}

    public function despachar(int $id, string $identidad, int $numero = 1): ?Resultado
    {
        try {
            if (! app()->runningInConsole() || request()->route() !== null || Auth::check()
                || DB::transactionLevel() !== 0 || ! Str::isUuid($identidad) || $numero < 1 || $numero > 4) {
                throw new RuntimeException;
            }
            $anterior = IntentoRecordatorioTurno::query()->useWritePdo()->where('identidad', $identidad)->first();
            if ($anterior !== null) {
                if ((int) $anterior->recordatorio_id !== $id || $anterior->numero !== $numero) {
                    throw new RuntimeException;
                }

                // Replay de un trabajo: consultar no autoriza a transportar otra vez.
                return $anterior->resultado;
            }
            if ($numero === 1 && ! IntentoRecordatorioTurno::query()->where('recordatorio_id', $id)->exists()) {
                $this->contenido->preparar($id);
            }
            $intento = $this->reintentos->reclamar($id, $identidad, $numero);
            if ($intento === null) {
                return null;
            }
            if (! $intento->wasRecentlyCreated) {
                return $intento->resultado;
            }
            // Un reintento nunca vuelve a emitir ni sustituye referencias existentes.
            $referencias = ['sobre_id' => RecordatorioTurno::query()->useWritePdo()->findOrFail($id)->sobre_id];
            if ($referencias['sobre_id'] === null) {
                $resultado = $this->transporte->enviar(fn () => $this->contenido->construir($id));
                $this->registros->registrarResultado((int) $intento->id, $resultado);
                $this->reintentos->revisar($id);

                return $resultado;
            }

            $resultado = $this->sobres->conMaterialParaDespacho($referencias['sobre_id'],
                function (#[SensitiveParameter] EnlaceAsistenciaEmitido $material, #[SensitiveParameter] string $reclamo) use ($id, $intento, $referencias): Resultado {
                    $resultado = $this->transporte->enviar(fn () => $this->contenido->construir($id, $material, $reclamo));
                    $registro = RecordatorioTurno::query()->useWritePdo()->findOrFail($id, ['id', 'turno_id']);
                    // Resultado y cierre del sobre en una transacción, con el prefijo de locks canónico.
                    $this->transacciones->ejecutar((int) $registro->turno_id, function () use ($referencias, $reclamo, $intento, $resultado): void {
                        $this->sobres->registrarResultado($referencias['sobre_id'], $reclamo, match ($resultado) {
                            Resultado::ACEPTADO => 'ACEPTADO',
                            Resultado::FALLO_RECUPERABLE => 'FALLO_CONFIRMADO',
                            Resultado::FALLO_DEFINITIVO => 'FALLO_DEFINITIVO',
                            default => 'INCIERTO',
                        });
                        $this->registros->registrarResultado((int) $intento->id, $resultado);
                        if ($resultado === Resultado::FALLO_RECUPERABLE) {
                            $this->sobres->limitarVigencia($referencias['sobre_id'], $intento->fresh()->resuelto_at->addHour());
                        }
                    });

                    return $resultado;
                });
            $this->reintentos->revisar($id);

            return $resultado;
        } catch (Throwable) {
            try {
                $this->reintentos->revisar($id);
            } catch (Throwable) {
                // Si la DB no está disponible, el reclamo durable sigue bloqueando duplicados.
            }
            // Los reclamos/intentos ya consolidados permanecen inciertos ante interrupción.
            // No conservar previous: SQL, callbacks y SMTP pueden contener secretos.
            throw new RuntimeException('No pudo completarse el despacho del recordatorio.');
        }
    }
}
