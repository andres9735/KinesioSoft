<?php

namespace App\Services\Turnos;

use App\Exceptions\Turnos\AccesoEnlaceRechazadoException;
use App\Models\EnlaceAsistenciaTurno;
use App\Services\Agenda\RelojAplicacion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use JsonException;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Cookie;

final class AutorizacionNavegadorEnlaceService
{
    public const COOKIE = '__Secure-ks_asistencia';

    public const RESULTADO = '__Secure-ks_asistencia_resultado';

    public const RECUPERACION = '__Secure-ks_asistencia_recuperacion_';

    public const PATH = '/turnos/asistencia';

    public function __construct(private RelojAplicacion $reloj) {}

    /** Sólo después de verificar posesión del enlace. El original nunca entra en el payload. */
    public function crear(EnlaceAsistenciaTurno $enlace): array
    {
        $limite = CarbonImmutable::parse($enlace->getRawOriginal('vence_at'), $enlace->zona_horaria);
        // Expires del navegador tiene precisión de segundos: no anticipar el límite
        // funcional con microsegundos, revalidado exactamente en servidor en cada POST.
        $vence = $limite->getTimestamp() + ($limite->microsecond > 0 ? 1 : 0);
        $datos = [
            'uso' => 'uc28-navegador-v1', 'enlace' => (int) $enlace->id,
            'revision' => (int) $enlace->version_programacion,
            'exp' => min($vence, $this->reloj->ahora()->addMinutes(15)->getTimestamp()),
            'csrf' => bin2hex(random_bytes(32)),
            'operaciones' => ['confirmar' => (string) Str::uuid(), 'cancelar' => (string) Str::uuid()],
            'recuperacion_exp' => $this->reloj->ahora()->addMinutes(15)->getTimestamp(),
        ];

        return [$datos, $this->cookie(self::COOKIE, $datos)];
    }

    public function leer(#[SensitiveParameter] string $cifrado, string $uso = 'uc28-navegador-v1'): array
    {
        try {
            $datos = json_decode(Crypt::decryptString($cifrado), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw new AccesoEnlaceRechazadoException;
        }
        if (! is_array($datos) || ($datos['uso'] ?? null) !== $uso
            || ! is_int($datos['enlace'] ?? null) || $datos['enlace'] < 1
            || ! is_int($datos['exp'] ?? null)) {
            throw new AccesoEnlaceRechazadoException;
        }
        if ($this->reloj->ahora()->getTimestamp() >= $datos['exp']) {
            throw new AccesoEnlaceRechazadoException('vencido');
        }
        if ($uso === 'uc28-navegador-v1' && (! is_int($datos['revision'] ?? null)
            || ! is_string($datos['csrf'] ?? null) || preg_match('/\A[0-9a-f]{64}\z/', $datos['csrf']) !== 1
            || ! is_array($datos['operaciones'] ?? null) || ! is_int($datos['recuperacion_exp'] ?? null)
            || ! is_string($datos['operaciones']['confirmar'] ?? null) || ! Str::isUuid($datos['operaciones']['confirmar'])
            || ! is_string($datos['operaciones']['cancelar'] ?? null) || ! Str::isUuid($datos['operaciones']['cancelar']))) {
            throw new AccesoEnlaceRechazadoException;
        }

        return $datos;
    }

    /** Comprobantes de consulta emitidos antes de decidir, independientes entre pestañas. */
    public function recuperaciones(#[SensitiveParameter] array $datos): array
    {
        $cookies = [];
        foreach ($datos['operaciones'] as $accion => $operacion) {
            $cookies[] = $this->cookie(self::nombreRecuperacion($operacion), [
                'uso' => 'uc28-recuperacion-v1', 'enlace' => $datos['enlace'],
                'revision' => $datos['revision'], 'accion' => $accion, 'operacion' => $operacion,
                'exp' => $datos['recuperacion_exp'],
            ]);
        }

        return $cookies;
    }

    public static function nombreRecuperacion(string $operacion): string
    {
        return self::RECUPERACION.str_replace('-', '', $operacion);
    }

    public function resultado(int $enlaceId, array $resultado): Cookie
    {
        return $this->cookie(self::RESULTADO, [
            'uso' => 'uc28-resultado-v1', 'enlace' => $enlaceId,
            'exp' => $this->reloj->ahora()->addMinutes(15)->getTimestamp(),
            'accion' => $resultado['accion'], 'replay' => $resultado['replay'],
            'codigo' => $resultado['codigo'] ?? 'exito',
        ]);
    }

    private function cookie(string $nombre, #[SensitiveParameter] array $datos): Cookie
    {
        $path = str_starts_with($nombre, self::RECUPERACION) ? self::PATH.'/resultado' : self::PATH;

        return new Cookie($nombre, Crypt::encryptString(json_encode($datos, JSON_THROW_ON_ERROR)), $datos['exp'], $path, null, true, true, false, Cookie::SAMESITE_STRICT);
    }
}
