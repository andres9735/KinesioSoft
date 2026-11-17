<?php

namespace App\Services\Turnos;

use App\Models\DespachoOfertaAdelanto;
use App\Models\OfertaAdelanto;
use App\Models\Paciente;
use App\Models\RecordatorioTurno;
use App\Services\Agenda\RelojAplicacion;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Throwable;

final class PrepararDespachoOfertaAdelantoService
{
    // Contrato para el resolvedor del subpaso 6. Todavía no registra una ruta HTTP.
    public const RUTA_RESOLUCION = '/turnos/adelanto';

    public function __construct(private readonly RelojAplicacion $reloj) {}

    /** Dentro de la transacción y mutex de agenda que confirman la primera oferta. */
    public function preparar(OfertaAdelanto $oferta): DespachoOfertaAdelanto
    {
        if (DB::transactionLevel() < 1 || ! $oferta->exists) {
            throw new LogicException('La preparación requiere la oferta persistida y su transacción.');
        }
        $existente = DespachoOfertaAdelanto::query()->where('oferta_id', $oferta->id)->lockForUpdate()->first();
        if ($existente !== null) {
            return $existente;
        }
        $correo = Paciente::query()->findOrFail($oferta->paciente_id)->user()->value('email');
        if (! RecordatorioTurno::correoUtilizable($correo)) {
            throw new RuntimeException('El destinatario de la oferta no admite correo.');
        }
        $token = bin2hex(random_bytes(32));
        $url = $this->baseSegura().self::RUTA_RESOLUCION.'?token='.$token;
        try {
            $cifrado = Crypt::encryptString(json_encode([
                'uso' => 'oferta-adelanto-v1', 'oferta_id' => (int) $oferta->id,
                'paciente_id' => (int) $oferta->paciente_id, 'destinatario' => $correo, 'url' => $url,
            ], JSON_THROW_ON_ERROR));
        } catch (Throwable) {
            throw new RuntimeException('No se pudo proteger el material de entrega de la oferta.');
        }

        return DespachoOfertaAdelanto::query()->create([
            'oferta_id' => $oferta->id, 'paciente_id' => $oferta->paciente_id,
            'token_hash' => hash('sha256', $token), 'material_cifrado' => $cifrado,
            'estado' => 'PENDIENTE', 'preparado_at' => $this->reloj->ahora(), 'vence_at' => $oferta->vence_at,
        ]);
    }

    public function baseSegura(): string
    {
        $base = config('app.url');
        $partes = is_string($base) ? parse_url($base) : false;
        if (! is_array($partes) || strtolower($partes['scheme'] ?? '') !== 'https' || empty($partes['host'])
            || isset($partes['user']) || isset($partes['pass']) || isset($partes['query']) || isset($partes['fragment'])
            || preg_match('/[\x00-\x20\\\\]/', $base)) {
            throw new LogicException('La oferta requiere una URL base HTTPS canónica sin credenciales.');
        }

        return rtrim($base, '/');
    }
}
