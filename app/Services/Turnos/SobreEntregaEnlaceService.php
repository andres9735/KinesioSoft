<?php

namespace App\Services\Turnos;

use App\Exceptions\Turnos\EntregaEnlaceNoDisponibleException;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\SobreEntregaEnlace;
use App\Models\Turno;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;
use Throwable;

/** Infraestructura R1–R10. Sin transporte, Jobs, Scheduler ni política de reintentos. */
final class SobreEntregaEnlaceService
{
    public function __construct(private TransaccionTurnoService $transacciones, private RelojAplicacion $reloj, private AuditoriaService $auditoria) {}

    /** Exclusivo de la creación dentro de la transacción de emisión, nunca un backfill. */
    public function preparar(EnlaceAsistenciaTurno $enlace, #[SensitiveParameter] string $url): int
    {
        if (! $enlace->wasRecentlyCreated || DB::transactionLevel() < 1) {
            throw new EntregaEnlaceNoDisponibleException('preparacion_no_autorizada');
        }
        try {
            $cifrado = Crypt::encryptString(json_encode([
                'uso' => 'uc28-entrega-v1', 'enlace' => (int) $enlace->id,
                'ciclo' => $enlace->ciclo_emision, 'revision' => $enlace->version_programacion,
                'url' => $url,
            ], JSON_THROW_ON_ERROR));
        } catch (Throwable) {
            // No adjuntar la excepción original: podría contener material de entrega.
            throw new EntregaEnlaceNoDisponibleException('cifrado_no_disponible');
        }
        try {
            $sobre = new SobreEntregaEnlace;
            $sobre->forceFill(['enlace_id' => $enlace->id, 'material_cifrado' => $cifrado,
                'estado' => 'DISPONIBLE', 'resultado' => 'SIN_DESPACHO', 'reclamo_hash' => null,
                'preparado_at' => $this->reloj->ahora(), 'actualizado_at' => $this->reloj->ahora(),
                'vence_at' => $enlace->getRawOriginal('vence_at'), 'cerrado_at' => null])->saveOrFail();
        } catch (Throwable) {
            throw new EntregaEnlaceNoDisponibleException('persistencia_no_disponible');
        }

        return (int) $sobre->id;
    }

    /**
     * Consumidor confiable CLI/worker, sin solicitud HTTP ni sesión del portal.
     * El callback recibe material transitorio y el ID técnico del reclamo FUERA
     * de cualquier transacción. Una caída queda INCIERTA, sin reenvío automático.
     * APP_PREVIOUS_KEYS conserva las claves anteriores hasta cerrar/recifrar los
     * sobres autorizados; cada recuperación recifra con la clave activa.
     */
    public function conMaterialParaDespacho(int $sobreId, #[SensitiveParameter] Closure $consumidor): mixed
    {
        $this->autorizarProceso();
        if (DB::transactionLevel() !== 0) {
            throw new EntregaEnlaceNoDisponibleException('transaccion_externa');
        }
        try {
            $resultado = $this->bajoLocks($sobreId, function (SobreEntregaEnlace $sobre, EnlaceAsistenciaTurno $enlace, Turno $turno, CarbonImmutable $ahora): array {
                if ($sobre->estado === 'CERRADO') {
                    return ['error' => 'cerrado'];
                }
                if (! $ahora->lt($sobre->vence_at)) {
                    $this->cerrar($sobre, 'VENCIDO', $ahora);

                    return ['error' => 'vencido'];
                }
                if (! $enlace->vigentePara($turno, $ahora)) {
                    $this->cerrar($sobre, 'INVALIDADO', $ahora);

                    return ['error' => 'enlace_invalido'];
                }
                if ($sobre->estado !== 'DISPONIBLE') {
                    return ['error' => 'resultado_incierto'];
                }
                try {
                    $datos = json_decode(Crypt::decryptString($sobre->material_cifrado), true, 16, JSON_THROW_ON_ERROR);
                    $url = $datos['url'] ?? null;
                    $partes = is_string($url) ? parse_url($url) : false;
                    $query = [];
                    if (is_array($partes)) {
                        parse_str($partes['query'] ?? '', $query);
                    }
                    if (($datos['uso'] ?? null) !== 'uc28-entrega-v1' || ($datos['enlace'] ?? null) !== (int) $enlace->id
                        || ($datos['ciclo'] ?? null) !== $enlace->ciclo_emision || ($datos['revision'] ?? null) !== $enlace->version_programacion
                        || ! is_array($partes) || strtolower($partes['scheme'] ?? '') !== 'https'
                        || isset($partes['user']) || isset($partes['pass']) || isset($partes['fragment'])
                        || ($partes['path'] ?? '') !== rtrim((string) parse_url(config('app.url'), PHP_URL_PATH), '/').EmitirEnlaceAsistenciaTurnoService::RUTA_RESOLUCION
                        || strtolower($partes['host'] ?? '') !== strtolower((string) parse_url(config('app.url'), PHP_URL_HOST))
                        || ($partes['port'] ?? 443) !== (parse_url(config('app.url'), PHP_URL_PORT) ?? 443)
                        || array_keys($query) !== ['token'] || ! is_string($query['token']) || ! $enlace->coincideConToken($query['token'])) {
                        throw new EntregaEnlaceNoDisponibleException('material_invalido');
                    }
                    $recifrado = Crypt::encryptString(json_encode($datos, JSON_THROW_ON_ERROR));
                } catch (Throwable) {
                    throw new EntregaEnlaceNoDisponibleException('material_invalido');
                }
                $reclamo = (string) Str::uuid();
                $sobre->forceFill(['material_cifrado' => $recifrado, 'estado' => 'INCIERTO', 'resultado' => 'INCIERTO',
                    'reclamo_hash' => hash('sha256', $reclamo), 'actualizado_at' => $ahora])->saveOrFail();
                $this->auditar($sobre);
                $final = $this->reloj->ahora();
                if (! $final->lt($sobre->vence_at) || ! $enlace->vigentePara($turno, $final)) {
                    throw new EntregaEnlaceNoDisponibleException('vigencia_modificada');
                }

                return ['material' => new EnlaceAsistenciaEmitido((int) $enlace->id, false, $url, (int) $sobre->id), 'reclamo' => $reclamo];
            });
        } catch (EntregaEnlaceNoDisponibleException $error) {
            throw $error;
        } catch (Throwable) {
            throw new EntregaEnlaceNoDisponibleException('preparacion_interrumpida');
        }
        if (isset($resultado['error'])) {
            throw new EntregaEnlaceNoDisponibleException($resultado['error']);
        }
        try {
            return $consumidor($resultado['material'], $resultado['reclamo']);
        } catch (Throwable) {
            throw new EntregaEnlaceNoDisponibleException('consumidor_interrumpido');
        }
    }

    /** El consumidor debe aportar un resultado conocido; INCIERTO nunca habilita otro envío. */
    public function registrarResultado(int $sobreId, string $reclamo, string $resultado): void
    {
        $this->autorizarProceso();
        if (! in_array($resultado, ['ACEPTADO', 'FALLO_CONFIRMADO', 'FALLO_DEFINITIVO', 'INCIERTO'], true)) {
            throw new EntregaEnlaceNoDisponibleException('resultado_invalido');
        }
        $this->bajoLocks($sobreId, function (SobreEntregaEnlace $sobre, EnlaceAsistenciaTurno $enlace, Turno $turno, CarbonImmutable $ahora) use ($reclamo, $resultado): void {
            if (! is_string($sobre->reclamo_hash) || ! hash_equals($sobre->reclamo_hash, hash('sha256', $reclamo))) {
                throw new EntregaEnlaceNoDisponibleException('reclamo_invalido');
            }
            if ($sobre->estado === 'CERRADO') {
                return;
            }
            if (! $ahora->lt($sobre->vence_at)) {
                $this->cerrar($sobre, 'VENCIDO', $ahora);
            } elseif (! $enlace->vigentePara($turno, $ahora)) {
                $this->cerrar($sobre, 'INVALIDADO', $ahora);
            } elseif ($sobre->resultado === $resultado) {
                return;
            } elseif (in_array($resultado, ['ACEPTADO', 'FALLO_DEFINITIVO'], true)) {
                $this->cerrar($sobre, $resultado, $ahora);
            } else {
                $sobre->forceFill(['estado' => $resultado === 'FALLO_CONFIRMADO' ? 'DISPONIBLE' : 'INCIERTO',
                    'resultado' => $resultado, 'actualizado_at' => $ahora])->saveOrFail();
                $this->auditar($sobre);
            }
        });
    }

    /** Cota reducible por el consumidor (p. ej. fin de su ventana de reintentos); jamás se extiende. */
    public function limitarVigencia(int $sobreId, CarbonImmutable $limite): void
    {
        $this->autorizarProceso();
        $this->bajoLocks($sobreId, function (SobreEntregaEnlace $sobre, EnlaceAsistenciaTurno $enlace, Turno $turno, CarbonImmutable $ahora) use ($limite): void {
            if ($sobre->estado === 'CERRADO') {
                return;
            }
            if ($limite->lt($sobre->vence_at)) {
                $sobre->vence_at = $limite;
                $sobre->actualizado_at = $ahora;
                $sobre->saveOrFail();
            }
            if (! $ahora->lt($sobre->vence_at)) {
                $this->cerrar($sobre, 'VENCIDO', $ahora);
            }
        });
    }

    /** R7: terminar la necesidad de entrega sin revocar el enlace ni revelar material. */
    public function finalizarNecesidad(int $sobreId): void
    {
        $this->autorizarProceso();
        $this->bajoLocks($sobreId, function (SobreEntregaEnlace $sobre, EnlaceAsistenciaTurno $enlace, Turno $turno, CarbonImmutable $ahora): void {
            if ($sobre->estado !== 'CERRADO') {
                $this->cerrar($sobre, 'FINALIZADO', $ahora);
            }
        });
    }

    /** Limpieza invocable por un proceso interno; sin Scheduler nuevo ni descifrado. */
    public function depurar(int $sobreId): void
    {
        $this->autorizarProceso();
        $this->bajoLocks($sobreId, function (SobreEntregaEnlace $sobre, EnlaceAsistenciaTurno $enlace, Turno $turno, CarbonImmutable $ahora): void {
            if ($sobre->estado === 'CERRADO') {
                return;
            }
            if (! $ahora->lt($sobre->vence_at)) {
                $this->cerrar($sobre, 'VENCIDO', $ahora);
            } elseif (! $enlace->vigentePara($turno, $ahora)) {
                $this->cerrar($sobre, 'INVALIDADO', $ahora);
            }
        });
    }

    private function bajoLocks(int $sobreId, Closure $operacion): mixed
    {
        $referencia = SobreEntregaEnlace::query()->useWritePdo()->find($sobreId, ['id', 'enlace_id']);
        $enlace = $referencia === null ? null : EnlaceAsistenciaTurno::query()->useWritePdo()->find($referencia->enlace_id);
        if ($enlace === null) {
            throw new EntregaEnlaceNoDisponibleException('sin_sobre_recuperable');
        }

        return $this->transacciones->ejecutar($enlace->turno_id, function (Turno $turno, CarbonImmutable $ahora) use ($sobreId, $enlace, $operacion): mixed {
            EnlaceAsistenciaTurno::versionActualDe($turno);
            $actual = EnlaceAsistenciaTurno::query()->lockForUpdate()->findOrFail($enlace->id);
            $sobre = SobreEntregaEnlace::query()->lockForUpdate()->findOrFail($sobreId);
            if ((int) $sobre->enlace_id !== (int) $actual->id) {
                throw new EntregaEnlaceNoDisponibleException('asociacion_invalida');
            }

            return $operacion($sobre, $actual, $turno, $ahora);
        });
    }

    private function cerrar(SobreEntregaEnlace $sobre, string $resultado, CarbonImmutable $ahora): void
    {
        $sobre->forceFill(['material_cifrado' => null, 'estado' => 'CERRADO', 'resultado' => $resultado,
            'cerrado_at' => $ahora, 'actualizado_at' => $ahora])->saveOrFail();
        $this->auditar($sobre);
    }

    private function auditar(SobreEntregaEnlace $sobre): void
    {
        $registro = $this->auditoria->registrar(evento: 'sobre_entrega_actualizado', modulo: 'Turnos', auditable: $sobre,
            valoresNuevos: ['enlace_id' => $sobre->enlace_id, 'estado' => $sobre->estado, 'resultado' => $sobre->resultado],
            url: 'urn:kinesiosoft:sobre-entrega-enlace', ipAddress: '', userAgent: 'Proceso interno');
        if (! $registro->exists) {
            throw new EntregaEnlaceNoDisponibleException('auditoria_no_disponible');
        }
    }

    private function autorizarProceso(): void
    {
        if (! app()->runningInConsole() || request()->route() !== null || Auth::check()) {
            throw new EntregaEnlaceNoDisponibleException('contexto_no_autorizado');
        }
    }
}
