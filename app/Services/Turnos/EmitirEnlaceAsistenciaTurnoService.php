<?php

namespace App\Services\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\Turno;
use App\Services\AuditoriaService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Str;
use LogicException;

final class EmitirEnlaceAsistenciaTurnoService
{
    // Contrato de ubicación para el futuro resolvedor; no registra una ruta HTTP.
    public const RUTA_RESOLUCION = '/turnos/asistencia';

    public function __construct(
        private TransaccionTurnoService $transaccion,
        private AuditoriaService $auditoria,
    ) {}

    /**
     * Consumidor interno previsto: UC–27, nunca parámetros de un visitante.
     * Un ciclo ordinario corresponde al turno y revisión vigentes, no al intento
     * de correo. Repetirlo no emite otra credencial ni reconstruye el secreto.
     * No enviar/encolar la URL hasta consolidar también cualquier transacción externa.
     */
    public function emitir(int $turnoId): EnlaceAsistenciaEmitido
    {
        $baseUrl = $this->baseUrlSegura();

        return $this->transaccion->ejecutar($turnoId, function (Turno $turno, CarbonImmutable $ahora) use ($baseUrl): EnlaceAsistenciaEmitido {
            $zona = (string) config('app.timezone');
            $inicioOriginal = $turno->getRawOriginal('inicio_at');
            $finOriginal = $turno->getRawOriginal('fin_at');
            if (! is_string($inicioOriginal) || ! is_string($finOriginal)) {
                throw new DomainException('El turno no admite emisión de enlace.');
            }
            $inicio = CarbonImmutable::parse($inicioOriginal, $zona);
            $fin = CarbonImmutable::parse($finOriginal, $zona);
            $version = EnlaceAsistenciaTurno::versionActualDe($turno);
            if ($version < 1 || ! $fin->gt($inicio) || ! $ahora->lt($inicio)
                || ! in_array($turno->estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)) {
                throw new DomainException('El turno no admite emisión de enlace.');
            }
            $existente = EnlaceAsistenciaTurno::query()->where('turno_id', $turno->id)
                ->where('version_programacion', $version)->lockForUpdate()->first();
            if ($existente !== null) {
                if (! $existente->vigentePara($turno, $ahora)) {
                    throw new DomainException('El ciclo de emisión no admite otra credencial.');
                }

                return new EnlaceAsistenciaEmitido((int) $existente->id, false, null);
            }

            $token = bin2hex(random_bytes(32));
            $enlace = new EnlaceAsistenciaTurno;
            $enlace->forceFill([
                'turno_id' => $turno->id, 'paciente_id' => $turno->paciente_id,
                'kinesiologa_id' => $turno->kinesiologa_id, 'version_programacion' => $version,
                'ciclo_emision' => (string) Str::uuid(), 'token_hash' => hash('sha256', $token),
                'alcance' => EnlaceAsistenciaTurno::ALCANCE, 'tipo' => $turno->tipo->value,
                'inicio_at' => $inicioOriginal, 'fin_at' => $finOriginal, 'zona_horaria' => $zona,
                'emitido_at' => $ahora, 'vence_at' => $inicio,
            ])->saveOrFail();
            $this->auditoria->registrar(
                evento: 'enlace_asistencia_emitido', modulo: 'Turnos', auditable: $enlace,
                valoresNuevos: [
                    'turno_id' => $turno->id, 'paciente_id' => $turno->paciente_id,
                    'version_programacion' => $version, 'ciclo_emision' => $enlace->ciclo_emision,
                    'alcance' => EnlaceAsistenciaTurno::ALCANCE, 'emitido_at' => $ahora->toIso8601String(),
                    'vence_at' => $inicio->toIso8601String(),
                ],
                url: 'urn:kinesiosoft:emision-enlace-asistencia', ipAddress: '', userAgent: 'Proceso interno',
            );

            return new EnlaceAsistenciaEmitido((int) $enlace->id, true, $baseUrl.self::RUTA_RESOLUCION.'?token='.$token);
        });
    }

    private function baseUrlSegura(): string
    {
        $base = config('app.url');
        $partes = is_string($base) ? parse_url($base) : false;
        if (! is_array($partes) || strtolower($partes['scheme'] ?? '') !== 'https'
            || empty($partes['host']) || isset($partes['user']) || isset($partes['pass'])
            || isset($partes['query']) || isset($partes['fragment']) || preg_match('/[\x00-\x20\\\\]/', $base)) {
            throw new LogicException('La emisión requiere una URL base HTTPS configurada y sin credenciales.');
        }

        return rtrim($base, '/');
    }
}
