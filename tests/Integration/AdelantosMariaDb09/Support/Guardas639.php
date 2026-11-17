<?php

namespace Tests\Integration\AdelantosMariaDb09\Support;

use DateTimeZone;
use RuntimeException;
use SensitiveParameter;

/** Guardas puras: no bootstrap, conexiones, archivos ni credenciales en errores. */
final class Guardas639
{
    public const HOST = '127.0.0.1';

    public const PORT = 3308;

    public const DATABASE = 'adelantos_09_sandbox';

    public const CUENTAS = ['pruebas' => 'adelanto09_runner', 'migraciones' => 'adelanto09_migrador'];

    public static function perfil(#[SensitiveParameter] array $p, string $modo): void
    {
        $obligatorias = ['AUTORIZACION', 'DATABASE', 'HOST', 'PORT', 'DATADIR', 'USERNAME', 'PASSWORD', 'RUN', 'APP_KEY', 'TIMEZONE'];
        foreach ($obligatorias as $clave) {
            if (! is_string($p[$clave] ?? null) || $p[$clave] === '') {
                self::rechazar();
            }
        }
        if (
            ! isset(self::CUENTAS[$modo]) || $p['AUTORIZACION'] !== 'AUTORIZADA_639_'.$modo
            || $p['DATABASE'] !== self::DATABASE || $p['HOST'] !== self::HOST || $p['PORT'] !== (string) self::PORT
            || $p['USERNAME'] !== self::CUENTAS[$modo] || ! preg_match('/\A[a-f0-9]{32}\z/', $p['RUN'])
            || ! str_starts_with($p['APP_KEY'], 'base64:')
            || strlen((string) base64_decode(substr($p['APP_KEY'], 7), true)) !== 32
            || ! in_array($p['TIMEZONE'], DateTimeZone::listIdentifiers(), true)
            || array_intersect_key($p, array_flip(['URL', 'SOCKET', 'READ', 'WRITE'])) !== []
        ) {
            self::rechazar();
        }
        self::datadir($p['DATADIR']);
    }

    public static function conexion(#[SensitiveParameter] array $c, string $modo): void
    {
        if (
            ! isset(self::CUENTAS[$modo]) || ($c['driver'] ?? null) !== 'mysql'
            || ($c['database'] ?? null) !== self::DATABASE || ($c['host'] ?? null) !== self::HOST
            || (string) ($c['port'] ?? '') !== (string) self::PORT || ($c['username'] ?? null) !== self::CUENTAS[$modo]
            || ($c['engine'] ?? null) !== 'InnoDB' || ($c['prefix'] ?? '') !== ''
            || ! empty($c['url']) || ! empty($c['unix_socket']) || ! empty($c['read']) || ! empty($c['write'])
            || ! empty($c['options']) || ! empty($c['sticky'])
        ) {
            self::rechazar();
        }
    }

    public static function servidor(array $s, string $modo): void
    {
        if (
            ! isset(self::CUENTAS[$modo]) || ($s['db'] ?? null) !== self::DATABASE
            || ! in_array($s['usuario'] ?? '', [self::CUENTAS[$modo].'@localhost', self::CUENTAS[$modo].'@127.0.0.1'], true)
            || ! str_contains($s['version'] ?? '', '10.11.19-MariaDB') || (string) ($s['puerto'] ?? '') !== (string) self::PORT
            || ($s['aislamiento'] ?? null) !== 'REPEATABLE-READ' || (int) ($s['fk'] ?? 0) !== 1
            || (int) ($s['autocommit'] ?? 0) !== 1
            || ! in_array('STRICT_TRANS_TABLES', explode(',', $s['sql_mode'] ?? ''), true)
            || strcasecmp($s['engine'] ?? '', 'InnoDB') !== 0
        ) {
            self::rechazar();
        }
        self::datadir($s['datadir'] ?? null);
    }

    /** Destino fijo del futuro servidor descartable; no procede de .env ni puede redirigirse. */
    public static function datadirEsperado(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 4)).'/storage/framework/testing/agenda-mariadb-639-instancia/data';
    }

    public static function datadir(mixed $ruta): void
    {
        if (self::normalizarDirectorioWindows($ruta) !== self::normalizarDirectorioWindows(self::datadirEsperado())) {
            self::rechazar();
        }
    }

    private static function normalizarDirectorioWindows(mixed $ruta): string
    {
        if (! is_string($ruta) || $ruta === '' || preg_match('/[\x00-\x1f]/', $ruta)) {
            self::rechazar();
        }
        $ruta = preg_replace('~/+~', '/', str_replace('\\', '/', $ruta));
        // No resolver alias, rutas relativas, UNC ni segmentos ambiguos sin inspeccionar el disco.
        if (! preg_match('~\A[A-Za-z]:/[^:*?"<>|]+\z~', $ruta)) {
            self::rechazar();
        }
        foreach (explode('/', substr(rtrim($ruta, '/'), 3)) as $segmento) {
            if ($segmento === '' || $segmento === '.' || $segmento === '..' || preg_match('/[. ]\z/', $segmento)) {
                self::rechazar();
            }
        }

        return strtolower(rtrim($ruta, '/'));
    }

    /** SHOW GRANTS se procesa en memoria; nunca se imprime su cláusula de autenticación. */
    public static function privilegios(#[SensitiveParameter] array $grants, string $modo): void
    {
        $permitidos = $modo === 'pruebas' ? ['SELECT', 'INSERT', 'UPDATE', 'DELETE']
            : ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'INDEX', 'DROP', 'REFERENCES', 'TRIGGER'];
        $vistos = [];
        foreach ($grants as $grant) {
            if (! is_string($grant) || str_contains($grant, 'WITH GRANT OPTION')) {
                self::rechazar();
            }
            if (preg_match('/\AGRANT USAGE ON \*\.\* TO /', $grant)) {
                continue;
            }
            if (
                ! preg_match('/\AGRANT ([A-Z ,]+) ON `([^`]+)`\.\* TO /', $grant, $m)
                // En GRANT los guiones bajos NO escapados son comodines, aun entre backticks.
                || $m[2] !== str_replace('_', '\\_', self::DATABASE)
            ) {
                self::rechazar();
            }
            foreach (explode(', ', $m[1]) as $privilegio) {
                if (! in_array($privilegio, $permitidos, true)) {
                    self::rechazar();
                }
                $vistos[] = $privilegio;
            }
        }
        if (! isset(self::CUENTAS[$modo]) || array_diff($permitidos, $vistos) !== []) {
            self::rechazar();
        }
    }

    public static function sentencia(string $sql, string $modo): void
    {
        if (
            preg_match('/\b(USE|TRUNCATE|GRANT|REVOKE|OUTFILE|DUMPFILE|LOAD_FILE|SLEEP|BENCHMARK)\b/i', $sql)
            || preg_match('/\bSET\s+(GLOBAL|PASSWORD)\b/i', $sql)
            || ($modo === 'pruebas' && preg_match('/\b(CREATE|ALTER|DROP|RENAME)\b/i', $sql))
        ) {
            self::rechazar();
        }
    }

    public static function origen(?string $run, string $esperado): void
    {
        if (! is_string($run) || ! hash_equals($esperado, $run)) {
            self::rechazar();
        }
    }

    private static function rechazar(): never
    {
        throw new RuntimeException('639: perfil, destino, privilegios u operación no autorizados.');
    }
}
